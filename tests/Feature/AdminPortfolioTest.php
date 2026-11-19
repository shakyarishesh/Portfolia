<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveAdminPortfolio;
use App\Models\Portfolio;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Section;
use App\Models\User;
use App\Support\CurrentPortfolio;
use App\Support\Templates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPortfolioTest extends TestCase
{
    use RefreshDatabase;

    private Portfolio $alice;

    private Portfolio $bob;

    protected function setUp(): void
    {
        parent::setUp();
        $this->alice = $this->portfolio(['name' => 'Alice', 'username' => 'alice'], ['full_name' => 'Alice Adams']);
        Project::create(['name' => 'Alice Project', 'slug' => 'alice-project', 'status' => 'published']);
        $this->bob = $this->portfolio(['name' => 'Bob', 'username' => 'bob', 'status' => 'draft', 'template' => 'minimal_editorial'], ['full_name' => 'Bob Brown']);
        Project::create(['name' => 'Bob Project', 'slug' => 'bob-project', 'status' => 'published']);
        CurrentPortfolio::set(null);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + ['name' => 'Carol', 'username' => 'carol', 'status' => 'draft', 'template' => 'classic',
            'seo_title' => 'Carol — Engineer', 'seo_description' => 'Hi', 'og_image_url' => 'https://example.test/og.png'];
    }

    private function rows(Portfolio $p): array
    {
        return [
            Project::withoutGlobalScopes()->where('portfolio_id', $p->id)->count(),
            Profile::withoutGlobalScopes()->where('portfolio_id', $p->id)->count(),
            Section::withoutGlobalScopes()->where('portfolio_id', $p->id)->count(),
        ];
    }

    public function test_list_search_and_filters(): void
    {
        $owner = User::factory()->create(['name' => 'Olga Owner', 'email' => 'olga@example.test']);
        $this->bob->update(['user_id' => $owner->id]);
        $this->admin();

        $this->get('/admin/portfolios')->assertOk()->assertSee('Alice')->assertSee('/bob')->assertSee('Minimal Editorial');
        [$a, $b] = ["/admin/portfolios/{$this->alice->id}/edit", "/admin/portfolios/{$this->bob->id}/edit"];
        $this->get('/admin/portfolios?q=olga')->assertOk()->assertSee($b)->assertDontSee($a);
        $this->get('/admin/portfolios?q=alic')->assertOk()->assertSee($a)->assertDontSee($b);
        $this->get('/admin/portfolios?template=minimal_editorial')->assertOk()->assertSee($b)->assertDontSee($a);
        $this->get('/admin/portfolios?status=published')->assertOk()->assertSee($a)->assertDontSee($b);
        $this->get('/admin/portfolios/create')->assertOk()->assertSee('Classic')->assertSee('templates/classic/preview.svg');
        $this->get("/admin/portfolios/{$this->bob->id}/edit")->assertOk()->assertSee('Minimal Editorial settings');
    }

    public function test_create_provisions_profile_and_sections_and_becomes_current(): void
    {
        $this->admin();
        $this->post('/admin/portfolios', $this->payload(['username' => '  Carol ']))
            ->assertRedirect()->assertSessionHasNoErrors();

        $carol = Portfolio::where('username', 'carol')->firstOrFail();
        $this->assertSame(['classic', 'draft', 'Carol — Engineer'], [$carol->template, $carol->status, $carol->seo_title]);
        $this->assertNull($carol->published_at);
        $this->assertSame([0, 1, count(Portfolio::DEFAULT_SECTIONS)], $this->rows($carol));
        $this->assertSame($carol->id, session(ResolveAdminPortfolio::SESSION_KEY));
        $this->get('/admin/projects')->assertOk()->assertDontSee('Alice Project');
        $this->get('/admin')->assertOk()->assertSee(url('carol'));
    }

    public function test_invalid_reserved_and_duplicate_usernames_are_rejected(): void
    {
        $this->admin();
        foreach (['ab', '-bad', 'has space', 'dot.name', 'admin', 'login', 'templates', 'ALICE', 'alice'] as $bad) {
            $this->post('/admin/portfolios', $this->payload(['username' => $bad]))->assertSessionHasErrors('username');
        }
        $this->post('/admin/portfolios', $this->payload(['template' => '../evil']))->assertSessionHasErrors('template');
        $this->post('/admin/portfolios', $this->payload(['status' => 'live']))->assertSessionHasErrors('status');
        $this->post('/admin/portfolios', $this->payload(['og_image_url' => 'javascript:alert(1)']))->assertSessionHasErrors('og_image_url');
        $this->assertSame(2, Portfolio::count());
    }

    public function test_edit_and_update(): void
    {
        $this->admin();
        $this->get("/admin/portfolios/{$this->alice->id}/edit")->assertOk()->assertSee('alice')->assertSee(url('alice'));

        $this->put("/admin/portfolios/{$this->alice->id}", ['name' => 'Alice A', 'username' => 'alice', 'status' => 'published'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Alice A', $this->alice->fresh()->name);

        $this->put("/admin/portfolios/{$this->alice->id}", ['name' => 'Alice A', 'username' => 'BOB', 'status' => 'published'])->assertSessionHasErrors('username');
        $this->put("/admin/portfolios/{$this->alice->id}", ['name' => 'Alice A', 'username' => 'Alice-2', 'status' => 'published'])->assertSessionHasNoErrors();
        $this->assertSame('alice-2', $this->alice->fresh()->username);
        $this->get('/alice-2')->assertOk();
        $this->get('/alice')->assertNotFound();
    }

    public function test_template_draft_preview_publish_never_touches_content(): void
    {
        $this->admin();
        $before = [$this->rows($this->alice), $this->rows($this->bob)];

        $this->from("/admin/portfolios/{$this->alice->id}/edit")
            ->post("/admin/portfolios/{$this->alice->id}/template", ['template' => 'content_journal'])
            ->assertRedirect("/admin/portfolios/{$this->alice->id}/edit")->assertSessionHas('success', 'Saved as draft. Preview it, then publish.');
        $this->assertSame(['classic', 'content_journal'], [$this->alice->fresh()->template, $this->alice->fresh()->draft_template]);
        $this->get("/admin/portfolios/{$this->alice->id}/edit")->assertOk()->assertSee('Draft template')->assertSee('Content Journal settings');

        $this->get("/admin/portfolios/{$this->alice->id}/preview")->assertRedirect('/alice');
        $this->assertSame('content_journal', session(Templates::previewKey($this->alice)));

        $this->post("/admin/portfolios/{$this->alice->id}/template", ['template' => 'nope'])->assertSessionHasErrors('template');

        $this->post("/admin/portfolios/{$this->alice->id}/template/publish")->assertRedirect();
        $this->assertSame(['content_journal', null], [$this->alice->fresh()->template, $this->alice->fresh()->draft_template]);
        $this->assertNull(session(Templates::previewKey($this->alice)));

        // Choosing the live template clears any draft.
        $this->post("/admin/portfolios/{$this->alice->id}/template", ['template' => 'classic']);
        $this->post("/admin/portfolios/{$this->alice->id}/template", ['template' => 'content_journal']);
        $this->assertNull($this->alice->fresh()->draft_template);

        $this->assertSame($before, [$this->rows($this->alice), $this->rows($this->bob)]);
    }

    public function test_template_settings_are_saved_per_template_and_allow_listed(): void
    {
        $this->admin();
        $this->alice->update(['template' => 'minimal_editorial', 'template_settings' => ['content_journal' => ['theme' => 'dark', 'home_posts' => '10', 'now' => null, 'uses' => null]]]);
        $base = ['name' => 'Alice', 'username' => 'alice', 'status' => 'published', 'settings_template' => 'minimal_editorial'];

        $this->put("/admin/portfolios/{$this->alice->id}", $base + ['settings' => ['accent' => 'rose', 'theme' => 'light', 'evil' => '<script>']])
            ->assertSessionHasNoErrors();
        $saved = $this->alice->fresh()->template_settings;
        $this->assertEquals(['accent' => 'rose', 'theme' => 'light', 'show_thumbnails' => false], $saved['minimal_editorial']);
        $this->assertSame('10', $saved['content_journal']['home_posts'], 'other templates keep their settings');

        $this->put("/admin/portfolios/{$this->alice->id}", $base + ['settings' => ['accent' => 'neon']])->assertSessionHasErrors('settings.accent');
        $this->put("/admin/portfolios/{$this->alice->id}", ['settings_template' => '../x'] + $base)->assertSessionHasErrors('settings_template');
    }

    public function test_status_changes_and_public_visibility(): void
    {
        $this->admin();
        $this->assertNull($this->bob->published_at);
        $this->post("/admin/portfolios/{$this->bob->id}/status", ['status' => 'published'])->assertRedirect();
        $first = $this->bob->fresh()->published_at;
        $this->assertNotNull($first);

        $this->travel(1)->days();
        $this->post("/admin/portfolios/{$this->bob->id}/status", ['status' => 'draft']);
        $this->post("/admin/portfolios/{$this->bob->id}/status", ['status' => 'published']);
        $this->assertEquals($first, $this->bob->fresh()->published_at, 'published_at is set only the first time');

        $this->post("/admin/portfolios/{$this->alice->id}/status", ['status' => 'archived']);
        $this->post("/admin/portfolios/{$this->alice->id}/status", ['status' => 'gone'])->assertSessionHasErrors('status');
        $this->assertSame('archived', $this->alice->fresh()->status);

        auth()->logout();
        $this->get('/alice')->assertNotFound();
        $this->get('/bob')->assertOk();
    }

    public function test_delete_requires_typed_username_and_cascades_only_that_portfolio(): void
    {
        $this->admin();
        $this->withSession([ResolveAdminPortfolio::SESSION_KEY => $this->alice->id]);
        $bobRows = $this->rows($this->bob);

        $this->delete("/admin/portfolios/{$this->alice->id}")->assertSessionHasErrors('confirm_username');
        $this->delete("/admin/portfolios/{$this->alice->id}", ['confirm_username' => 'bob'])->assertSessionHasErrors('confirm_username');
        $this->assertNotNull($this->alice->fresh());

        $this->delete("/admin/portfolios/{$this->alice->id}", ['confirm_username' => 'alice'])->assertRedirect('/admin/portfolios');
        $this->assertNull($this->alice->fresh());
        $this->assertSame([0, 0, 0], $this->rows($this->alice));
        $this->assertSame($bobRows, $this->rows($this->bob));
        $this->assertNull(session(ResolveAdminPortfolio::SESSION_KEY));
    }

    public function test_switch_changes_which_portfolio_the_content_screens_edit(): void
    {
        $this->admin();
        $this->post("/admin/portfolios/{$this->alice->id}/switch")->assertRedirect();
        $this->get('/admin/projects')->assertOk()->assertSee('Alice Project')->assertDontSee('Bob Project')->assertSee(url('alice'));

        $this->from('/admin/projects')->post("/admin/portfolios/{$this->bob->id}/switch")->assertRedirect('/admin/projects');
        $this->get('/admin/projects')->assertOk()->assertSee('Bob Project')->assertDontSee('Alice Project');
        $this->get('/admin/sections')->assertOk();
    }

    public function test_switching_lands_on_the_new_portfolios_page_not_the_old_one(): void
    {
        $this->admin();
        $project = \App\Models\Project::withoutGlobalScopes()->create(['portfolio_id' => $this->alice->id, 'name' => 'A', 'slug' => 'a', 'status' => 'published']);
        $cases = [
            "/admin/portfolios/{$this->alice->id}/edit" => "/admin/portfolios/{$this->bob->id}/edit", // settings page follows the switch
            "/admin/projects/{$project->id}/edit" => '/admin/projects', // a record of the old portfolio → its list
            '/admin/profile' => '/admin/profile', // "current portfolio" screens just reload
            '/admin/projects' => '/admin/projects',
            '/alice' => '/admin', // not an admin page
        ];
        foreach ($cases as $from => $to) {
            $this->from($from)->post("/admin/portfolios/{$this->bob->id}/switch")->assertRedirect($to);
        }
        $this->get("/admin/portfolios/{$this->bob->id}/edit")->assertSee('autocomplete="off"', false);
    }

    public function test_non_admins_get_403_and_guests_are_sent_to_login(): void
    {
        $id = $this->alice->id;
        $routes = [
            ['get', '/admin/portfolios'], ['get', '/admin/portfolios/create'], ['post', '/admin/portfolios'],
            ['get', "/admin/portfolios/{$id}/edit"], ['put', "/admin/portfolios/{$id}"], ['post', "/admin/portfolios/{$id}/status"],
            ['post', "/admin/portfolios/{$id}/template"], ['post', "/admin/portfolios/{$id}/template/publish"],
            ['delete', "/admin/portfolios/{$id}"], ['post', "/admin/portfolios/{$id}/switch"],
        ];

        foreach ($routes as [$method, $url]) {
            $this->{$method}($url, ['confirm_username' => 'alice', 'status' => 'archived'])->assertRedirect('/admin/login');
        }

        // A user who owns nothing gets nothing.
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url, ['confirm_username' => 'alice', 'status' => 'archived'])->assertForbidden();
        }

        // The owner edits their own portfolio, but listing, creating, status and deleting stay admin-only.
        $owner = User::factory()->create(['is_admin' => false]);
        $this->alice->update(['user_id' => $owner->id]);
        $this->actingAs($owner);
        foreach ([['get', '/admin/portfolios'], ['get', '/admin/portfolios/create'], ['post', '/admin/portfolios'],
            ['post', "/admin/portfolios/{$id}/status"], ['delete', "/admin/portfolios/{$id}"], ['post', "/admin/portfolios/{$id}/owner"]] as [$method, $url]) {
            $this->{$method}($url, ['confirm_username' => 'alice', 'status' => 'archived'])->assertForbidden();
        }
        $this->get("/admin/portfolios/{$id}/edit")->assertOk()->assertDontSee('name="status"', false)->assertDontSee('Danger zone');
        // Status/username in the update are ignored for owners.
        $this->put("/admin/portfolios/{$id}", ['name' => 'Alice A.', 'status' => 'archived', 'username' => 'hijack'])->assertSessionHasNoErrors();
        $this->assertSame(['Alice A.', 'published', 'alice'], [$this->alice->fresh()->name, $this->alice->fresh()->status, $this->alice->fresh()->username]);
    }

    public function test_first_portfolio_can_be_created_from_an_empty_install(): void
    {
        Portfolio::query()->delete();
        $this->admin();
        $this->get('/admin/projects')->assertRedirect('/admin/portfolios/create');
        $this->get('/admin/portfolios/create')->assertOk();
        $this->get('/admin/portfolios')->assertOk()->assertSee('No portfolios found');
        $this->post('/admin/portfolios', $this->payload())->assertRedirect();
        $this->get('/admin/projects')->assertOk();
    }
}
