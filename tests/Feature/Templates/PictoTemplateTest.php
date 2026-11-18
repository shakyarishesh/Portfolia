<?php

namespace Tests\Feature\Templates;

use App\Models\Portfolio;
use App\Models\Post;
use App\Models\Project;
use App\Models\Skill;
use App\Support\CurrentPortfolio;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PictoTemplateTest extends TestCase
{
    use RefreshDatabase;

    private Portfolio $portfolio;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(PortfolioSeeder::class);
        $this->portfolio = Portfolio::where('username', 'rishesh')->firstOrFail();
        $this->portfolio->update(['template' => 'picto']);

        CurrentPortfolio::set($this->portfolio);
        Post::create([
            'title' => 'Designing payroll engines', 'slug' => 'payroll-engines', 'status' => 'published', 'published_at' => now()->subDay(),
            'excerpt' => 'How I structure salary calculations.', 'tags' => ['laravel'], 'category' => 'Backend',
            'body' => "## Why\n\nBecause.\n\n## How\n\nLike this.\n\n<script>alert(1)</script>",
        ]);
        Post::create(['title' => 'Secret draft post', 'slug' => 'secret', 'status' => 'draft', 'body' => 'x']);
        Project::create(['name' => 'Secret Draft Project', 'slug' => 'secret-project', 'status' => 'draft']);
        CurrentPortfolio::set(null);
    }

    private function useSettings(array $settings): void
    {
        $this->portfolio->update(['template_settings' => ['picto' => $settings]]);
    }

    public function test_home_renders_the_picto_layout_with_seeded_content(): void
    {
        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('Rishesh Shakya')
            ->assertSee('Velox Labs')
            ->assertSee('Hajir')
            ->assertSee('Patan Multiple Campus')
            ->assertSee('Designing payroll engines')
            ->assertSee('<html lang="en" data-template="picto">', false)
            ->assertSee('class="pc palette-candy page-home" data-palette="candy" data-deco="on"', false)
            ->assertSee('templates/picto/style.css?v=', false)
            ->assertSee('templates/picto/script.js?v=', false)
            ->assertSee('class="pc-skip" href="#content"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('"@type":"Person"', false)
            ->assertSee('<nav class="pc-nav" aria-label="Sections">', false)
            ->assertSee('href="#experience"', false)->assertSee('data-nav="experience"', false)
            ->assertSee('href="#writing"', false)->assertSee('data-nav="writing"', false)
            ->assertSee('<span class="pc-nav-label">Projects</span>', false)
            ->assertSee('<span class="pc-marker c', false)
            ->assertSee('class="pc-timeline"', false)
            ->assertDontSee('Secret draft post')
            ->assertDontSee('Secret Draft Project')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'exactly one h1');
        // No interests data exists, so no invented section; no external font/CDN hosts.
        $this->assertStringNotContainsString('id="interests"', $html);
        foreach (['fonts.googleapis', 'fonts.gstatic', 'cdnjs', 'jsdelivr', 'unpkg'] as $host) {
            $this->assertStringNotContainsString($host, $html);
        }
        // Other templates' markers are absent.
        foreach (['data-template="creative_experience"', 'data-template="content_journal"', 'class="me ', 'assets/style.css', 'cursor-ring'] as $other) {
            $this->assertStringNotContainsString($other, $html);
        }
    }

    public function test_settings_change_palette_and_decorations(): void
    {
        $this->get('/rishesh')->assertSee('class="pc-floaties" aria-hidden="true"', false);

        $this->useSettings(['palette' => 'tropical', 'decorations' => false]);
        $this->get('/rishesh')->assertOk()
            ->assertSee('class="pc palette-tropical page-home" data-palette="tropical" data-deco="off"', false)
            ->assertDontSee('pc-floaties', false);

        $this->useSettings(['palette' => 'sunrise']);
        $this->get('/rishesh')->assertSee('palette-sunrise', false)->assertSee('pc-floaties', false);

        $this->useSettings(['palette' => '<script>']);
        $this->get('/rishesh')->assertOk()->assertSee('palette-candy', false)->assertDontSee('palette-<', false);
    }

    public function test_skill_tiles_are_grouped_by_category_with_text_labels(): void
    {
        $html = $this->get('/rishesh')->assertOk()->getContent();

        foreach (['Backend', 'Databases', 'Integrations', 'Frontend', 'Tools'] as $category) {
            $this->assertMatchesRegularExpression('/<h3><span class="pc-skillset-ico c\d" aria-hidden="true">\s*\S+\s*<\/span> '.$category.'<\/h3>/u', $html);
        }
        $this->assertSame(5, substr_count($html, 'class="pc-skillset pc-card'));
        $this->assertSame(Skill::withoutGlobalScopes()->count(), substr_count($html, 'class="pc-tile"'));
        $this->assertStringContainsString('<span class="pc-tile-label">Laravel</span>', $html);
        $this->assertStringContainsString('<span class="pc-tile-level">Strong</span>', $html);
        // Emoji are decoration: every tile icon is hidden from assistive tech.
        $this->assertSame(substr_count($html, 'class="pc-tile-ico'), substr_count($html, 'class="pc-tile-ico c1" aria-hidden="true"')
            + substr_count($html, 'class="pc-tile-ico c2" aria-hidden="true"') + substr_count($html, 'class="pc-tile-ico c3" aria-hidden="true"')
            + substr_count($html, 'class="pc-tile-ico c4" aria-hidden="true"'));
    }

    public function test_about_keeps_first_paragraph_visible_and_expands_accessibly(): void
    {
        $html = $this->get('/rishesh')->assertOk()->getContent();

        $bio = substr($html, strpos($html, 'class="pc-card pc-bio"'));
        $details = strpos($bio, '<details class="pc-more">');
        $this->assertNotFalse($details);
        $this->assertLessThan($details, strpos($bio, 'based in Lalitpur'), 'first paragraph sits outside the expansion');
        $this->assertGreaterThan($details, strpos($bio, 'almost right'), 'later paragraphs live inside it');
        $this->assertStringContainsString('<summary><span aria-hidden="true">🍪</span> A little more about me</summary>', $bio);
    }

    public function test_projects_without_a_thumbnail_get_a_fallback_tile(): void
    {
        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('class="pc-shot pc-shot-fallback', false)
            ->assertDontSee('class="pc-shot" src', false)
            ->getContent();
        $this->assertSame(Project::withoutGlobalScopes()->where('status', 'published')->count(), substr_count($html, 'pc-shot-fallback'));

        CurrentPortfolio::set($this->portfolio);
        Project::where('slug', 'hajir')->firstOrFail()->media()->create([
            'collection' => 'thumbnail', 'disk' => 'public', 'path' => 'projects/hajir.png',
            'original_name' => 'hajir.png', 'mime_type' => 'image/png', 'size' => 10,
        ]);
        CurrentPortfolio::set(null);

        $this->get('/rishesh')
            ->assertSee('class="pc-shot" src=', false)
            ->assertSee('loading="lazy" width="640" height="400"', false);
    }

    public function test_contact_section_has_form_with_honeypot_and_shows_feedback(): void
    {
        $this->get('/rishesh')->assertOk()
            ->assertSee('id="contact"', false)
            ->assertSee('action="'.url('/rishesh/contact'), false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="website" tabindex="-1" autocomplete="off"', false)
            ->assertSee('for="c-message"', false)
            ->assertSee('href="https://github.com/shakyarishesh"', false);

        $this->post('/rishesh/contact', ['name' => '', 'email' => 'nope', 'message' => 'short'])->assertRedirect(url('/rishesh').'#contact');
        $this->get('/rishesh')->assertSee('aria-invalid="true"', false)->assertSee('role="alert"', false);

        $this->post('/rishesh/contact', ['name' => 'Ann', 'email' => 'ann@example.test', 'message' => 'Hello there, nice portfolio!']);
        $this->get('/rishesh')->assertSee('your message is on its way');
    }

    public function test_project_page(): void
    {
        $this->get('/rishesh/projects/hajir')->assertOk()
            ->assertSee('data-template="picto"', false)
            ->assertSee('page-project', false)
            ->assertSee('<h1>Hajir</h1>', false)
            ->assertSee('What I built')
            ->assertSee('<link rel="canonical" href="'.url('/rishesh/projects/hajir').'"', false)
            ->assertDontSee('Preview — status');

        $this->get('/rishesh/projects/secret-project')->assertNotFound();
    }

    public function test_blog_list_and_post(): void
    {
        $this->get('/rishesh/blog')->assertOk()
            ->assertSee('page-posts', false)
            ->assertSee('role="search"', false)
            ->assertSee('name="q"', false)
            ->assertSee('#laravel')
            ->assertSee('Designing payroll engines')
            ->assertDontSee('Secret draft post');

        $this->get('/rishesh/blog?q=nothing-matches-this')->assertOk()->assertSee('Nothing matches');

        $html = $this->get('/rishesh/blog/payroll-engines')->assertOk()
            ->assertSee('page-post', false)
            ->assertSee('<h2 id="why">Why</h2>', false)
            ->assertSee('href="#how"', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertSame(1, substr_count($html, '<h1'));

        $this->get('/rishesh/blog/secret')->assertNotFound();
    }

    public function test_empty_sections_are_omitted(): void
    {
        CurrentPortfolio::set($this->portfolio);
        \App\Models\Education::query()->delete();
        \App\Models\Certification::query()->delete();
        \App\Models\SkillCategory::query()->update(['status' => 'draft']);
        CurrentPortfolio::set(null);

        $this->get('/rishesh')->assertOk()
            ->assertDontSee('id="education"', false)->assertDontSee('href="#education"', false)
            ->assertDontSee('id="skills"', false)->assertDontSee('pc-skillset', false)
            ->assertSee('id="projects"', false);
    }

    public function test_empty_portfolio_renders_every_page(): void
    {
        $bare = $this->portfolio(['name' => 'Bare', 'username' => 'bare-one'], ['headline' => '', 'intro' => null, 'email' => null]);
        $bare->update(['template' => 'picto']);
        CurrentPortfolio::set(null);

        $html = $this->get('/bare-one')->assertOk()
            ->assertSee('data-template="picto"', false)
            ->assertSee('Test Person')
            ->assertSee('id="contact"', false)
            ->assertDontSee('id="home"', false)
            ->assertDontSee('href="#writing"', false)
            ->assertDontSee('pc-headline', false)
            ->assertDontSee('<img', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->get('/bare-one/blog')->assertOk()->assertSee('Nothing published yet');
    }

    public function test_switching_template_keeps_content(): void
    {
        CurrentPortfolio::set($this->portfolio);
        $records = fn () => [Project::count(), Skill::count(), Post::count(), \App\Models\Experience::count()];
        $before = $records();
        CurrentPortfolio::set(null);
        $cards = fn () => [substr_count($h = $this->get('/rishesh')->getContent(), 'class="pc-project pc-card'), substr_count($h, 'class="pc-tile"'), substr_count($h, 'class="pc-step')];
        $rendered = $cards();

        $this->portfolio->update(['template' => 'minimal_editorial']);
        $this->get('/rishesh')->assertOk()->assertSee('Hajir');
        $this->portfolio->update(['template' => 'picto']);

        $this->assertSame($rendered, $cards());
        $this->assertSame([3, 25, 2], $rendered); // published projects, skills, jobs
        CurrentPortfolio::set($this->portfolio);
        $this->assertSame($before, $records());
    }
}
