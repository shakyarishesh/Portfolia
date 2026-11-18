<?php

namespace Tests\Feature\Templates;

use App\Models\Certification;
use App\Models\Education;
use App\Models\Portfolio;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Support\CurrentPortfolio;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GradientTemplateTest extends TestCase
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
        $this->portfolio->update(['template' => 'gradient']);

        $this->within(fn () => [
            Post::create([
                'title' => 'Designing payroll engines', 'slug' => 'payroll-engines', 'status' => 'published', 'published_at' => now()->subDay(),
                'excerpt' => 'How I structure salary calculations.', 'tags' => ['laravel'], 'category' => 'Backend',
                'body' => "## Why\n\nBecause.\n\n## How\n\nLike this.\n\n<script>alert(1)</script>",
            ]),
            Post::create(['title' => 'Secret draft post', 'slug' => 'secret', 'status' => 'draft', 'body' => 'x']),
            Project::create(['name' => 'Secret Draft Project', 'slug' => 'secret-project', 'status' => 'draft']),
        ]);
    }

    private function within(callable $fn): mixed
    {
        CurrentPortfolio::set($this->portfolio);
        $result = $fn();
        CurrentPortfolio::set(null);

        return $result;
    }

    private function useSettings(array $settings): void
    {
        $this->portfolio->update(['template_settings' => ['gradient' => $settings]]);
    }

    public function test_home_renders_the_gradient_layout_with_seeded_content(): void
    {
        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('data-template="gradient" data-gradient="blue_violet" data-shapes="on"', false)
            ->assertSee('Rishesh Shakya')->assertSee('Velox Labs')->assertSee('Hajir')->assertSee('Patan Multiple Campus')
            ->assertSee('Designing payroll engines')
            ->assertSee('class="gd-skip" href="#content"', false)
            ->assertSee('<nav class="gd-nav" aria-label="Primary">', false)
            ->assertSee('data-spy="experience"', false)
            ->assertSee('href="#writing"', false)
            ->assertSee('class="gd-btn gd-btn-sm gd-cta" href="#contact"', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('"@type":"Person"', false)
            ->assertDontSee('Secret draft post')
            ->assertDontSee('Secret Draft Project')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'exactly one h1');
        foreach (['home', 'about', 'experience', 'projects', 'skills', 'education', 'writing', 'contact'] as $id) {
            $this->assertStringContainsString("id=\"{$id}\"", $html);
        }
        $this->assertMatchesRegularExpression('#templates/gradient/style\.css\?v=\d+#', $html);
        $this->assertMatchesRegularExpression('#templates/gradient/script\.js\?v=\d+" defer#', $html);
        $this->assertDoesNotMatchRegularExpression('#<(script|link)[^>]+(src|href)="https?://(?!localhost)#', $html);
        foreach (['fonts.googleapis', 'cdnjs', 'jsdelivr'] as $host) {
            $this->assertStringNotContainsString($host, $html);
        }
        // None of the other templates' markers.
        foreach (['data-template="minimal_editorial"', 'data-template="creative_experience"', 'data-template="content_journal"',
            'class="me ', 'hero-canvas', 'cursor-glow', 'assets/main.js', 'templates/professional/', 'templates/picto/', 'templates/modern_personal/'] as $other) {
            $this->assertStringNotContainsString($other, $html);
        }
    }

    public function test_hero_has_exactly_one_gradient_accented_phrase(): void
    {
        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('<p class="gd-headline">Backend <span class="gd-accent-phrase">Developer</span></p>', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, 'gd-accent-phrase'));

        // Longer headlines accent the last two words.
        $this->within(fn () => Profile::query()->update(['headline' => 'Backend developer building payroll systems']));
        $this->get('/rishesh')->assertSee('Backend developer building <span class="gd-accent-phrase">payroll systems</span>', false);
    }

    public function test_settings_change_gradient_and_shapes(): void
    {
        $this->get('/rishesh')->assertSee('class="gd-shapes" aria-hidden="true"', false);

        $this->useSettings(['gradient' => 'coral_purple', 'shapes' => false]);
        $this->get('/rishesh')->assertOk()
            ->assertSee('data-gradient="coral_purple" data-shapes="off"', false)
            ->assertDontSee('class="gd-shapes"', false);

        $this->useSettings(['gradient' => 'teal_blue', 'shapes' => true]);
        $this->get('/rishesh')->assertSee('data-gradient="teal_blue" data-shapes="on"', false)->assertSee('class="gd-shapes"', false);

        // Unknown values fall back to defaults (allow-listed by the registry).
        $this->useSettings(['gradient' => '"><script>']);
        $this->get('/rishesh')->assertSee('data-gradient="blue_violet"', false)->assertDontSee('"><script>', false);
    }

    public function test_education_and_certificates_are_separate_sections_when_present(): void
    {
        // Seed has education but no certificates.
        $this->get('/rishesh')->assertSee('<section id="education" class="gd-section gd-education"', false)
            ->assertDontSee('gd-certificates', false);

        $this->within(fn () => Certification::create(['name' => 'AWS Cloud Practitioner', 'issuer' => 'Amazon', 'issued_on' => '2024-05-01', 'status' => 'published']));
        $this->get('/rishesh')
            ->assertSee('<section id="education" class="gd-section gd-education"', false)
            ->assertSee('<section id="certificates" class="gd-section gd-certificates"', false)
            ->assertSee('AWS Cloud Practitioner')->assertSee('Amazon · May 2024');

        // Only certificates → they take over the #education anchor; no empty education block.
        $this->within(fn () => Education::query()->delete());
        $this->get('/rishesh')->assertOk()
            ->assertSee('<section id="education" class="gd-section gd-certificates"', false)
            ->assertDontSee('gd-education', false);

        // Neither → the section and its nav link disappear.
        $this->within(fn () => Certification::query()->delete());
        $this->get('/rishesh')->assertOk()->assertDontSee('id="education"', false)->assertDontSee('href="#education"', false);
    }

    public function test_projects_grid_marks_featured_cards_and_handles_missing_images(): void
    {
        $this->within(fn () => Project::where('slug', 'hajir')->firstOrFail()->media()->create([
            'collection' => 'thumbnail', 'disk' => 'public', 'path' => 'projects/hajir.png',
            'original_name' => 'hajir.png', 'mime_type' => 'image/png', 'size' => 10,
        ]));

        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('class="gd-project is-featured has-media reveal" aria-labelledby="p-hajir"', false)
            ->assertSee('class="gd-project is-featured no-media reveal" aria-labelledby="p-dialinger"', false)
            ->assertSee('alt="" loading="lazy" width="640" height="360"', false)
            ->getContent();

        $published = $this->within(fn () => Project::published()->count());
        $this->assertSame($published, substr_count($html, 'class="gd-project '));
        $this->assertSame(1, substr_count($html, 'class="gd-project-media"'), 'only projects with a thumbnail get an image block');
    }

    public function test_contact_section_has_form_with_honeypot_and_shows_feedback(): void
    {
        $this->get('/rishesh')->assertOk()
            ->assertSee('id="contact"', false)
            ->assertSee('action="'.route('contact.store').'"', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="website" tabindex="-1" autocomplete="off"', false)
            ->assertSee('<label for="c-email">Email</label>', false)
            ->assertSee('<label for="c-message">Message</label>', false);

        $this->post('/rishesh/contact', ['name' => '', 'email' => 'nope', 'message' => 'short'])->assertRedirect(url('/rishesh').'#contact');
        $this->get('/rishesh')->assertSee('aria-invalid="true"', false)->assertSee('role="alert"', false);

        $this->post('/rishesh/contact', ['name' => 'Ann', 'email' => 'ann@example.test', 'message' => 'Hello there, nice portfolio!']);
        $this->get('/rishesh')->assertSee('your message is on its way');

        $this->within(fn () => Profile::query()->update(['contact_form_enabled' => false]));
        $this->get('/rishesh')->assertOk()->assertSee('id="contact"', false)->assertDontSee('name="website"', false);
    }

    public function test_project_page(): void
    {
        $this->get('/rishesh/projects/hajir')->assertOk()
            ->assertSee('data-template="gradient"', false)
            ->assertSee('<h1 class="gd-article-title">Hajir</h1>', false)
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
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);

        $this->get('/rishesh/blog/secret')->assertNotFound();
    }

    public function test_empty_portfolio_renders_every_page(): void
    {
        $bare = $this->portfolio(['name' => 'Bare', 'username' => 'bare-one'], ['headline' => '', 'intro' => null, 'email' => null]);
        $bare->update(['template' => 'gradient']);
        CurrentPortfolio::set(null);

        $html = $this->get('/bare-one')->assertOk()
            ->assertSee('data-template="gradient"', false)
            ->assertSee('Test Person')
            ->assertSee('id="contact"', false)
            ->assertDontSee('href="#writing"', false)
            ->assertDontSee('id="about"', false)
            ->assertDontSee('gd-accent-phrase', false)
            ->assertDontSee('<img', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->get('/bare-one/blog')->assertOk()->assertSee('Nothing published yet');
    }

    public function test_switching_templates_keeps_content(): void
    {
        $counts = fn () => $this->within(fn () => [Project::count(), Post::count(), Education::count(), Profile::count()]);
        $before = $counts();
        $cards = substr_count($this->get('/rishesh')->getContent(), 'class="gd-project ');

        $this->portfolio->update(['template' => 'minimal_editorial']);
        $this->get('/rishesh')->assertOk()->assertDontSee('data-template="gradient"', false);
        $this->portfolio->update(['template' => 'gradient']);

        $this->assertSame($before, $counts());
        $this->assertSame($cards, substr_count($this->get('/rishesh')->getContent(), 'class="gd-project '));
    }

    public function test_assets_exist_and_are_self_contained(): void
    {
        foreach (['style.css', 'script.js', 'preview.svg'] as $file) {
            $this->assertFileExists(public_path("templates/gradient/{$file}"));
        }
        $this->assertStringNotContainsString('http', preg_replace('#xmlns="http://www.w3.org/2000/svg"#', '', file_get_contents(public_path('templates/gradient/preview.svg'))));
        $css = file_get_contents(public_path('templates/gradient/style.css'));
        $this->assertStringNotContainsString('@import', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
        // Gradient text has a solid fallback colour and only clips inside @supports.
        $this->assertMatchesRegularExpression('/\.gd-accent-phrase \{ color: var\(--g2\); \}\s*@supports/', $css);
        $this->assertStringContainsString('prefers-reduced-motion', file_get_contents(public_path('templates/gradient/script.js')));
    }
}
