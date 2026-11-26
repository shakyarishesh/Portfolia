<?php

namespace Tests\Feature;

use App\Support\Templates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cursor effect: a shared setting on every template (glowing trail, Classic's neon ring, or off). */
class CursorEffectTest extends TestCase
{
    use RefreshDatabase;

    public function test_trail_can_be_switched_on_and_off_on_every_template(): void
    {
        $portfolio = $this->portfolio();

        foreach (array_keys(Templates::available()) as $key) {
            $portfolio->update(['template' => $key, 'template_settings' => ['_shared' => ['cursor' => 'trail', 'cursor_color' => 'pink']]]);
            $this->get('/test-person')->assertOk()->assertSee('id="pf-cursor-trail"', false)->assertSee('"244, 114, 182"', false)
                ->assertDontSee('class="cursor-ring"', false);

            $portfolio->update(['template_settings' => ['_shared' => ['cursor' => 'none']]]);
            $this->get('/test-person')->assertOk()->assertDontSee('pf-cursor-trail')->assertDontSee('class="cursor-ring"', false);
        }
    }

    public function test_defaults_and_classic_ring(): void
    {
        $portfolio = $this->portfolio();
        // Classic and Creative Experience start with the trail; the calmer templates start with none.
        $this->assertSame('trail', $portfolio->settingsFor('classic')['cursor']);
        $this->assertSame('trail', $portfolio->settingsFor('creative_experience')['cursor']);
        $this->assertSame('none', $portfolio->settingsFor('modern_personal')['cursor']);
        $this->get('/test-person')->assertSee('id="pf-cursor-trail"', false);

        // The old neon ring & dot only exists on Classic; elsewhere it means no effect.
        $portfolio->update(['template_settings' => ['_shared' => ['cursor' => 'ring']]]);
        $this->get('/test-person')->assertSee('class="cursor-ring"', false)->assertDontSee('pf-cursor-trail');
        $portfolio->update(['template' => 'gradient']);
        $this->get('/test-person')->assertDontSee('cursor-ring')->assertDontSee('pf-cursor-trail');

        // Unknown values fall back to the template's default.
        $portfolio->update(['template' => 'classic', 'template_settings' => ['_shared' => ['cursor' => '<script>', 'cursor_color' => 'x']]]);
        $this->assertSame(['trail', 'electric'], [$portfolio->fresh()->settingsFor('classic')['cursor'], $portfolio->fresh()->settingsFor('classic')['cursor_color']]);
    }

    public function test_admin_can_set_it(): void
    {
        $portfolio = $this->portfolio(['template' => 'professional']);
        $this->admin();
        $this->get("/admin/portfolios/{$portfolio->id}/edit")->assertSee('Cursor effect')->assertSee('Glowing trail');
        $this->put("/admin/portfolios/{$portfolio->id}", ['name' => 'Test Person', 'username' => 'test-person', 'status' => 'published',
            'settings_template' => 'professional', 'settings' => ['cursor' => 'trail', 'cursor_color' => 'violet']])->assertSessionHasNoErrors();
        $this->assertSame(['trail', 'violet'], [$portfolio->fresh()->settingsFor('professional')['cursor'], $portfolio->fresh()->settingsFor('professional')['cursor_color']]);
        $this->put("/admin/portfolios/{$portfolio->id}", ['name' => 'Test Person', 'username' => 'test-person', 'status' => 'published',
            'settings_template' => 'professional', 'settings' => ['cursor' => 'sparkles']])->assertSessionHasErrors('settings.cursor');
    }
}
