<?php

namespace Tests\Feature\Templates;

use App\Models\Portfolio;
use App\Models\Post;
use App\Models\Project;
use App\Support\CurrentPortfolio;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MinimalEditorialTemplateTest extends TestCase
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
        $this->portfolio->update(['template' => 'minimal_editorial']);

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
        $this->portfolio->update(['template_settings' => ['minimal_editorial' => $settings]]);
    }

    public function test_home_renders_the_editorial_layout_with_seeded_content(): void
    {
        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('Rishesh Shakya')
            ->assertSee('Velox Labs')
            ->assertSee('Hajir')
            ->assertSee('Patan Multiple Campus')
            ->assertSee('Designing payroll engines')
            ->assertSee('class="me theme-dark accent-teal page-home"', false)
            ->assertSee('templates/minimal_editorial/style.css?v=', false)
            ->assertSee('class="me-skip" href="#content"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('"@type":"Person"', false)
            ->assertSee('aria-label="Sections"', false)
            ->assertSee('href="#experience"', false)
            ->assertSee('href="#writing"', false)
            ->assertDontSee('Secret draft post')
            ->assertDontSee('Secret Draft Project')
            ->assertDontSee('cursor-ring')
            ->assertDontSee('assets/style.css')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'exactly one h1');
        $this->assertStringNotContainsString('fonts.googleapis', $html);
        $this->assertStringNotContainsString('cdnjs', $html);
    }

    public function test_contact_section_has_form_with_honeypot_and_shows_feedback(): void
    {
        $this->get('/rishesh')->assertOk()
            ->assertSee('id="contact"', false)
            ->assertSee('action="'.url('/rishesh/contact'), false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="website" tabindex="-1" autocomplete="off"', false)
            ->assertSee('for="c-message"', false);

        $this->post('/rishesh/contact', ['name' => '', 'email' => 'nope', 'message' => 'short'])->assertRedirect(url('/rishesh').'#contact');
        $this->get('/rishesh')->assertSee('aria-invalid="true"', false)->assertSee('role="alert"', false);

        $this->post('/rishesh/contact', ['name' => 'Ann', 'email' => 'ann@example.test', 'message' => 'Hello there, nice portfolio!']);
        $this->get('/rishesh')->assertSee('your message is on its way');
    }

    public function test_settings_change_theme_accent_and_thumbnails(): void
    {
        CurrentPortfolio::set($this->portfolio);
        Project::where('slug', 'hajir')->firstOrFail()->media()->create([
            'collection' => 'thumbnail', 'disk' => 'public', 'path' => 'projects/hajir.png',
            'original_name' => 'hajir.png', 'mime_type' => 'image/png', 'size' => 10,
        ]);
        CurrentPortfolio::set(null);

        $this->get('/rishesh')->assertSee('class="me-thumb"', false)->assertSee('loading="lazy"', false);

        $this->useSettings(['accent' => 'rose', 'theme' => 'light', 'show_thumbnails' => false]);
        $this->get('/rishesh')->assertOk()
            ->assertSee('class="me theme-light accent-rose page-home"', false)
            ->assertSee('<meta name="color-scheme" content="light">', false)
            ->assertDontSee('class="me-thumb"', false);
    }

    public function test_project_page(): void
    {
        $this->get('/rishesh/projects/hajir')->assertOk()
            ->assertSee('class="me theme-dark accent-teal page-project"', false)
            ->assertSee('<h1 class="me-article-title">Hajir</h1>', false)
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
            ->assertSee('BlogPosting', false)
            ->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

        $this->get('/rishesh/blog/secret')->assertNotFound();
    }

    public function test_empty_portfolio_renders_every_page(): void
    {
        $bare = $this->portfolio(['name' => 'Bare', 'username' => 'bare-one'], ['headline' => '', 'intro' => null, 'email' => null]);
        $bare->update(['template' => 'minimal_editorial']);
        CurrentPortfolio::set(null);

        $this->get('/bare-one')->assertOk()
            ->assertSee('page-home', false)
            ->assertSee('Test Person')
            ->assertSee('id="contact"', false)
            ->assertDontSee('href="#writing"', false);
        $this->get('/bare-one/blog')->assertOk()->assertSee('Nothing published yet');
    }
}
