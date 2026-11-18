<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_have_the_dark_mode_switch_and_apply_the_saved_theme_early(): void
    {
        $this->portfolio();
        $init = "localStorage.getItem('admin-theme')";

        $this->actingAs($this->admin())->get('/admin')->assertOk()
            ->assertSee('data-theme-toggle', false)->assertSee($init, false);
        auth()->logout();
        $this->get('/admin/login')->assertOk()->assertSee($init, false);
    }
}
