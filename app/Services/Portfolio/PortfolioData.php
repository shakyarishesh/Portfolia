<?php

namespace App\Services\Portfolio;

use App\Models\Achievement;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\ExpertiseArea;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Section;
use App\Models\SkillCategory;
use App\Models\SocialLink;
use App\Models\Testimonial;

/** Everything the public site shows. Only published records; sections without content are dropped. */
class PortfolioData
{
    public function get(): array
    {
        $profile = Profile::query()->with(['avatar', 'resume'])->firstOrFail();

        $experiences = Experience::published()->ordered()->with('technologies')->get();
        $projects = Project::published()->ordered()->with(['technologies', 'thumbnail', 'experience'])->get();
        $highlights = Achievement::published()->ordered()->where('kind', 'highlight')
            ->with(['project' => fn ($q) => $q->published()])->get();
        $milestones = Achievement::published()->ordered()->where('kind', 'milestone')->get();
        $skillCategories = SkillCategory::published()->ordered()
            ->with(['skills' => fn ($q) => $q->published()->ordered()])->get()
            ->filter(fn ($c) => $c->skills->isNotEmpty())->values();
        $expertise = ExpertiseArea::published()->ordered()->get();
        $education = Education::published()->ordered()->get();
        $certifications = Certification::published()->ordered()->get();
        $links = SocialLink::published()->ordered()->get();
        $testimonials = Testimonial::published()->ordered()->get();

        $hasContent = [
            'home' => true,
            'experience' => $experiences->isNotEmpty() || $milestones->isNotEmpty(),
            'projects' => $projects->isNotEmpty() || $highlights->isNotEmpty(),
            'skills' => $skillCategories->isNotEmpty(),
            'education' => $education->isNotEmpty() || $certifications->isNotEmpty(),
            'contact' => true,
        ];
        $sections = Section::query()->ordered()->where('is_visible', true)->get()
            ->filter(fn (Section $s) => $hasContent[$s->key] ?? false)->values();
        if ($sections->isEmpty()) {
            $sections = collect([new Section(['key' => 'home', 'nav_label' => 'Home', 'title' => 'About me'])]);
        }

        return [
            'profile' => $profile,
            'sections' => $sections,
            'experiences' => $experiences,
            'currentJob' => $experiences->firstWhere('is_current', true),
            'projects' => $projects,
            'highlights' => $highlights,
            'milestones' => $milestones,
            'skillCategories' => $skillCategories,
            'expertise' => $expertise,
            'education' => $education,
            'certifications' => $certifications,
            'links' => $links,
            'testimonials' => $testimonials,
            // Lower-cased technology names used by published projects, so skills can link to them.
            'projectTech' => $projects->flatMap->technologies->pluck('name')->map(fn ($n) => mb_strtolower($n))->unique()->values(),
            'hasBlog' => Post::live()->exists(),
        ];
    }

    /** JSON shape for /api/portfolio. */
    public function toApi(): array
    {
        $d = $this->get();
        $p = $d['profile'];
        $tech = fn ($m) => $m->technologies->pluck('name')->all();

        return [
            'profile' => [
                'name' => $p->full_name, 'headline' => $p->headline, 'intro' => $p->intro, 'about' => $p->aboutParagraphs(),
                'location' => $p->location, 'email' => $p->email, 'phone' => $p->show_phone ? $p->phone : null,
                'avatar' => $p->avatarSrc(), 'resume' => $p->resume ? route('resume') : null, 'open_to_work' => $p->open_to_work,
            ],
            'sections' => $d['sections']->map->only(['key', 'nav_label', 'title'])->values(),
            'experience' => $d['experiences']->map(fn ($e) => [
                'company' => $e->company, 'company_url' => $e->company_url, 'role' => $e->role, 'type' => $e->employment_type,
                'location' => $e->location, 'start' => $e->start_date?->toDateString(), 'end' => $e->end_date?->toDateString(),
                'current' => $e->is_current, 'summary' => $e->summary, 'highlights' => $e->highlights ?? [], 'technologies' => $tech($e),
            ]),
            'projects' => $d['projects']->map(fn ($pr) => [
                'name' => $pr->name, 'slug' => $pr->slug, 'url' => route('projects.show', $pr), 'tagline' => $pr->tagline,
                'category' => $pr->category, 'period' => $pr->period(), 'company' => $pr->experience?->company, 'role' => $pr->role,
                'description' => $pr->description, 'problem' => $pr->problem, 'challenges' => $pr->challenges, 'features' => $pr->features ?? [], 'architecture' => $pr->architecture,
                'live_url' => $pr->live_url, 'repo_url' => $pr->repo_url, 'technologies' => $tech($pr), 'thumbnail' => $pr->thumbnail?->url(),
            ]),
            'highlights' => $d['highlights']->map->only(['title', 'label', 'summary', 'details', 'category']),
            'milestones' => $d['milestones']->map->only(['title', 'summary', 'date_label']),
            'skills' => $d['skillCategories']->map(fn ($c) => ['category' => $c->name, 'skills' => $c->skills->map(fn ($s) => ['name' => $s->name, 'level' => $s->levelLabel(), 'description' => $s->description])]),
            'expertise' => $d['expertise']->map->only(['title', 'description']),
            'education' => $d['education']->map->only(['institution', 'degree', 'field', 'location', 'start_year', 'end_year']),
            'certifications' => $d['certifications']->map(fn ($c) => ['name' => $c->name, 'issuer' => $c->issuer, 'issued_on' => $c->issued_on?->toDateString(), 'url' => $c->credential_url]),
            'links' => $d['links']->map->only(['platform', 'label', 'url']),
            'testimonials' => $d['testimonials']->map->only(['name', 'position', 'company', 'quote']),
        ];
    }
}
