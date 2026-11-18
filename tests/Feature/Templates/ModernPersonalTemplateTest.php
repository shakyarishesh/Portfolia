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

class ModernPersonalTemplateTest extends TestCase
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
        $this->portfolio->update(['template' => 'modern_personal']);

        CurrentPortfolio::set($this->portfolio);
        Post::create([
            'title' => 'Designing payroll engines', 'slug' => 'payroll-engines', 'status' => 'published', 'published_at' => now()->subDay(),
            'excerpt' => 'How I structure salary calculations.', 'tags' => ['laravel'], 'category' => 'Backend',
            'body' => "## Why\n\nBecause.\n\n## How\n\nLike this.\n\n<script>alert(1)</script>",
        ]);
        Post::create(['title' => 'Secret draft post', 'slug' => 'secret', 'status' => 'draft', 'body' => 'x']);
        Project::create(['name' => 'Secret Draft Project', 'slug' => 'secret-project', 'status' => 'draft']);
        // 3 seeded + 4 extra published projects = 7, so every featured_count option leaves a "More projects" list.
        foreach (range(1, 4) as $i) {
            Project::create(['name' => "Side Project {$i}", 'slug' => "side-{$i}", 'status' => 'published', 'sort_order' => 10 + $i]);
        }
        CurrentPortfolio::set(null);
    }

    private function useSettings(array $settings): void
    {
        $this->portfolio->update(['template_settings' => ['modern_personal' => $settings]]);
    }

    public function test_home_renders_the_modern_personal_layout_with_seeded_content(): void
    {
        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('data-template="modern_personal"', false)
            ->assertSee('class="mp accent-ink mp-serif"', false)
            ->assertSee('templates/modern_personal/style.css?v=', false)
            ->assertSee('class="mp-skip" href="#content"', false)
            ->assertSee('<main id="content"', false)
            ->assertSee('<h1 id="intro-h" class="mp-name">Rishesh Shakya</h1>', false)
            ->assertSee('Lalitpur, Nepal')
            ->assertSee('Velox Labs')
            ->assertSee('Hajir')
            ->assertSee('Patan Multiple Campus')
            ->assertSee('id="about"', false)
            ->assertSee('id="experience"', false)
            ->assertSee('id="skills"', false)
            ->assertSee('id="education"', false)
            ->assertSee('id="writing"', false)
            ->assertSee('Designing payroll engines')
            ->assertSee('href="https://github.com/shakyarishesh"', false)
            ->assertSee('<link rel="canonical" href="'.url('/rishesh').'"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('"@type":"Person"', false)
            ->assertDontSee('Secret draft post')
            ->assertDontSee('Secret Draft Project')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'exactly one h1');
        // Writing sits right before Contact; featured projects lead Selected work.
        $this->assertLessThan(strpos($html, 'id="contact"'), strpos($html, 'id="writing"'));
        $this->assertLessThan(strpos($html, 'Side Project 1'), strpos($html, '>Dialinger<'));
        // Self-contained: no external hosts, no script, and no other template's markers.
        $this->assertDoesNotMatchRegularExpression('#(src|href)="https?://(?!localhost)[^"]*\.(css|js)#', $html);
        foreach (['fonts.googleapis', 'cdnjs', 'unpkg', 'jsdelivr', '<script src', 'data-template="minimal_editorial"', 'data-template="content_journal"',
            'data-template="creative_experience"', 'class="me ', 'cursor-ring', 'assets/style.css'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html, 'no emoji');
    }

    public function test_css_is_restrained_and_local(): void
    {
        $css = file_get_contents(public_path('templates/modern_personal/style.css'));
        $this->assertStringNotContainsString('gradient(', $css);
        $this->assertStringNotContainsString('backdrop-filter', $css);
        $this->assertStringNotContainsString('@import', $css);
        $this->assertDoesNotMatchRegularExpression('#url\(\s*["\']?https?:#', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        $this->assertStringContainsString(':focus-visible', $css);
        foreach (['ink', 'olive', 'terracotta'] as $accent) {
            $this->assertStringContainsString(".accent-{$accent}", $css);
        }
        $this->assertFileExists(public_path('templates/modern_personal/preview.svg'));
    }

    public function test_settings_change_accent_serif_and_featured_count(): void
    {
        // Default: 4 selected rows, the other 3 under "More projects".
        $html = $this->get('/rishesh')->assertSee('More projects')->getContent();
        $this->assertSame(4, substr_count($html, 'class="mp-work-row"'));

        $this->useSettings(['accent' => 'terracotta', 'serif_headings' => false, 'featured_count' => '6']);
        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('class="mp accent-terracotta"', false)
            ->assertDontSee('mp-serif')
            ->getContent();
        $this->assertSame(6, substr_count($html, 'class="mp-work-row"'));

        $this->useSettings(['accent' => 'olive', 'featured_count' => '3']);
        $html = $this->get('/rishesh')->assertSee('class="mp accent-olive mp-serif"', false)->getContent();
        $this->assertSame(3, substr_count($html, 'class="mp-work-row"'));

        // No leftovers -> no "More projects" list.
        CurrentPortfolio::set($this->portfolio);
        Project::where('slug', 'like', 'side-%')->delete();
        CurrentPortfolio::set(null);
        $this->get('/rishesh')->assertDontSee('More projects');
    }

    public function test_thumbnails_only_when_every_selected_project_has_one(): void
    {
        $this->useSettings(['featured_count' => '3']);
        CurrentPortfolio::set($this->portfolio);
        $thumb = fn (Project $p) => $p->media()->create([
            'collection' => 'thumbnail', 'disk' => 'public', 'path' => "projects/{$p->slug}.png",
            'original_name' => 'x.png', 'mime_type' => 'image/png', 'size' => 10,
        ]);
        $thumb(Project::where('slug', 'hajir')->firstOrFail());
        CurrentPortfolio::set(null);
        $this->get('/rishesh')->assertDontSee('class="mp-thumb"', false);

        CurrentPortfolio::set($this->portfolio);
        Project::whereIn('slug', ['dialinger', 'school-management-system'])->get()->each($thumb);
        CurrentPortfolio::set(null);
        $this->get('/rishesh')->assertSee('class="mp-thumb"', false)->assertSee('loading="lazy" width="160" height="100"', false);
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

    public function test_project_page(): void
    {
        $html = $this->get('/rishesh/projects/hajir')->assertOk()
            ->assertSee('data-template="modern_personal"', false)
            ->assertSee('class="page-project"', false)
            ->assertSee('<h1 class="mp-title">Hajir</h1>', false)
            ->assertSee('What I built')
            ->assertSee('<link rel="canonical" href="'.url('/rishesh/projects/hajir').'"', false)
            ->assertDontSee('Preview — status')
            ->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));

        $this->get('/rishesh/projects/secret-project')->assertNotFound();
    }

    public function test_blog_list_and_post(): void
    {
        $this->get('/rishesh/blog')->assertOk()
            ->assertSee('class="page-posts"', false)
            ->assertSee('role="search"', false)
            ->assertSee('name="q"', false)
            ->assertSee('#laravel')
            ->assertSee('Designing payroll engines')
            ->assertDontSee('Secret draft post');

        $this->get('/rishesh/blog?q=nothing-matches-this')->assertOk()->assertSee('Nothing matches');

        $html = $this->get('/rishesh/blog/payroll-engines')->assertOk()
            ->assertSee('class="page-post"', false)
            ->assertSee('<h2 id="why">Why</h2>', false)
            ->assertSee('href="#how"', false)
            ->assertSee('"@type":"BlogPosting"', false)
            ->assertSee('<link rel="canonical" href="'.url('/rishesh/blog/payroll-engines').'"', false)
            ->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertSame(1, substr_count($html, '<h1'));

        $this->get('/rishesh/blog/secret')->assertNotFound();
    }

    public function test_writing_rows_only_when_posts_exist(): void
    {
        CurrentPortfolio::set($this->portfolio);
        Post::query()->delete();
        CurrentPortfolio::set(null);

        $this->get('/rishesh')->assertOk()->assertDontSee('id="writing"', false)->assertDontSee('href="#writing"', false);
    }

    public function test_empty_portfolio_renders_every_page_and_omits_empty_sections(): void
    {
        $bare = $this->portfolio(['name' => 'Bare', 'username' => 'bare-one'], ['headline' => '', 'intro' => null, 'email' => null]);
        $bare->update(['template' => 'modern_personal']);
        CurrentPortfolio::set(null);

        $html = $this->get('/bare-one')->assertOk()
            ->assertSee('data-template="modern_personal"', false)
            ->assertSee('Test Person')
            ->assertSee('id="contact"', false) // the form is on by default
            ->assertDontSee('id="about"', false)
            ->assertDontSee('id="projects"', false)
            ->assertDontSee('id="experience"', false)
            ->assertDontSee('id="writing"', false)
            ->assertDontSee('class="mp-role"', false)
            ->assertDontSee('class="mp-avatar"', false)
            ->assertDontSee('More projects')
            ->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->get('/bare-one/blog')->assertOk()->assertSee('Nothing published yet');

        // Nothing at all to contact -> no contact section either.
        CurrentPortfolio::set($bare);
        \App\Models\Profile::query()->update(['contact_form_enabled' => false]);
        CurrentPortfolio::set(null);
        $this->get('/bare-one')->assertOk()->assertDontSee('id="contact"', false)->assertDontSee('Get in touch');
    }

    public function test_switching_template_keeps_content(): void
    {
        $count = fn () => [Project::withoutGlobalScopes()->count(), Post::withoutGlobalScopes()->count()];
        $before = $count();
        foreach (['minimal_editorial', 'modern_personal', 'classic', 'modern_personal'] as $key) {
            $this->portfolio->update(['template' => $key]);
            $this->get('/rishesh')->assertOk()->assertSee('Hajir');
        }
        $this->assertSame($before, $count());
        $this->get('/rishesh')->assertSee('data-template="modern_personal"', false);
    }
}
