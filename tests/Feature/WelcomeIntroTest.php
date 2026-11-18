<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Support\Templates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** First-visit "welcome in several languages" intro: on every template, configured once per portfolio. */
class WelcomeIntroTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_template_shows_the_intro_on_the_home_page_only(): void
    {
        $portfolio = $this->portfolio();
        Project::create(['name' => 'Rocket', 'slug' => 'rocket', 'status' => 'published']);

        foreach (array_keys(Templates::available()) as $key) {
            $portfolio->update(['template' => $key]);
            $home = $this->get('/test-person')->assertOk();
            if ($key === 'classic') {
                $home->assertSee('seenIntro:test-person', false)->assertSee('data-words="'.e(json_encode(config('portfolio.intro_words'))).'"', false);
            } else {
                $home->assertSee('id="pf-intro"', false)->assertSee('"seenIntro:test-person"', false)->assertSee(json_encode('नमस्ते'), false);
                $this->get('/test-person/projects/rocket')->assertOk()->assertDontSee('pf-intro');
            }
        }
    }

    public function test_settings_are_shared_by_all_templates_and_can_turn_it_off(): void
    {
        $portfolio = $this->portfolio(['template' => 'gradient']);
        $this->admin();
        $base = ['name' => 'Test Person', 'username' => 'test-person', 'status' => 'published', 'settings_template' => 'gradient'];

        $this->get("/admin/portfolios/{$portfolio->id}/edit")->assertSee('settings[intro]', false)->assertSee('settings[intro_words]', false);
        $this->put("/admin/portfolios/{$portfolio->id}", $base + ['settings' => ['gradient' => 'teal_blue', 'intro' => '1', 'intro_words' => "Hola\n\n  Namaste  \n</script><b>x"]])
            ->assertSessionHasNoErrors();

        $saved = $portfolio->fresh()->template_settings;
        $this->assertSame(['intro' => true, 'intro_words' => "Hola\n\n  Namaste  \n</script><b>x"], $saved['_shared']);
        $this->assertEquals(['gradient' => 'teal_blue', 'shapes' => false], $saved['gradient']);
        // Another template sees the same intro settings.
        $this->assertSame(['Hola', 'Namaste', '</script><b>x'], Templates::introWords($portfolio->fresh()->settingsFor('professional')));

        auth()->logout();
        // Words go into the inline script hex-escaped, so "</script>" can't end it early.
        $this->get('/test-person')->assertSee(json_encode(['Hola', 'Namaste', '</script><b>x'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT), false)
            ->assertDontSee('</script><b>x', false);

        $portfolio->update(['template' => 'classic']);
        $this->get('/test-person')->assertSee(e(json_encode(['Hola', 'Namaste', '</script><b>x'])), false);

        // Off: no overlay on either kind of template.
        $portfolio->update(['template_settings' => ['_shared' => ['intro' => false]]]);
        $this->get('/test-person')->assertDontSee("classList.add('intro')", false);
        $portfolio->update(['template' => 'picto']);
        $this->get('/test-person')->assertDontSee('pf-intro');
    }

    public function test_demo_never_plays_the_intro(): void
    {
        $this->withSession(['demo' => ['name' => 'Demo Person', 'template' => 'professional', 'projects' => []]])->get('/demo')->assertOk()->assertDontSee('pf-intro');
        $this->withSession(['demo' => ['name' => 'Demo Person', 'template' => 'classic', 'projects' => []]])->get('/demo')->assertOk()->assertDontSee("classList.add('intro')", false);
    }
}
