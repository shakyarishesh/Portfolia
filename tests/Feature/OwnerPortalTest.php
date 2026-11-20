<?php

namespace Tests\Feature;

use App\Http\Middleware\ResolveAdminPortfolio;
use App\Models\Portfolio;
use App\Models\Profile;
use App\Models\Project;
use App\Models\User;
use App\Support\CurrentPortfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Portfolio owners sign in to the same portal as admins but only ever see and edit their own portfolio. */
class OwnerPortalTest extends TestCase
{
    use RefreshDatabase;

    private Portfolio $mine;

    private Portfolio $other;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->other = $this->portfolio(['name' => 'Other', 'username' => 'other']);
        Project::create(['name' => 'Other Secret', 'slug' => 'other-secret', 'status' => 'published']);
        $this->owner = User::factory()->create(['email' => 'owner@example.test', 'phone' => '+9779811111111', 'password' => 'owner-pass-1', 'is_admin' => false]);
        $this->mine = $this->portfolio(['name' => 'Mine', 'username' => 'mine', 'user_id' => $this->owner->id]);
        Project::create(['name' => 'My Project', 'slug' => 'my-project', 'status' => 'published']);
        CurrentPortfolio::set(null);
    }

    public function test_owner_signs_in_with_email_username_or_phone(): void
    {
        foreach (['owner@example.test', 'MINE', '+977 981-111 1111'] as $login) {
            $this->post('/admin/login', ['login' => $login, 'password' => 'owner-pass-1'])->assertRedirect(route('admin.dashboard'));
            $this->assertAuthenticatedAs($this->owner);
            auth()->logout();
        }
        $this->post('/admin/login', ['login' => 'mine', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->post('/admin/login', ['login' => 'other', 'password' => 'owner-pass-1'])->assertSessionHasErrors('login'); // no owner
        $this->assertGuest();
    }

    public function test_owner_sees_and_edits_only_their_own_portfolio(): void
    {
        $this->actingAs($this->owner);

        // Even with another portfolio's id in the session, the owner gets their own.
        $this->withSession([ResolveAdminPortfolio::SESSION_KEY => $this->other->id])->get('/admin')->assertOk()
            ->assertSee('My portfolio')->assertSee('mine')->assertDontSee('(other)')
            ->assertDontSee('/admin/applications')->assertDontSee('/admin/imports')->assertDontSee('/admin/proposals');
        $this->get('/admin/projects')->assertOk()->assertSee('My Project')->assertDontSee('Other Secret');
        $otherProject = Project::withoutGlobalScopes()->where('slug', 'other-secret')->value('id');
        $this->get("/admin/projects/{$otherProject}/edit")->assertNotFound();

        $this->put('/admin/profile', ['full_name' => 'Mine Updated', 'headline' => 'Engineer'])->assertSessionHasNoErrors();
        CurrentPortfolio::using($this->mine, fn () => $this->assertSame('Mine Updated', Profile::query()->value('full_name')));
        CurrentPortfolio::using($this->other, fn () => $this->assertSame('Test Person', Profile::query()->value('full_name')));

        // Other portfolios and admin-only areas are off limits.
        $this->post("/admin/portfolios/{$this->other->id}/switch")->assertForbidden();
        $this->get("/admin/portfolios/{$this->other->id}/edit")->assertForbidden();
        foreach (['/admin/portfolios', '/admin/applications', '/admin/imports', '/admin/proposals'] as $url) {
            $this->get($url)->assertForbidden();
        }

        // Template and SEO of their own portfolio are theirs to change.
        $this->post("/admin/portfolios/{$this->mine->id}/template", ['template' => 'gradient'])->assertSessionHasNoErrors();
        $this->post("/admin/portfolios/{$this->mine->id}/template/publish")->assertSessionHasNoErrors();
        $this->assertSame('gradient', $this->mine->fresh()->template);
    }

    public function test_admin_creates_a_login_for_an_existing_portfolio_and_resets_its_password(): void
    {
        $this->admin();
        $this->get("/admin/portfolios/{$this->other->id}/edit")->assertOk()->assertSee('Owner login')->assertSee('Create login');

        $this->post("/admin/portfolios/{$this->other->id}/owner", ['owner_name' => 'Olive', 'owner_email' => 'owner@example.test',
            'password' => 'olive-pass-1', 'password_confirmation' => 'olive-pass-1'])->assertSessionHasErrors('owner_email'); // taken
        $this->post("/admin/portfolios/{$this->other->id}/owner", ['owner_name' => 'Olive', 'owner_email' => 'olive@example.test', 'owner_phone' => '01-4455',
            'password' => 'olive-pass-1', 'password_confirmation' => 'olive-pass-1'])->assertSessionHasNoErrors();
        $olive = User::where('email', 'olive@example.test')->sole();
        $this->assertSame([$olive->id, false, '014455'], [$this->other->fresh()->user_id, $olive->is_admin, $olive->phone]);

        $this->get("/admin/portfolios/{$this->other->id}/edit")->assertSee('Set new password');
        $this->post("/admin/portfolios/{$this->other->id}/owner", ['password' => 'olive-pass-2', 'password_confirmation' => 'olive-pass-2'])->assertSessionHasNoErrors();

        auth()->logout();
        $this->post('/admin/login', ['login' => 'other', 'password' => 'olive-pass-2'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($olive);
    }

    public function test_applicant_gets_a_login_and_can_keep_editing_while_pending(): void
    {
        $this->post('/create', ['type' => 'technical', 'username' => 'newbie', 'full_name' => 'New Bie', 'email' => 'owner@example.test',
            'password' => 'newbie-pass', 'password_confirmation' => 'newbie-pass'])->assertSessionHasErrors('email'); // email already has an account
        $this->post('/create', ['type' => 'technical', 'username' => 'newbie', 'full_name' => 'New Bie', 'email' => 'newbie@example.test',
            'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');

        $this->post('/create', ['type' => 'technical', 'username' => 'newbie', 'full_name' => 'New Bie', 'email' => 'newbie@example.test',
            'password' => 'newbie-pass', 'password_confirmation' => 'newbie-pass'])->assertRedirect(route('apply.submitted'));
        $this->get(route('apply.submitted'))->assertSee('Open my dashboard');
        $this->get('/admin')->assertOk()->assertSee('Pending approval');
        $this->put('/admin/profile', ['full_name' => 'New Bie', 'headline' => 'Added later'])->assertSessionHasNoErrors();
        CurrentPortfolio::using(Portfolio::where('username', 'newbie')->sole(), fn () => $this->assertSame('Added later', Profile::query()->value('headline')));
    }
}
