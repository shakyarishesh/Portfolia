<?php

namespace Tests\Feature;

use App\Models\Achievement;
use App\Models\Project;
use App\Models\Section;
use App\Models\Skill;
use App\Models\SkillCategory;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(PortfolioSeeder::class);
    }

    public function test_home_shows_seeded_profile_with_seo_metadata(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Rishesh Shakya — Backend Developer</title>', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('application/ld+json', false)
            ->assertSee('Velox Labs')
            ->assertSee('Hajir')
            ->assertSee('Patan Multiple Campus');
    }

    public function test_only_published_content_is_public(): void
    {
        Project::where('slug', 'dialinger')->update(['status' => 'draft']);
        $hidden = Achievement::where('status', 'needs_review')->firstOrFail();

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Dialinger — Cloud', false)
            ->assertDontSee($hidden->title);
        $this->get('/projects/dialinger')->assertNotFound();
        $this->get('/projects/hajir')->assertOk()->assertSee('What I built');
    }

    public function test_hidden_and_empty_sections_are_left_out(): void
    {
        Section::where('key', 'education')->update(['is_visible' => false]);
        SkillCategory::query()->update(['status' => 'draft']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="education"', $html);
        $this->assertStringNotContainsString('id="skills"', $html);
        $this->assertStringContainsString('id="projects"', $html);
    }

    public function test_section_order_follows_admin_settings(): void
    {
        Section::where('key', 'contact')->update(['sort_order' => 0]);
        $html = $this->get('/')->getContent();

        $this->assertLessThan(strpos($html, 'data-tab="home">'), strpos($html, 'data-tab="contact">'));
    }

    public function test_unrated_skills_show_no_meter(): void
    {
        $rated = Skill::where('name', 'Laravel')->firstOrFail();
        $this->assertSame(3, $rated->level);
        $this->assertNull(Skill::where('name', 'Docker')->value('level'));
        $this->get('/')->assertSee('aria-label="Strong"', false);
    }

    public function test_api_returns_published_content_as_json(): void
    {
        $this->getJson('/api/portfolio')
            ->assertOk()
            ->assertJsonPath('profile.name', 'Rishesh Shakya')
            ->assertJsonPath('experience.0.company', 'Velox Labs')
            ->assertJsonCount(3, 'projects')
            ->assertJsonMissing(['title' => 'Earned > monthly salary']);
    }

    public function test_resume_download_requires_an_uploaded_file(): void
    {
        \App\Models\Media::query()->where('collection', 'resume')->get()->each->delete();

        $this->get('/resume')->assertNotFound();
        $this->get('/')->assertDontSee('Download CV');
    }

    public function test_markdown_in_content_is_escaped(): void
    {
        \App\Models\Profile::query()->update(['intro' => 'Hi <script>alert(1)</script> **bold** [x](javascript:alert(1))']);

        $this->get('/')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="javascript:', false)
            ->assertSee('<strong>bold</strong>', false);
    }
}
