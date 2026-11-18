<?php

namespace Tests\Feature\Templates;

use App\Models\Education;
use App\Models\Experience;
use App\Models\Media;
use App\Models\Portfolio;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\SkillCategory;
use App\Support\CurrentPortfolio;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfessionalTemplateTest extends TestCase
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
        $this->portfolio->update(['template' => 'professional']);

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
        $this->portfolio->update(['template_settings' => ['professional' => $settings]]);
    }

    /** Run $fn with the seeded portfolio as the current tenant. */
    private function within(callable $fn): mixed
    {
        CurrentPortfolio::set($this->portfolio);
        try {
            return $fn();
        } finally {
            CurrentPortfolio::set(null);
        }
    }

    public function test_home_renders_the_professional_layout_with_seeded_content(): void
    {
        $html = $this->get('/rishesh')->assertOk()
            ->assertSee('data-template="professional"', false)
            ->assertSee('class="pro accent-navy"', false)
            ->assertSee('Rishesh Shakya')
            ->assertSee('Velox Labs')
            ->assertSee('Hajir')
            ->assertSee('Patan Multiple Campus')
            ->assertSee('Designing payroll engines')
            ->assertSee('Open to work')
            ->assertSee('Contact Me')
            ->assertSee('View Projects')
            ->assertSee('class="pro-skip" href="#content"', false)
            ->assertSee('<link rel="canonical" href="'.url('/rishesh').'"', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('"@type":"Person"', false)
            ->assertSee('aria-label="Primary"', false)
            ->assertSee('aria-expanded="false" aria-controls="pro-menu"', false)
            ->assertSee('id="pro-menu"', false)
            ->assertSee('href="#experience"', false)
            ->assertSee('href="#writing"', false)
            ->assertSee('is-featured', false)
            ->assertSee('pro-thumb-fallback', false)
            ->assertDontSee('Secret draft post')
            ->assertDontSee('Secret Draft Project')
            ->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'exactly one h1');
        foreach (['about', 'experience', 'projects', 'skills', 'education', 'contact', 'writing'] as $id) {
            $this->assertStringContainsString("<section id=\"{$id}\"", $html);
        }
        $this->assertMatchesRegularExpression('#templates/professional/style\.css\?v=\d+#', $html);
        $this->assertMatchesRegularExpression('#templates/professional/script\.js\?v=\d+" defer#', $html);
    }

    public function test_no_external_assets_and_no_other_template_markers(): void
    {
        $html = $this->get('/rishesh')->assertOk()->getContent();

        foreach (['fonts.googleapis', 'fonts.gstatic', 'cdnjs', 'jsdelivr', 'unpkg'] as $host) {
            $this->assertStringNotContainsString($host, $html);
        }
        preg_match_all('#<(?:script|link)[^>]+(?:src|href)="(https?://[^"]+)"#', $html, $m);
        foreach ($m[1] as $url) {
            $this->assertStringStartsWith(url('/'), $url, "external asset {$url}");
        }
        foreach (['minimal_editorial', 'content_journal', 'creative_experience', 'picto', 'gradient', 'modern_personal'] as $other) {
            $this->assertStringNotContainsString("data-template=\"{$other}\"", $html);
            $this->assertStringNotContainsString("templates/{$other}/", $html);
        }
        $this->assertStringNotContainsString('assets/style.css', $html);
        $this->assertStringNotContainsString('cursor-ring', $html);
    }

    public function test_experience_is_newest_first_regardless_of_admin_order(): void
    {
        $this->within(fn () => Experience::create([
            'company' => 'Ancient Corp', 'role' => 'Junior Dev', 'start_date' => '2019-01-01', 'end_date' => '2020-01-01',
            'sort_order' => 0, 'status' => 'published',
        ]));

        $html = $this->get('/rishesh')->assertOk()->getContent();
        $timeline = substr($html, strpos($html, 'class="pro-timeline"'));
        $velox = strpos($timeline, 'Velox Labs');
        $times = strpos($timeline, 'Times Global');
        $ancient = strpos($timeline, 'Ancient Corp');
        $this->assertNotFalse($ancient);
        $this->assertTrue($velox < $times && $times < $ancient, 'current job, then by start date descending');
    }

    public function test_settings_change_accent_and_stats(): void
    {
        [$projects, $skills] = $this->within(fn () => [
            Project::published()->count(),
            SkillCategory::published()->with(['skills' => fn ($q) => $q->published()])->get()->flatMap->skills->count(),
        ]);

        $this->get('/rishesh')->assertOk()
            ->assertSee('class="pro-stats"', false)
            ->assertSee('Years of experience')
            ->assertSee('<dt>Projects shipped</dt><dd>'.$projects.'</dd>', false)
            ->assertSee('<dt>Skills &amp; tools</dt><dd>'.$skills.'</dd>', false);

        $this->useSettings(['accent' => 'burgundy', 'show_stats' => false]);
        $this->get('/rishesh')->assertOk()
            ->assertSee('class="pro accent-burgundy"', false)
            ->assertDontSee('class="pro-stats"', false)
            ->assertDontSee('Projects shipped');

        $this->useSettings(['accent' => 'teal']);
        $this->get('/rishesh/projects/hajir')->assertOk()->assertSee('class="pro accent-teal"', false);
    }

    public function test_stats_hide_zero_values(): void
    {
        $this->within(function () {
            Experience::query()->update(['start_date' => now()->subMonths(3), 'end_date' => null]);
        });

        $this->get('/rishesh')->assertOk()
            ->assertSee('Projects shipped')
            ->assertDontSee('Years of experience');
    }

    public function test_download_cv_only_when_a_resume_exists(): void
    {
        $this->within(fn () => Profile::query()->firstOrFail()->media()->where('collection', 'resume')->delete());
        $this->get('/rishesh')->assertOk()->assertDontSee('Download CV')->assertDontSee(url('/rishesh/resume'));

        $this->within(fn () => Profile::query()->firstOrFail()->media()->create([
            'collection' => 'resume', 'disk' => 'local', 'path' => 'resume/cv.pdf', 'original_name' => 'cv.pdf',
            'mime_type' => 'application/pdf', 'size' => 10, 'is_active' => true,
        ]));
        $this->get('/rishesh')->assertOk()->assertSee('Download CV')->assertSee('href="'.url('/rishesh/resume').'"', false);
    }

    public function test_project_thumbnails_are_lazy_with_dimensions(): void
    {
        $this->within(fn () => Project::where('slug', 'hajir')->firstOrFail()->media()->create([
            'collection' => 'thumbnail', 'disk' => 'public', 'path' => 'projects/hajir.png',
            'original_name' => 'hajir.png', 'mime_type' => 'image/png', 'size' => 10,
        ]));

        $this->get('/rishesh')->assertOk()
            ->assertSee('projects/hajir.png" alt="" loading="lazy" decoding="async" width="640" height="400"', false);
    }

    public function test_contact_section_has_form_with_honeypot_and_shows_feedback(): void
    {
        $this->get('/rishesh')->assertOk()
            ->assertSee('<section id="contact"', false)
            ->assertSee('action="'.url('/rishesh/contact'), false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="website" tabindex="-1" autocomplete="off"', false)
            ->assertSee('for="c-name"', false)
            ->assertSee('for="c-email"', false)
            ->assertSee('for="c-subject"', false)
            ->assertSee('for="c-message"', false);

        $this->post('/rishesh/contact', ['name' => '', 'email' => 'nope', 'message' => 'short'])->assertRedirect(url('/rishesh').'#contact');
        $this->get('/rishesh')->assertSee('aria-invalid="true"', false)->assertSee('role="alert"', false);

        $this->post('/rishesh/contact', ['name' => 'Ann', 'email' => 'ann@example.test', 'message' => 'Hello there, nice portfolio!']);
        $this->get('/rishesh')->assertSee('your message is on its way');

        $this->within(fn () => Profile::query()->update(['contact_form_enabled' => false]));
        $this->get('/rishesh')->assertOk()->assertDontSee('name="website"', false)->assertSee('<section id="contact"', false);
    }

    public function test_project_page(): void
    {
        $html = $this->get('/rishesh/projects/hajir')->assertOk()
            ->assertSee('data-template="professional"', false)
            ->assertSee('page-project', false)
            ->assertSee('<h1 class="pro-page-title">Hajir</h1>', false)
            ->assertSee('What I built')
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('<link rel="canonical" href="'.url('/rishesh/projects/hajir').'"', false)
            ->assertDontSee('Preview — status')
            ->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));

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
            ->assertSee('<link rel="canonical" href="'.url('/rishesh/blog/payroll-engines').'"', false)
            ->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertSame(1, substr_count($html, '<h1'));

        $this->get('/rishesh/blog/secret')->assertNotFound();
    }

    public function test_switching_template_does_not_change_content(): void
    {
        $counts = fn () => $this->within(fn () => [
            Project::count(), Experience::count(), Education::count(), Post::count(), Media::count(),
            Profile::query()->value('full_name'),
        ]);
        $before = $counts();

        foreach (['classic', 'minimal_editorial', 'professional'] as $key) {
            $this->portfolio->update(['template' => $key]);
            $this->get('/rishesh')->assertOk();
        }

        $this->assertSame($before, $counts());
    }

    public function test_empty_portfolio_renders_every_page_and_omits_empty_sections(): void
    {
        $bare = $this->portfolio(['name' => 'Bare', 'username' => 'bare-one'], ['headline' => '', 'intro' => null, 'email' => null, 'open_to_work' => false]);
        $bare->update(['template' => 'professional']);
        CurrentPortfolio::set(null);

        $html = $this->get('/bare-one')->assertOk()
            ->assertSee('data-template="professional"', false)
            ->assertSee('Test Person')
            ->assertDontSee('<section id="experience"', false)
            ->assertDontSee('<section id="projects"', false)
            ->assertDontSee('<section id="skills"', false)
            ->assertDontSee('<section id="education"', false)
            ->assertDontSee('<section id="about"', false)
            ->assertDontSee('<section id="writing"', false)
            ->assertDontSee('<section id="testimonials"', false)
            ->assertDontSee('href="#projects"', false)
            ->assertDontSee('Download CV')
            ->assertDontSee('Open to work')
            ->assertDontSee('class="pro-stats"', false)
            ->assertDontSee('class="pro-role"', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertDoesNotMatchRegularExpression('#<img[^>]+src=""#', $html);

        $this->get('/bare-one/blog')->assertOk()->assertSee('Nothing published yet');
        $this->get('/bare-one/notes')->assertOk();
    }

    public function test_default_test_portfolio_renders(): void
    {
        $this->portfolio(['template' => 'professional']);
        CurrentPortfolio::set(null);

        $this->get('/test-person')->assertOk()
            ->assertSee('data-template="professional"', false)
            ->assertSee('Backend Developer')
            ->assertSee('<section id="contact"', false);
    }
}
