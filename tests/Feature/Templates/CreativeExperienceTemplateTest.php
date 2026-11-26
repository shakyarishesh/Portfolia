<?php

namespace Tests\Feature\Templates;

use App\Models\Portfolio;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CreativeExperienceTemplateTest extends TestCase
{
    use RefreshDatabase;

    private const ASSETS = 'templates/creative_experience/';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->seed(PortfolioSeeder::class);
        $this->use(['palette' => 'ocean', 'hero' => 'particles', 'motion' => true]);
    }

    private function use(array $settings): void
    {
        Portfolio::where('username', 'rishesh')->update([
            'template' => 'creative_experience',
            'template_settings' => json_encode(['creative_experience' => $settings]),
        ]);
    }

    private function article(array $attributes = []): Post
    {
        return Post::create($attributes + [
            'title' => 'Designing payroll engines', 'slug' => 'payroll-engines', 'status' => 'published', 'published_at' => now()->subDay(),
            'excerpt' => 'How I structure salary calculations.', 'tags' => ['laravel'], 'body' => "## Why\n\nBecause.\n\n## How\n\nLike this.",
        ]);
    }

    public function test_home_is_complete_server_html_with_canvas_as_decoration(): void
    {
        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('data-template="creative_experience"', false)
            ->assertSee('<h1 class="name" id="hero-name">', false)
            ->assertSee('Rishesh')->assertSee('Velox Labs')->assertSee('Hajir')->assertSee('Patan Multiple Campus')->assertSee('Laravel')
            ->assertSee('application/ld+json', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('class="skip" href="#main"', false)
            ->assertSee('<nav class="site-nav" aria-label="Primary">', false)
            ->assertSee('href="#projects"', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        foreach (['home', 'experience', 'projects', 'skills', 'education', 'contact'] as $id) {
            $this->assertStringContainsString("id=\"{$id}\"", $html);
        }
        // Decorative canvas, static art always present as the fallback.
        $this->assertMatchesRegularExpression('/<canvas class="hero-canvas" aria-hidden="true" data-mode="particles"/', $html);
        $this->assertStringContainsString('<div class="hero-art" aria-hidden="true">', $html);
        // Settings reach CSS/JS through data-attributes.
        $this->assertStringContainsString('data-palette="ocean" data-hero="particles" data-motion="on"', $html);
        // Assets: own, cache-busted, deferred; nothing external; none of the classic markers.
        $this->assertMatchesRegularExpression('#templates/creative_experience/script\.js\?v=\d+" defer#', $html);
        $this->assertMatchesRegularExpression('#templates/creative_experience/style\.css\?v=\d+#', $html);
        $this->assertDoesNotMatchRegularExpression('#<(script|link)[^>]+(src|href)="https?://(?!localhost)#', $html);
        foreach (['cursor-glow', 'data-tab=', 'assets/main.js', 'intro-screen', 'class="tabs"'] as $classic) {
            $this->assertStringNotContainsString($classic, $html);
        }
    }

    public function test_project_selector_is_accessible_and_has_a_no_js_fallback(): void
    {
        $html = $this->get('/rishesh')->assertOk()->getContent();

        $this->assertStringContainsString('role="tablist" aria-label="Choose a project" data-selector hidden', $html);
        $this->assertStringContainsString('<button type="button" role="tab" id="tab-hajir" aria-controls="project-hajir" aria-selected="true" tabindex="0">', $html);
        $this->assertMatchesRegularExpression('/role="tab" id="tab-[a-z0-9-]+" aria-controls="project-[a-z0-9-]+" aria-selected="false" tabindex="-1"/', $html);
        // Every panel is server-rendered and visible without JS, with case-study details and a link to the full study.
        $this->assertStringContainsString('<article class="panel" id="project-hajir"', $html);
        $this->assertStringNotContainsString('id="project-hajir" hidden', $html);
        $this->assertStringContainsString('<dt>Role</dt>', $html);
        $this->assertStringContainsString('<dt>Stack</dt>', $html);
        $this->assertStringContainsString(route('projects.show', ['project' => 'hajir']), $html);
        $this->assertMatchesRegularExpression('#<noscript>\s*<nav class="proj-index" aria-label="Project index">#', $html);
        $this->assertStringContainsString('href="#project-hajir"', $html);
    }

    public function test_settings_change_hero_palette_and_motion(): void
    {
        $this->use(['palette' => 'mono', 'hero' => 'none', 'motion' => true]);
        $this->get('/rishesh')->assertOk()->assertSee('data-palette="mono" data-hero="none"', false)
            ->assertDontSee('class="hero-canvas"', false)->assertSee('class="hero-art"', false);

        $this->use(['palette' => 'sunset', 'hero' => 'orbits', 'motion' => true]);
        $this->get('/rishesh')->assertOk()->assertSee('data-hero="orbits"', false)
            ->assertSee('data-mode="orbits" data-skills="[&quot;', false)
            ->assertSee('<ul class="orbit-static"', false);

        $this->use(['palette' => 'sunset', 'hero' => 'particles', 'motion' => false]);
        $this->get('/rishesh')->assertOk()->assertSee('data-motion="off"', false)->assertDontSee('class="hero-canvas"', false);

        // Unknown values fall back to defaults (allow-listed by the registry).
        $this->use(['palette' => '"><script>', 'hero' => 'webgl']);
        $this->get('/rishesh')->assertOk()->assertSee('data-palette="sunset" data-hero="particles" data-motion="on"', false);
    }

    public function test_contact_form_has_honeypot_and_labels(): void
    {
        Profile::query()->update(['contact_form_enabled' => true]);
        $this->get('/rishesh')->assertOk()
            ->assertSee('action="'.route('contact.store').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="website" tabindex="-1" autocomplete="off"', false)
            ->assertSee('<label for="c-email">Email</label>', false)
            ->assertSee('<label for="c-message">Message</label>', false);

        $this->post(route('contact.store'), ['name' => 'Ann', 'email' => 'ann@example.test', 'message' => 'Hello there, nice work!'])
            ->assertRedirect(route('home').'#contact');
        $this->get('/rishesh')->assertSee('your message was sent');
    }

    public function test_project_case_study_page(): void
    {
        $this->get('/rishesh/projects/hajir')->assertOk()
            ->assertSee('<h1 class="case-title">Hajir</h1>', false)
            ->assertSee('What I built')
            ->assertSee('aria-label="Case study sections"', false)
            ->assertSee('data-template="creative_experience"', false)
            ->assertDontSee('cdn.jsdelivr.net');

        Project::where('slug', 'dialinger')->update(['status' => 'draft']);
        $this->get('/rishesh/projects/dialinger')->assertNotFound();
        $this->get('/rishesh')->assertDontSee('id="project-dialinger"', false);
    }

    public function test_blog_list_and_post_pages(): void
    {
        $this->article();
        $this->article(['title' => 'Secret draft', 'slug' => 'secret', 'status' => 'draft']);

        $this->get('/rishesh')->assertOk()->assertSee('id="writing"', false)->assertSee('Designing payroll engines')->assertDontSee('Secret draft');
        $this->get('/rishesh/blog')->assertOk()
            ->assertSee('Designing payroll engines')->assertDontSee('Secret draft')
            ->assertSee('role="search"', false)->assertSee('name="q"', false)
            ->assertSee('href="'.route('blog.index', ['tag' => 'laravel']).'"', false);
        $this->get('/rishesh/blog?q=nomatch')->assertOk()->assertSee('Nothing matches');
        $this->get('/rishesh/blog/payroll-engines')->assertOk()
            ->assertSee('<h1 class="case-title">Designing payroll engines</h1>', false)
            ->assertSee('aria-label="On this page"', false)
            ->assertSee('id="why"', false);
        $this->get('/rishesh/blog/secret')->assertNotFound();
    }

    public function test_empty_portfolio_renders(): void
    {
        $this->portfolio(['username' => 'fresh', 'template' => 'creative_experience', 'template_settings' => ['creative_experience' => ['hero' => 'orbits']]], ['headline' => '', 'intro' => null]);

        $html = $this->get('/fresh')->assertOk()->assertSee('Test Person')->getContent();
        // No skills to orbit → particles instead of an empty orbit.
        $this->assertStringContainsString('data-hero="particles"', $html);
        $this->assertStringNotContainsString('orbit-static', $html);
        $this->assertStringNotContainsString('role="tablist"', $html);
    }

    public function test_assets_exist_and_stay_within_budget(): void
    {
        foreach (['style.css' => 25_000, 'script.js' => 15_000, 'preview.svg' => 20_000] as $file => $budget) {
            $path = public_path(self::ASSETS.$file);
            $this->assertFileExists($path);
            $this->assertLessThan($budget, filesize($path), "{$file} is over budget");
        }
        $this->assertStringNotContainsString('http', preg_replace('#xmlns="http://www.w3.org/2000/svg"#', '', file_get_contents(public_path(self::ASSETS.'preview.svg'))));
        $js = file_get_contents(public_path(self::ASSETS.'script.js'));
        $this->assertStringContainsString('prefers-reduced-motion', $js);
        $this->assertStringContainsString('visibilitychange', $js);
        $this->assertStringContainsString('IntersectionObserver', $js);
    }
}
