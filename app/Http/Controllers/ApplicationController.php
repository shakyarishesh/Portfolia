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
use App\Models\PortfolioType;
use App\Models\Project;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\SocialLink;
use App\Models\User;
use App\Services\CvReader;
use App\Services\CvReaderException;
use App\Support\CurrentPortfolio;
use App\Support\Templates;
use App\Support\Username;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;

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
        // Plus the profession-specific fields of the chosen type's modules (see groups()).
        'projects' => ['Projects', 'project', ProjectResource::class, ['name', 'tagline', 'category', 'description', 'role', 'start_date', 'end_date', 'is_current', 'live_url', 'technologies', 'is_featured']],
        'skills' => ['Skills', 'skill group', SkillCategoryResource::class, ['name']],
        'education' => ['Education', 'education entry', EducationResource::class, ['institution', 'degree', 'field', 'location', 'start_year', 'end_year']],
        'certifications' => ['Certifications', 'certification', CertificationResource::class, ['name', 'issuer', 'issued_on', 'credential_url']],
        'links' => ['Professional links', 'link', SocialLinkResource::class, ['platform', 'url']],
        'expertise' => ['Areas of expertise', 'expertise area', ExpertiseAreaResource::class, ['title', 'description']],
        'testimonials' => ['Testimonials', 'testimonial', TestimonialResource::class, ['name', 'position', 'company', 'quote']],
    ];

    /** Step 1 picks the portfolio type (/create); the form for that type follows (/create?type=…[&disciplines[]=…]). */
    public function create(Request $request)
    {
        if (! $request->filled('type') || $request->query('step') === 'type') {
            return view('site.apply.type', [
                'types' => PortfolioType::active()->ordered()->get(),
                'disciplineOptions' => PortfolioType::disciplineOptions(),
            ]);
        }
        [$type, $disciplines] = $this->chosenType($request);

        return view('site.apply.create', [
            'type' => $type,
            'disciplines' => $disciplines,
            'disciplineNames' => array_values(array_intersect_key(PortfolioType::disciplineOptions(), array_flip($disciplines))),
            'profileFields' => $this->profileFields(),
            'groups' => $this->groups($type->slug, $disciplines),
            'templates' => Templates::forType($type->slug),
        ]);
    }

    /**
     * The validated ?type= (an active type) and, for Hybrid, its disciplines (at least two other active types).
     *
     * @return array{0: PortfolioType, 1: list<string>}
     */
    private function chosenType(Request $request): array
    {
        $type = PortfolioType::active()->where('slug', (string) $request->input('type'))->first();
        if (! $type) {
            throw ValidationException::withMessages(['type' => 'Choose the type of portfolio you want to create.'])->redirectTo(route('apply.create'));
        }
        $disciplines = [];
        if ($type->isHybrid()) {
            $disciplines = array_values(array_intersect(array_keys(PortfolioType::disciplineOptions()), (array) $request->input('disciplines')));
            if (count($disciplines) < 2) {
                throw ValidationException::withMessages(['disciplines' => 'A hybrid portfolio combines at least two disciplines — tick two or more.'])
                    ->redirectTo(route('apply.create', ['step' => 'type', 'type' => $type->slug]));
            }
        }

        return [$type, $disciplines];
    }

    public function store(Request $request)
    {
        // Bots fill the hidden field; pretend success so they don't retry.
        if (filled($request->input('website'))) {
            return redirect()->route('apply.submitted');
        }

        [$type, $disciplines] = $this->chosenType($request);
        $groups = $this->groups($type->slug, $disciplines);
        $request->merge(['username' => mb_strtolower(trim((string) $request->input('username')))] + $this->withoutEmptyRows($request, $groups));
        $data = $request->validate($this->rules($groups, $type->slug), [
            'email.unique' => 'An account already uses this email — sign in to edit your portfolio instead.',
        ], $this->attributeNames($groups));

        $stored = [];
        try {
            [$portfolio, $user] = DB::transaction(function () use ($request, $data, $groups, $type, $disciplines, &$stored) {
                // The applicant's own login: email, portfolio username or phone + password → the portal, own portfolio only.
                $phone = User::normalizePhone($data['phone'] ?? null);
                $user = User::create(['name' => $data['full_name'], 'email' => $data['email'], 'password' => $data['password'], 'is_admin' => false,
                    'phone' => $phone && ! User::where('phone', $phone)->exists() ? $phone : null]);
                $profile = Arr::only($data, self::PROFILE_FIELDS);
                $portfolio = Portfolio::provision(
                    ['name' => $data['full_name'], 'username' => $data['username'], 'status' => 'pending', 'user_id' => $user->id,
                        'type' => $type->slug, 'disciplines' => $disciplines ?: null,
                        'template' => $data['template'] ?? array_key_first(Templates::forType($type->slug)) ?? Templates::DEFAULT],
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

                return [$portfolio, $user];
            });
        } catch (\Throwable $e) {
            foreach ($stored as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }
            throw $e;
        }

        if (! Auth::check()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('apply.submitted')->with('applied_url', $portfolio->url());
    }

    /** "Preview" step: the form so far, rendered by the session-only demo with the chosen template. Nothing is saved. */
    public function preview(Request $request)
    {
        [$type, $disciplines] = $this->chosenType($request);
        $str = fn ($v, int $max) => is_string($v) ? mb_substr(trim($v), 0, $max) : '';
        $url = fn ($v) => is_string($v) && preg_match('#^https?://#i', $v) && filter_var($v, FILTER_VALIDATE_URL) ? mb_substr($v, 0, 255) : null;
        $rows = fn (string $key) => array_values(array_filter((array) $request->input($key), fn ($r) => is_array($r)));

        $projects = [];
        foreach (array_slice(array_filter($rows('projects'), fn ($r) => filled($r['name'] ?? null)), 0, 6) as $r) {
            $details = [];
            foreach (config('portfolio.project_details', []) as $name => $f) {
                if (empty($f['private']) && filled($r[$name] ?? null)) {
                    $details[$name] = match ($f['type']) {
                        'lines' => array_slice(ProjectResource::splitLines($str($r[$name], 4000)), 0, 30),
                        'checkboxes' => array_values(array_intersect((array) $r[$name], $disciplines)),
                        'url' => $url($r[$name]),
                        default => $str($r[$name], 2000),
                    };
                }
            }
            $projects[] = ['name' => $str($r['name'], 80), 'description' => $str($r['description'] ?? null, 1000) ?: $str($r['tagline'] ?? null, 255),
                'technologies' => $str($r['technologies'] ?? null, 300), 'url' => $url($r['live_url'] ?? null), 'details' => array_filter($details)];
        }
        $templates = Templates::forType($type->slug);
        $request->session()->put('demo', [
            'name' => $str($request->input('full_name'), 80) ?: 'Your name',
            'headline' => $str($request->input('headline'), 120),
            'bio' => $str($request->input('about'), 1500) ?: $str($request->input('intro'), 600),
            'location' => $str($request->input('location'), 80),
            'email' => filter_var($request->input('email'), FILTER_VALIDATE_EMAIL) ? $str($request->input('email'), 150) : null,
            'skills' => implode(', ', array_map(fn ($r) => $str($r['skills'] ?? null, 400), $rows('skills'))),
            'projects' => $projects,
            'template' => isset($templates[$request->input('template')]) ? $request->input('template') : array_key_first($templates),
            'type' => $type->slug,
            'disciplines' => $disciplines,
        ]);

        return redirect()->route('demo.show');
    }

    public function submitted()
    {
        return view('site.apply.submitted');
    }

    /** Plain-text template of every form field, to fill in offline and upload with import(). */
    public function template(Request $request)
    {
        [$type, $disciplines] = $request->filled('type') ? $this->chosenType($request) : [PortfolioType::where('slug', PortfolioType::DEFAULT)->firstOrFail(), []];

        return response($this->detailsTemplate($type, $disciplines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="portfolio-details.txt"',
        ]);
    }

    private function detailsTemplate(PortfolioType $type, array $disciplines): string
    {
        $out = [
            '# '.config('portfolio.platform_name').' — portfolio details',
            '#',
            '# How to use this file:',
            "#  1. Write your answers after the colons. Leave anything you don't need empty.",
            '#  2. Long answers can continue on the next lines (leave a blank line between paragraphs).',
            '#  3. Sections with several entries (jobs, projects …): put a line with only --- between entries.',
            '#     Copy a whole block to add another entry.',
            '#  4. Upload this file on '.route('apply.create').' — the form is filled in for you to check.',
            '#     Add your photo and CV there, then submit.',
            '# Lines starting with # are notes and are ignored.',
            '#',
            '# Portfolio type: '.$type->name.($disciplines ? ' ('.implode(' + ', array_intersect_key(PortfolioType::disciplineOptions(), array_flip($disciplines))).')' : '')
                .' — upload the file on the form for this type.',
            '',
        ];
        foreach ($this->fileSections($type->slug, $disciplines) as $title => [$key, $fields]) {
            $out[] = "== {$title} ==";
            if ($key) {
                $out[] = '# Put a line with only --- between entries.';
            }
            foreach ($fields as $name => $field) {
                $hint = trim(implode(' ', array_filter([
                    in_array('required', $field['rules'] ?? [], true) ? 'Required.' : null,
                    $field['help'] ?? null,
                    match ($field['type']) {
                        'select', 'checkboxes' => ($field['type'] === 'select' ? 'One of: ' : 'Any of (comma separated): ').implode(', ', $field['options']).'.',
                        'checkbox' => 'yes or no.',
                        'date' => 'Date as YYYY-MM-DD'.($name === 'end_date' && isset($fields['is_current']) ? ', or "present".' : '.'),
                        default => null,
                    },
                ])));
                if ($hint !== '') {
                    $out[] = "# {$hint}";
                }
                $out[] = "{$field['label']}: ".($name === 'template' ? Templates::meta((string) array_key_first($field['options']))['name'] : '');
            }
            $out[] = '';
        }

        return implode("\n", $out);
    }

    /** Fill the form from an uploaded template: flashed as old input, so the applicant reviews it before submitting. */
    public function import(Request $request)
    {
        [$type, $disciplines] = $this->chosenType($request);
        $request->validate(['details' => ['required', 'file', 'max:200', 'mimes:txt', 'mimetypes:text/plain']], [], ['details' => 'details file']);
        [$data, $warnings] = $this->parseDetails((string) file_get_contents($request->file('details')->getRealPath()), $type->slug, $disciplines);

        return redirect()->route('apply.create', ['type' => $type->slug, 'disciplines' => $disciplines ?: null])->withInput($data)->with('import_warnings', $warnings)
            ->with('success', 'The form below was filled in from your file. Check everything, add your photo and CV, then submit.');
    }

    /** Fill the form from a CV: Gemini writes the details template from the PDF, then it goes through the same parser as import(). */
    public function importCv(Request $request, CvReader $reader)
    {
        abort_unless(CvReader::enabled(), 404);
        [$type, $disciplines] = $this->chosenType($request);
        $request->validate(['cv' => ['required', 'file', 'max:5120', 'mimes:pdf', 'mimetypes:application/pdf']], [], ['cv' => 'CV']);
        $back = redirect()->route('apply.create', ['type' => $type->slug, 'disciplines' => $disciplines ?: null]);

        // Counted only once the upload is valid, so a wrong file type doesn't use up an attempt.
        $keys = ['cv-import:'.$request->ip() => 2, 'cv-import:all' => (int) config('services.gemini.daily_limit')];
        foreach ($keys as $key => $max) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return $back->withErrors(['cv' => $key === 'cv-import:all'
                    ? 'CV reading is busy today. Please use the template below instead, or try again tomorrow.'
                    : 'You can read in up to 2 CVs a day. Please use the template below instead, or fill in the form.']);
            }
        }
        foreach ($keys as $key => $max) {
            RateLimiter::hit($key, 86400);
        }

        try {
            $text = $reader->fill($request->file('cv')->getRealPath(), $this->detailsTemplate($type, $disciplines));
        } catch (CvReaderException $e) {
            report($e);
            // Not the applicant's fault: give the attempt back. The site-wide count stays, as the quota was used.
            RateLimiter::decrement('cv-import:'.$request->ip(), 86400);

            return $back->withErrors(['cv' => 'We couldn\'t read your CV just now. Please try again later, or use the template below.']);
        }
        [$data, $warnings] = $this->parseDetails($text, $type->slug, $disciplines);

        return $back->withInput($data)->with('import_warnings', $warnings)
            ->with('success', 'The form below was filled in from your CV. Check everything carefully — it was read automatically — add your photo and CV, then submit.');
    }

    /** Sections of the details file: heading => [group key (null = top-level fields), fields]. */
    private function fileSections(string $type, array $disciplines): array
    {
        return [
            'Your portfolio' => [null, [
                'username' => ['type' => 'text', 'label' => 'Username', 'rules' => ['required'], 'help' => 'Your address: '.url('/').'/username. Lower-case letters, numbers and hyphens.'],
                'template' => ['type' => 'select', 'label' => 'Template', 'options' => array_map(fn ($t) => $t['name'], Templates::forType($type))],
            ]],
            'Profile' => [null, $this->profileFields()],
        ] + collect($this->groups($type, $disciplines))->mapWithKeys(fn ($g, $key) => [$g['label'] => [$key, $g['fields']]])->all();
    }

    /** @return array{0: array, 1: list<string>} form input shaped like the POST, and lines that couldn't be used */
    private function parseDetails(string $text, string $type, array $disciplines): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }
        $sections = collect($this->fileSections($type, $disciplines))->mapWithKeys(fn ($s, $title) => [mb_strtolower($title) => $s]);
        $data = $warnings = [];
        $section = $path = $type = null;
        $row = 0;

        foreach (preg_split('/\R/', $text) as $n => $line) {
            if (str_starts_with(ltrim($line), '#')) {
                continue;
            }
            if (preg_match('/^\s*==\s*(.+?)\s*==\s*$/', $line, $m)) {
                $section = $sections[mb_strtolower($m[1])] ?? null;
                if (! $section) {
                    $warnings[] = 'Unknown section "'.$m[1].'" on line '.($n + 1).' was skipped.';
                }
                $row = $section && $section[0] ? count($data[$section[0]] ?? []) : 0;
                $path = null;

                continue;
            }
            if (! $section) {
                continue;
            }
            if ($section[0] && trim($line) === '---') {
                $row++;
                $path = null;

                continue;
            }
            if (preg_match('/^\s*([^:]{1,80}):(.*)$/', $line, $m)) {
                $name = collect($section[1])->search(fn ($f, $k) => in_array(mb_strtolower(trim($m[1])), [mb_strtolower($f['label']), $k], true));
                if ($name !== false) {
                    $path = $section[0] ? "{$section[0]}.{$row}.{$name}" : $name;
                    $type = $section[1][$name]['type'];
                    Arr::set($data, $path, trim($m[2]));

                    continue;
                }
                // "Label: …" that isn't a field: part of long text, otherwise a typo worth reporting.
                if (! in_array($type, ['textarea', 'lines'], true)) {
                    $warnings[] = 'Line '.($n + 1).' ("'.mb_strimwidth(trim($line), 0, 60, '…').'") is not a field and was skipped.';
                    $path = null;

                    continue;
                }
            }
            if ($path) {
                Arr::set($data, $path, Arr::get($data, $path)."\n".rtrim($line));
            }
        }

        foreach ($sections as [$key, $fields]) {
            if (! $key) {
                $data = $this->cleanRow($data, $fields, '', $warnings);

                continue;
            }
            $rows = [];
            foreach ($data[$key] ?? [] as $r) {
                ($r = $this->cleanRow($r, $fields, $key, $warnings)) && $rows[] = $r; // blank entries dropped
            }
            if (count($rows) > self::MAX_ROWS) {
                $warnings[] = 'Only the first '.self::MAX_ROWS.' entries of '.$key.' were used.';
            }
            $data[$key] = array_slice($rows, 0, self::MAX_ROWS);
        }

        return [array_filter($data, fn ($v) => $v !== [] && $v !== ''), $warnings];
    }

    /** Trim values, drop blanks, and turn yes/no, option names and dates into what the form inputs expect. */
    private function cleanRow(array $row, array $fields, string $group, array &$warnings): array
    {
        foreach ($fields as $name => $field) {
            $value = trim((string) ($row[$name] ?? ''));
            unset($row[$name]);
            if ($value === '') {
                continue;
            }
            $where = $field['label'].($group !== '' ? " ({$group})" : '');
            if ($field['type'] === 'checkbox') {
                if (in_array(mb_strtolower($value), ['yes', 'y', 'true', '1', 'x'], true)) {
                    $row[$name] = '1';
                }
            } elseif ($field['type'] === 'select') {
                $key = collect($field['options'])->search(fn ($label, $k) => in_array(mb_strtolower($value), [mb_strtolower($label), mb_strtolower((string) $k)], true));
                if ($key !== false) {
                    $row[$name] = (string) $key;
                } else {
                    $warnings[] = "\"{$value}\" is not an option for {$where}.";
                }
            } elseif ($field['type'] === 'checkboxes') {
                $picked = collect(explode(',', $value))->map(fn ($v) => collect($field['options'])->search(fn ($label, $k) => in_array(mb_strtolower(trim($v)), [mb_strtolower($label), (string) $k], true)));
                $row[$name] = $picked->filter(fn ($k) => $k !== false)->values()->all();
            } elseif ($field['type'] === 'date') {
                if (in_array(mb_strtolower($value), ['present', 'current', 'now'], true) && isset($fields['is_current'])) {
                    $row['is_current'] = '1';
                } elseif ($date = $this->toDate($value)) {
                    $row[$name] = $date;
                } else {
                    $warnings[] = "\"{$value}\" is not a date for {$where} — use YYYY-MM-DD.";
                }
            } else {
                $row[$name] = $value;
            }
        }

        return $row;
    }

    /** 2021-03-15, 2021-03, 2021, "March 2021" → Y-m-d; null when unreadable. */
    private function toDate(string $value): ?string
    {
        return match (true) {
            (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) => strtotime($value) ? $value : null,
            (bool) preg_match('/^\d{4}-\d{2}$/', $value) => $value.'-01',
            (bool) preg_match('/^\d{4}$/', $value) => $value.'-01-01',
            default => ($t = strtotime('1 '.$value)) || ($t = strtotime($value)) ? date('Y-m-d', $t) : null,
        };
    }

    /** Profile fields shown on the form (labels from the admin profile form). */
    private function profileFields(): array
    {
        $fields = Arr::only(ProfileForm::fields(), self::PROFILE_FIELDS);
        $fields['headline']['rules'] = ['nullable', 'string', 'max:120'];
        $fields['email'] = ['label' => 'Email', 'help' => 'Shown on your portfolio, and how we contact you about this application.', 'rules' => ['required', 'email', 'max:150']] + $fields['email'];

        return $fields;
    }

    /**
     * key => [label, singular, resource, fields => [name => field definition]]. Projects get the type's labels
     * ("Campaigns", "Courses & programs" …) and its profession-specific fields.
     */
    private function groups(string $type, array $disciplines = []): array
    {
        $tags = ['type' => 'tags', 'help' => 'Comma separated.'];
        $modules = Portfolio::modulesFor($type, $disciplines);
        $work = Portfolio::labelsFor($modules)['work'];

        return collect(self::GROUPS)->map(function (array $g, string $key) use ($tags, $modules, $disciplines, $work) {
            [$label, $singular, $resource, $names] = $g;
            if ($key === 'projects') {
                // The common fields plus the type's own, in form order (no Mermaid diagram on a first form).
                $own = array_diff([...ProjectResource::MODULE_FIELDS, ...Project::detailKeys()], ['diagram']);
                $fields = Arr::only(ProjectResource::fieldsFor($modules, $disciplines), [...$names, ...$own]);

                return ['label' => $work[0], 'singular' => $work[3], 'resource' => $resource, 'fields' => $fields];
            }
            $fields = Arr::only($resource::fields(), $names);
            if ($key === 'experience' && ! in_array('technical', $modules, true)) {
                $fields['technologies']['label'] = 'Skills & tools used';
            }
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
            $text = array_keys(array_filter($group['fields'], fn ($f) => ! in_array($f['type'], ['select', 'checkbox', 'checkboxes'], true)));
            $rows = array_values(array_filter($rows, fn ($row) => ! is_array($row) || Arr::first($text, fn ($n) => filled($row[$n] ?? null)) !== null));
            // "Present" ticked: the end date is meaningless (and may be stale from before it was ticked).
            $out[$key] = array_map(fn ($row) => is_array($row) && filled($row['is_current'] ?? null) ? Arr::except($row, 'end_date') : $row, $rows);
        }

        return $out;
    }

    private function rules(array $groups, string $type): array
    {
        $image = config('portfolio.uploads.image_max_kb');
        $resume = config('portfolio.uploads.resume_max_kb');
        $rules = [
            'username' => Username::rules(),
            'template' => ['nullable', Rule::in(array_keys(Templates::forType($type)))],
            'avatar' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$image}", 'dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000'],
            'resume' => ['nullable', 'file', 'mimes:pdf', 'mimetypes:application/pdf', "max:{$resume}"],
            'password' => ['required', 'confirmed', Password::min(8)],
        ] + collect($this->profileFields())->map(fn ($f) => $f['rules'])->all();
        $rules['email'][] = Rule::unique('users', 'email');

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
                if ($field['type'] === 'checkboxes') {
                    $rules["{$key}.*.{$name}.*"] = ['string', Rule::in(array_keys($field['options']))];
                }
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
