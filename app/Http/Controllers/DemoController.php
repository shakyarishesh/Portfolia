<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Section;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\Technology;
use App\Support\CurrentPortfolio;
use App\Support\Templates;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Try-it demo: a visitor's few details rendered with the real templates. The data lives only in
 * the session (key "demo") and is turned into unsaved, in-memory models per request — nothing is
 * written to the database and no page views are recorded.
 */
class DemoController extends Controller
{
    public const MAX_SKILLS = 15;

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:80'],
            'headline' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:600'],
            'location' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:150'],
            'skills' => ['nullable', 'string', 'max:400'],
            'projects' => ['nullable', 'array', 'max:2'],
            'projects.*.name' => ['nullable', 'string', 'max:80', 'required_with:projects.*.description,projects.*.technologies,projects.*.url'],
            'projects.*.description' => ['nullable', 'string', 'max:300'],
            'projects.*.technologies' => ['nullable', 'string', 'max:200'],
            'projects.*.url' => ['nullable', 'url:http,https', 'max:255'],
            'template' => ['required', Rule::in(array_keys(Templates::available()))],
        ]);
        // The form sits at the bottom of the landing page, so send errors back to it rather than to the top.
        if ($validator->fails()) {
            return redirect(route('root').'#demo')->withErrors($validator)->withInput();
        }
        $data = $validator->validated();
        $data['projects'] = array_values(array_filter($data['projects'] ?? [], fn ($p) => filled($p['name'] ?? null)));

        $request->session()->put('demo', $data);

        return redirect()->route('demo.show');
    }

    public function show(Request $request)
    {
        $data = $request->session()->get('demo');
        if (! $data) {
            return redirect(route('root').'#demo');
        }
        if (Templates::exists((string) $request->query('template'))) {
            $data['template'] = $request->query('template');
            $request->session()->put('demo.template', $data['template']);
        }

        return $this->render('home', $this->build($data) + ['assistant' => false]);
    }

    public function project(Request $request, string $slug)
    {
        $data = $request->session()->get('demo');
        if (! $data) {
            return redirect(route('root').'#demo');
        }
        $home = $this->build($data);
        $project = $home['projects']->firstWhere('slug', $slug) ?? abort(404);

        return $this->render('project', [
            'project' => $project,
            'profile' => $home['profile'],
            'highlights' => new Collection,
            'related' => $home['projects']->reject(fn ($p) => $p === $project)->values(),
        ]);
    }

    public function destroy(Request $request)
    {
        $request->session()->forget('demo');

        return redirect(route('root').'#demo');
    }

    /**
     * Like PortfolioController::render, minus the admin bits: always "previewing" (noindex, demo banner) but
     * preview=false, which in the project/post views means "admin previewing a draft" (editor links need ids).
     */
    private function render(string $page, array $data)
    {
        $portfolio = CurrentPortfolio::get();

        return response()->view(Templates::view($portfolio->template, $page), $data + [
            'portfolio' => $portfolio,
            'template' => $portfolio->template,
            'settings' => $portfolio->settingsFor(),
            'previewing' => true,
            'preview' => false,
            'demo' => true,
            // content_journal's layout would otherwise look these up in the database.
            'navBlog' => false,
            'navNotes' => false,
        ])->withHeaders(['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'private, no-store']);
    }

    /** Session data → the same shape PortfolioData::get() gives every template, built from unsaved models. */
    private function build(array $d): array
    {
        $list = fn (?string $csv, int $max) => collect(explode(',', (string) $csv))->map(fn ($s) => trim($s))->filter()->unique()->take($max)->values();

        // Unsaved portfolio as "current" → route('home') etc. resolve to /demo/… (served by this controller).
        $portfolio = new Portfolio(['name' => $d['name'], 'username' => 'demo', 'template' => $d['template'], 'status' => 'published']);
        CurrentPortfolio::set($portfolio);

        $profile = (new Profile([
            'full_name' => $d['name'], 'headline' => $d['headline'] ?? '', 'intro' => $d['bio'] ?? null, 'about' => $d['bio'] ?? null,
            'location' => $d['location'] ?? null, 'email' => $d['email'] ?? null, 'open_to_work' => false, 'contact_form_enabled' => false,
        ]))->setRelation('avatar', null)->setRelation('resume', null);

        $slugs = [];
        $projects = new Collection(array_map(function (array $p, int $i) use ($list, &$slugs) {
            $slug = $base = Str::slug($p['name']) ?: 'project';
            for ($n = 2; in_array($slug, $slugs, true); $n++) {
                $slug = "{$base}-{$n}";
            }
            $slugs[] = $slug;

            return (new Project([
                'name' => $p['name'], 'slug' => $slug, 'description' => $p['description'] ?? null, 'live_url' => $p['url'] ?? null,
                'status' => 'published', 'is_featured' => true, 'sort_order' => $i,
            ]))->setRelation('technologies', new Collection($list($p['technologies'] ?? null, 10)->map(fn ($n) => new Technology(['name' => $n]))->all()))
                ->setRelation('thumbnail', null)->setRelation('experience', null)->setRelation('screenshots', new Collection);
        }, $d['projects'], array_keys($d['projects'])));

        $skills = $list($d['skills'] ?? null, self::MAX_SKILLS)->map(fn ($n) => new Skill(['name' => $n, 'status' => 'published']));
        $skillCategories = new Collection($skills->isEmpty() ? [] : [
            (new SkillCategory(['name' => 'Skills', 'status' => 'published']))->setRelation('skills', new Collection($skills->all())),
        ]);

        $keys = array_keys(array_filter(['home' => true, 'projects' => $projects->isNotEmpty(), 'skills' => $skills->isNotEmpty(), 'contact' => true]));
        $sections = new Collection(array_map(function (string $key, int $i) {
            [$label, $eyebrow, $title] = Portfolio::DEFAULT_SECTIONS[$key];

            return new Section(['key' => $key, 'nav_label' => $label, 'eyebrow' => $eyebrow, 'title' => $title, 'sort_order' => $i, 'is_visible' => true]);
        }, $keys, array_keys($keys)));

        $none = new Collection;

        return [
            'profile' => $profile,
            'sections' => $sections,
            'experiences' => $none,
            'currentJob' => null,
            'projects' => $projects,
            'highlights' => $none,
            'milestones' => $none,
            'skillCategories' => $skillCategories,
            'expertise' => $none,
            'education' => $none,
            'certifications' => $none,
            'links' => $none,
            'testimonials' => $none,
            'projectTech' => $projects->flatMap->technologies->pluck('name')->map(fn ($n) => mb_strtolower($n))->unique()->values(),
            'hasBlog' => false,
            'hasNotes' => false,
            'posts' => $none,
            'notes' => $none,
            'enabledSections' => $keys,
        ];
    }
}
