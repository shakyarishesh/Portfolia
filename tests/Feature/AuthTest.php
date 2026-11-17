<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/projects')->assertRedirect('/admin/login');
        $this->post('/admin/skills', [])->assertRedirect('/admin/login');
    }

    public function test_admin_can_log_in_and_out(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($user);

        $this->post('/admin/logout')->assertRedirect('/admin/login');
        $this->assertGuest();
    }

    public function test_wrong_password_and_non_admins_are_rejected(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_login_is_rate_limited(): void
    {
        $user = User::factory()->create(['is_admin' => true]);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(429);
    }

    public function test_admin_pages_are_not_cached_or_indexed(): void
    {
        $this->admin();
        \App\Models\Profile::create(['full_name' => 'A', 'headline' => 'B']);

        $this->get('/admin')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
