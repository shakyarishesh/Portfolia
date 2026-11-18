<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeSwitchTest extends TestCase
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

    public function test_public_platform_pages_have_the_switch_and_the_platform_name(): void
    {
        config(['portfolio.platform_name' => 'Acme Folio', 'app.name' => 'Owner Person']);

        foreach (['/', '/create'] as $url) {
            $this->get($url)->assertOk()->assertSee('data-theme-toggle', false)
                ->assertSee("localStorage.getItem('site-theme')", false)
                ->assertSee('Acme Folio')->assertDontSee('Owner Person');
        }
        $this->assertStringStartsWith('# Acme Folio', $this->get('/create/template')->getContent());
    }
}
