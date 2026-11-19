<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentPortfolio;
use App\Support\Templates;
use App\Support\Username;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class MultiPortfolioTest extends TestCase
{
    use RefreshDatabase;

    private Portfolio $alice;

    private Portfolio $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->portfolio(['name' => 'Alice', 'username' => 'alice'], ['full_name' => 'Alice Adams', 'headline' => 'Alice Engineer']);
        Project::create(['name' => 'Alice Secret Draft', 'slug' => 'shared-slug', 'status' => 'draft']);
        Project::create(['name' => 'Alice Project', 'slug' => 'alice-project', 'status' => 'published']);

        $this->bob = $this->portfolio(['name' => 'Bob', 'username' => 'bob'], ['full_name' => 'Bob Brown', 'headline' => 'Bob Designer']);
        Project::create(['name' => 'Bob Project', 'slug' => 'shared-slug', 'status' => 'published']); // same slug, other portfolio: allowed
        CurrentPortfolio::set(null);
    }

    private function valid(string $username, ?Portfolio $ignore = null): bool
    {
        return Validator::make(['u' => $username], ['u' => Username::rules($ignore)])->passes();
    }

    public function test_username_format_reserved_words_and_case_insensitive_uniqueness(): void
    {
        $this->assertTrue($this->valid('hello'));
        $this->assertTrue($this->valid('sujan-shakya2'));
        foreach (['ab', '-abc', 'abc-', 'a--b', 'has space', 'UPPER', 'dot.name', str_repeat('a', 41)] as $bad) {
            $this->assertFalse($this->valid($bad), "{$bad} should be invalid");
        }
        foreach (['admin', 'api', 'login', 'blog', 'preview', 'storage', 'assets', 'sitemap', 'playground', 'templates', 'up'] as $reserved) {
            $this->assertFalse($this->valid($reserved), "{$reserved} should be reserved");
        }
        $this->assertFalse($this->valid('alice'));
        $this->assertTrue($this->valid('alice', $this->alice), 'own username is fine when editing');

        $saved = Portfolio::create(['name' => 'Case', 'username' => '  CaseUser ']);
        $this->assertSame('caseuser', $saved->username);
    }

    public function test_public_pages_resolve_by_username_and_only_show_that_portfolio(): void
    {
        $this->get('/alice')->assertOk()->assertSee('Alice Adams')->assertDontSee('Bob Brown');
        $this->get('/bob')->assertOk()->assertSee('Bob Brown')->assertDontSee('Alice Adams');

        // Same slug in two portfolios resolves to each one's own record; drafts stay hidden.
        $this->get('/bob/projects/shared-slug')->assertOk()->assertSee('Bob Project');
        $this->get('/alice/projects/shared-slug')->assertNotFound();
        $this->get('/bob/projects/alice-project')->assertNotFound();
        $this->get('/alice/projects/alice-project')->assertOk();

        $this->get('/nobody')->assertNotFound();
        $this->get('/Alice')->assertRedirect('/alice');
    }

    public function test_draft_disabled_and_archived_portfolios_are_not_public(): void
    {
        foreach (['draft', 'disabled', 'archived'] as $status) {
            $this->alice->update(['status' => $status]);
            $this->get('/alice')->assertNotFound();
            $this->get('/alice/projects/alice-project')->assertNotFound();
        }
        $this->assertStringNotContainsString('/alice', $this->get('/sitemap.xml')->getContent());
        $this->assertStringContainsString('/bob', $this->get('/sitemap.xml')->getContent());
    }

    public function test_preview_is_limited_to_admins_and_owners_and_is_noindex(): void
    {
        $this->alice->update(['status' => 'draft']);
        $stranger = User::factory()->create(['is_admin' => false]);
        $owner = User::factory()->create(['is_admin' => false]);
        $this->alice->update(['user_id' => $owner->id]);

        $this->actingAs($stranger)->get('/alice')->assertNotFound();
        $this->actingAs($owner)->get('/alice')->assertOk()->assertSee('PREVIEW')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('noindex', false);

        $this->admin();
        $this->get('/alice')->assertOk()->assertSee('PREVIEW');
    }

    public function test_template_preview_does_not_change_the_live_site_or_content(): void
    {
        $this->admin();
        $this->alice->update(['draft_template' => 'minimal_editorial']);
        $before = Project::withoutGlobalScopes()->where('portfolio_id', $this->alice->id)->count();

        $this->get("/admin/portfolios/{$this->alice->id}/preview")->assertRedirect('/alice');
        $this->assertSame('minimal_editorial', session(Templates::previewKey($this->alice)));
        $this->assertSame('classic', $this->alice->fresh()->template, 'preview never publishes');

        // Visitors (logged out) still get the live template, with no preview banner.
        auth()->logout();
        $this->get('/alice')->assertOk()->assertDontSee('PREVIEW');
        $this->assertSame($before, Project::withoutGlobalScopes()->where('portfolio_id', $this->alice->id)->count());
    }

    public function test_admin_cannot_reach_another_portfolios_records(): void
    {
        $this->admin();
        $this->withSession(['admin_portfolio_id' => $this->alice->id]);
        $bobProject = Project::withoutGlobalScopes()->where('portfolio_id', $this->bob->id)->firstOrFail();

        $this->get("/admin/projects/{$bobProject->id}/edit")->assertNotFound();
        $this->put("/admin/projects/{$bobProject->id}", ['name' => 'Hacked', 'status' => 'published'])->assertNotFound();
        $this->delete("/admin/projects/{$bobProject->id}")->assertNotFound();
        $this->get("/admin/projects/{$bobProject->id}/preview")->assertNotFound();
        $this->assertSame('Bob Project', $bobProject->fresh()->name);

        // New content lands in the selected portfolio only.
        $this->post('/admin/projects', ['name' => 'Fresh', 'status' => 'published'])->assertRedirect();
        $this->assertSame($this->alice->id, Project::withoutGlobalScopes()->where('name', 'Fresh')->value('portfolio_id'));
    }

    public function test_system_routes_win_over_usernames_and_legacy_urls_redirect(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk();
        $this->get('/robots.txt')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
        $this->get('/up')->assertOk();

        // "/" is the platform landing page; old single-site URLs redirect to the default (oldest published) portfolio.
        $this->get('/')->assertOk()->assertSee('Create your portfolio')->assertDontSee('Alice Adams');
        $this->get('/projects/alice-project')->assertRedirect('/alice/projects/alice-project')->assertStatus(301);
        $this->get('/blog')->assertRedirect('/alice/blog');
        config(['portfolio.default_username' => 'bob']);
        $this->get('/resume')->assertRedirect('/bob/resume');
    }

    public function test_api_and_urls_are_per_portfolio(): void
    {
        $this->getJson('/api/portfolio/bob')->assertOk()->assertJsonPath('profile.name', 'Bob Brown')
            ->assertJsonPath('projects.0.url', url('/bob/projects/shared-slug'));
        $this->getJson('/api/portfolio')->assertOk()->assertJsonPath('profile.name', 'Alice Adams');
    }

    public function test_notes_are_separate_from_articles(): void
    {
        CurrentPortfolio::set($this->alice);
        Post::create(['title' => 'Evergreen idea', 'slug' => 'idea', 'kind' => 'note', 'status' => 'published', 'published_at' => now()->subDay(), 'body' => 'Note body']);
        Post::create(['title' => 'Dated article', 'slug' => 'article', 'kind' => 'article', 'status' => 'published', 'published_at' => now()->subDay(), 'body' => 'Body']);
        CurrentPortfolio::set(null);

        $this->get('/alice/notes')->assertOk()->assertSee('Evergreen idea')->assertDontSee('Dated article');
        $this->get('/alice/blog')->assertOk()->assertSee('Dated article')->assertDontSee('Evergreen idea');
        $this->get('/alice/notes/idea')->assertOk();
        $this->get('/alice/blog/idea')->assertRedirect('/alice/notes/idea');
        $this->get('/bob/notes/idea')->assertNotFound();
    }

    public function test_every_template_renders_every_page_with_the_same_content(): void
    {
        CurrentPortfolio::set($this->alice);
        Post::create(['title' => 'Template article', 'slug' => 'tpl', 'status' => 'published', 'published_at' => now()->subDay(), 'body' => '## Hi']);
        CurrentPortfolio::set(null);

        foreach (array_keys(Templates::available()) as $key) {
            $this->alice->update(['template' => $key]);
            $this->get('/alice')->assertOk()->assertSee('Alice Adams')->assertSee('Alice Project');
            $this->get('/alice/projects/alice-project')->assertOk()->assertSee('Alice Project');
            $this->get('/alice/blog')->assertOk()->assertSee('Template article');
            $this->get('/alice/blog/tpl')->assertOk()->assertSee('Template article');
            $this->assertSame('Alice Adams', Profile::withoutGlobalScopes()->where('portfolio_id', $this->alice->id)->value('full_name'));
        }
    }

    public function test_template_keys_and_settings_are_allow_listed(): void
    {
        $this->assertSame('portfolio.index', Templates::view('../../etc/passwd', 'home'));
        $this->assertSame('portfolios.templates.minimal_editorial.home', Templates::view('minimal_editorial', 'home'));
        $this->alice->update(['template' => 'nope', 'template_settings' => ['minimal_editorial' => ['accent' => '<script>', 'evil' => 'x']]]);
        $this->get('/alice')->assertOk(); // unknown template falls back to classic
        $this->assertSame(['accent' => 'teal', 'theme' => 'dark', 'show_thumbnails' => true, 'intro' => true, 'intro_words' => null], $this->alice->settingsFor('minimal_editorial'));
    }

    public function test_content_writes_without_a_current_portfolio_are_refused(): void
    {
        $this->expectException(\LogicException::class);
        Project::create(['name' => 'Orphan', 'slug' => 'orphan']);
    }
}
