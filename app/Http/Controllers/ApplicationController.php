<?php

namespace App\Http\Controllers;

use App\Admin\ProfileForm;
use App\Admin\Resources\CertificationResource;
use App\Admin\Resources\EducationResource;
use App\Admin\Resources\ExperienceResource;
use App\Admin\Resources\ExpertiseAreaResource;
use App\Admin\Resources\ProjectResource;
use App\Admin\Resources\SkillCategoryResource;
use App\Admin\Resources\SocialLinkResource;
use App\Admin\Resources\TestimonialResource;
use App\Models\Portfolio;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\SocialLink;
use App\Support\CurrentPortfolio;
use App\Support\Templates;
use App\Support\Username;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Public "create your portfolio" application. Everything the templates display is collected and stored
 * as a `pending` portfolio (published content, non-public portfolio) that an admin approves.
 * Field labels and rules come from the admin Resources so both forms validate the same way.
 */
class ApplicationController extends Controller
{
    public const MAX_ROWS = 20;

    private const PROFILE_FIELDS = ['full_name', 'headline', 'intro', 'about', 'location', 'email', 'phone', 'show_phone', 'open_to_work'];

    /** Repeatable groups: input key => [heading, one row's name, resource, fields]. */
    private const GROUPS = [
        'experience' => ['Experience', 'job', ExperienceResource::class, ['company', 'company_url', 'role', 'employment_type', 'location', 'start_date', 'end_date', 'is_current', 'summary', 'highlights', 'technologies']],
        'projects' => ['Projects', 'project', ProjectResource::class, ['name', 'tagline', 'category', 'description', 'role', 'start_date', 'end_date', 'live_url', 'repo_url', 'technologies', 'is_featured']],
        'skills' => ['Skills', 'skill group', SkillCategoryResource::class, ['name']],
        'education' => ['Education', 'education entry', EducationResource::class, ['institution', 'degree', 'field', 'location', 'start_year', 'end_year']],
        'certifications' => ['Certifications', 'certification', CertificationResource::class, ['name', 'issuer', 'issued_on', 'credential_url']],
        'links' => ['Professional links', 'link', SocialLinkResource::class, ['platform', 'url']],
        'expertise' => ['Areas of expertise', 'expertise area', ExpertiseAreaResource::class, ['title', 'description']],
        'testimonials' => ['Testimonials', 'testimonial', TestimonialResource::class, ['name', 'position', 'company', 'quote']],
    ];

    public function create()
    {
        return view('site.apply.create', [
            'profileFields' => $this->profileFields(),
            'groups' => $this->groups(),
            'templates' => Templates::available(),
        ]);
    }

    public function store(Request $request)
    {
        // Bots fill the hidden field; pretend success so they don't retry.
        if (filled($request->input('website'))) {
            return redirect()->route('apply.submitted');
        }

        $groups = $this->groups();
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))] + $this->withoutEmptyRows($request, $groups));
        $data = $request->validate($this->rules($groups), [], $this->attributeNames($groups));

        $stored = [];
        try {
            $portfolio = DB::transaction(function () use ($request, $data, $groups, &$stored) {
                $profile = Arr::only($data, self::PROFILE_FIELDS);
                $portfolio = Portfolio::provision(
                    ['name' => $data['full_name'], 'username' => $data['username'], 'status' => 'pending', 'template' => $data['template'] ?? Templates::DEFAULT],
                    ['headline' => $profile['headline'] ?? ''] + $profile + ['show_phone' => false, 'open_to_work' => false],
                );

                CurrentPortfolio::using($portfolio, function () use ($request, $data, $groups, &$stored) {
                    $this->storeUploads($request, Profile::query()->firstOrFail(), $stored);
                    foreach ($groups as $key => $group) {
                        foreach ($data[$key] ?? [] as $row) {
                            $this->storeRow($key, $group, $row);
                        }
                    }
                });

                return $portfolio;
            });
        } catch (\Throwable $e) {
            foreach ($stored as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }

        return redirect()->route('apply.submitted')->with('applied_url', $portfolio->url());
    }

    public function submitted()
    {
        return view('site.apply.submitted');
    }

    /** Profile fields shown on the form (labels from the admin profile form). */
    private function profileFields(): array
    {
        $fields = Arr::only(ProfileForm::fields(), self::PROFILE_FIELDS);
        $fields['headline']['rules'] = ['nullable', 'string', 'max:120'];
        $fields['email'] = ['label' => 'Email', 'help' => 'Shown on your portfolio, and how we contact you about this application.', 'rules' => ['required', 'email', 'max:150']] + $fields['email'];

        return $fields;
    }

    /** key => [label, singular, resource, fields => [name => field definition]] */
    private function groups(): array
    {
        $tags = ['type' => 'tags', 'help' => 'Comma separated.'];

        return collect(self::GROUPS)->map(function (array $g, string $key) use ($tags) {
            [$label, $singular, $resource, $names] = $g;
            $fields = Arr::only($resource::fields(), $names);
            if ($key === 'skills') {
                $fields['name'] = ['type' => 'text', 'label' => 'Category', 'help' => 'e.g. Backend, Frontend, Tools.', 'rules' => ['required', 'string', 'max:100', 'distinct:ignore_case']];
                $fields['skills'] = $tags + ['label' => 'Skills', 'rules' => ['required', 'string', 'max:1000']];
            }

            return ['label' => $label, 'singular' => $singular, 'resource' => $resource, 'fields' => $fields];
        })->all();
    }

    /** Rows whose text fields are all blank are dropped (select/checkbox defaults don't count as content). */
    private function withoutEmptyRows(Request $request, array $groups): array
    {
        $out = [];
        foreach ($groups as $key => $group) {
            $rows = $request->input($key);
            if (! is_array($rows)) {
                continue; // absent, or rejected by the "array" rule
            }
            $text = array_keys(array_filter($group['fields'], fn ($f) => ! in_array($f['type'], ['select', 'checkbox'], true)));
            $out[$key] = array_values(array_filter($rows, fn ($row) => ! is_array($row) || Arr::first($text, fn ($n) => filled($row[$n] ?? null)) !== null));
        }

        return $out;
    }

    private function rules(array $groups): array
    {
        $image = config('portfolio.uploads.image_max_kb');
        $resume = config('portfolio.uploads.resume_max_kb');
        $rules = [
            'username' => Username::rules(),
            'template' => ['nullable', Rule::in(array_keys(Templates::available()))],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$image}", 'dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000'],
            'resume' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', "max:{$resume}"],
        ] + collect($this->profileFields())->map(fn ($f) => $f['rules'])->all();

        foreach ($groups as $key => $group) {
            $rules[$key] = ['nullable', 'array', 'max:'.self::MAX_ROWS];
            $rules["{$key}.*"] = ['array'];
            foreach ($group['fields'] as $name => $field) {
                $rules["{$key}.*.{$name}"] = array_map(fn ($rule) => match (true) {
                    // Sibling-field rules must point at the same row.
                    is_string($rule) && str_starts_with($rule, 'after_or_equal:') => 'after_or_equal:'.$key.'.*.'.substr($rule, 15),
                    // gte fails when the start year is blank; an end (graduation) year alone is fine.
                    $rule === 'gte:start_year' => fn ($attribute, $value, $fail) => filled($start = request()->input(substr($attribute, 0, -8).'start_year')) && (int) $value < (int) $start
                        ? $fail('The end year must be the same as or after the start year.') : null,
                    default => $rule,
                }, $field['rules']);
                if ($field['type'] === 'tags') {
                    $rules["{$key}.*.{$name}"][] = fn ($attribute, $value, $fail) => collect(explode(',', (string) $value))->contains(fn ($t) => mb_strlen(trim($t)) > 100)
                        ? $fail('Each item must be 100 characters or fewer.') : null;
                }
            }
        }

        return $rules;
    }

    /** "experience.0.company" → "company (experience 1)" in error messages. */
    private function attributeNames(array $groups): array
    {
        $names = ['full_name' => 'full name'];
        foreach ($groups as $key => $group) {
            foreach ($group['fields'] as $name => $field) {
                $names["{$key}.*.{$name}"] = mb_strtolower($field['label']).' ('.$group['singular'].' :position)';
            }
        }

        return $names;
    }

    private function storeUploads(Request $request, Profile $profile, array &$stored): void
    {
        foreach (['avatar' => 'public', 'resume' => 'local'] as $collection => $disk) {
            if (! $file = $request->file($collection)) {
                continue;
            }
            $stored[] = [$disk, $path = $file->store($collection, $disk)];
            $profile->media()->create([
                'collection' => $collection,
                'disk' => $disk, // the CV is served through a download route, not a public URL
                'path' => $path,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'alt' => $collection === 'avatar' ? $profile->full_name : null,
                'is_active' => $collection === 'resume',
            ]);
        }
    }

    private function storeRow(string $key, array $group, array $row): void
    {
        $row = Arr::only($row, array_keys($group['fields']));
        $resource = $group['resource'];

        if ($key === 'skills') {
            $category = $resource::save($resource::newModel(), ['name' => $row['name'], 'status' => 'published']);
            foreach (array_unique($resource::splitTags($row['skills'])) as $i => $name) {
                Skill::create(['skill_category_id' => $category->id, 'name' => $name, 'status' => 'published', 'sort_order' => $i + 1]);
            }

            return;
        }
        if ($key === 'links') {
            $row['label'] = SocialLink::PLATFORMS[$row['platform']];
        }
        // Resource::save handles lines/tags/checkboxes, slugs and sort order exactly like the admin forms.
        $resource::save($resource::newModel(), $row + ['status' => 'published']);
    }
}
