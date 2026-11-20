<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\PortfolioType;
use App\Models\Project;
use App\Support\CurrentPortfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** /create: portfolio type first, then a form made for that type; preview and samples through the session-only demo. */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private function submit(array $overrides): \Illuminate\Testing\TestResponse
    {
        return $this->post('/create', $overrides + ['username' => 'new-person', 'full_name' => 'New Person', 'email' => 'new@example.test',
            'password' => 'secret-pass', 'password_confirmation' => 'secret-pass']);
    }

    public function test_type_selection_comes_first(): void
    {
        $page = $this->get('/create')->assertOk()->assertSee('What kind of portfolio are you creating?')->assertDontSee('name="username"', false);
        foreach (PortfolioType::all() as $type) {
            $page->assertSee('value="'.$type->slug.'"', false)->assertSee($type->name)->assertSee(route('demo.sample', $type));
        }
        $page->assertSee('Photographers')->assertSee('aria-current="step"', false)->assertSee('name="disciplines[]"', false);

        // A type that doesn't exist or is switched off sends the visitor back to choose.
        PortfolioType::where('slug', 'business')->update(['is_active' => false]);
        $this->get('/create?type=business')->assertRedirect('/create')->assertSessionHasErrors('type');
        $this->get('/create')->assertDontSee('value="business"', false);
    }

    public function test_form_is_made_for_the_chosen_type(): void
    {
        $creative = $this->get('/create?type=creative')->assertOk()
            ->assertSee('Create your Creative &amp; Design portfolio', false)
            ->assertSee('name="type" value="creative"', false)
            ->assertSee('name="projects[0][client]"', false)->assertSee('name="projects[0][process]"', false)->assertSee('Tools used')
            ->assertDontSee('name="projects[0][repo_url]"', false)->assertDontSee('name="projects[0][architecture]"', false)
            ->assertSee('value="picto"', false)->assertDontSee('value="classic"', false) // compatible templates only
            ->assertSee(route('apply.template', ['type' => 'creative']), false);

        $this->get('/create?type=technical')->assertSee('name="projects[0][repo_url]"', false)->assertSee('name="projects[0][architecture]"', false);
        $this->get('/create/template?type=education')->assertSee('Portfolio type: Education & Training', false)->assertSee('Learning objectives:')->assertDontSee('Repository URL:');
    }

    public function test_hybrid_needs_two_disciplines(): void
    {
        $this->get('/create?type=hybrid&disciplines[]=technical')->assertRedirect('/create?step=type&type=hybrid')->assertSessionHasErrors('disciplines');
        $this->get('/create?type=hybrid&disciplines[]=technical&disciplines[]=creative')->assertOk()
            ->assertSee('Technical &amp; IT + Creative &amp; Design', false)
            ->assertSee('name="projects[0][disciplines][]"', false)->assertSee('name="projects[0][architecture]"', false)->assertSee('name="projects[0][process]"', false);
    }

    public function test_non_technical_submission_stores_type_and_profession_fields(): void
    {
        $this->submit(['type' => 'business', 'template' => 'classic'])->assertSessionHasErrors('template'); // not compatible with Business

        $this->submit(['type' => 'business', 'template' => 'professional', 'projects' => [[
            'name' => 'Spring sale', 'industry' => 'Retail', 'strategy' => 'Email first.', 'metrics' => "Revenue: +30%\nROAS: 4x", 'budget' => '5000',
            'repo_url' => 'https://github.com/x/y', // not a Business field: ignored
        ]]])->assertSessionHasNoErrors()->assertRedirect(route('apply.submitted'));

        $p = Portfolio::where('username', 'new-person')->sole();
        $this->assertSame(['business', null, 'professional', 'pending'], [$p->type, $p->disciplines, $p->template, $p->status]);
        CurrentPortfolio::using($p, function () {
            $project = Project::sole();
            $this->assertSame(['Retail', 'Email first.', ['Revenue: +30%', 'ROAS: 4x'], '5000', null], [$project->industry, $project->strategy, $project->metrics, $project->budget, $project->repo_url]);
            $this->assertSame('Campaigns', \App\Models\Section::where('key', 'projects')->value('nav_label'));
        });
    }

    public function test_hybrid_submission_keeps_disciplines_on_portfolio_and_projects(): void
    {
        $this->submit(['type' => 'hybrid', 'disciplines' => ['education', 'technical'], 'template' => 'professional',
            'projects' => [['name' => 'Course site', 'disciplines' => ['technical', 'education'], 'subject' => 'Physics', 'architecture' => 'Static site']]])
            ->assertSessionHasNoErrors();
        $p = Portfolio::where('username', 'new-person')->sole();
        $this->assertSame(['technical', 'education'], $p->disciplines); // stored in the platform's order
        CurrentPortfolio::using($p, fn () => $this->assertSame([['technical', 'education'], 'Physics', 'Static site'],
            [Project::sole()->disciplines, Project::sole()->subject, Project::sole()->architecture]));

        $this->submit(['type' => 'hybrid', 'disciplines' => ['technical'], 'username' => 'other-one', 'email' => 'o@example.test'])->assertSessionHasErrors('disciplines');
    }

    public function test_preview_renders_the_form_with_the_demo_and_saves_nothing(): void
    {
        $this->post('/create/preview', ['type' => 'education', 'template' => 'professional', 'full_name' => 'Ms Teacher', 'about' => 'I teach.',
            'skills' => [['name' => 'Subjects', 'skills' => 'Physics, Maths']],
            'projects' => [['name' => 'Physics 101', 'subject' => 'Physics', 'budget' => 'secret-budget', 'outcome' => 'Everyone passed.']]])
            ->assertRedirect(route('demo.show'));

        $this->assertSame(0, Portfolio::count());
        $this->get('/demo')->assertOk()->assertSee('Ms Teacher')->assertSee('Physics 101')->assertSee('Teaching')->assertDontSee('secret-budget');
        $this->get('/demo/projects/physics-101')->assertOk()->assertDontSee('secret-budget');
    }

    public function test_every_type_has_a_sample_portfolio(): void
    {
        foreach (PortfolioType::all() as $type) {
            $this->get(route('demo.sample', $type))->assertRedirect(route('demo.show'));
            $this->get('/demo')->assertOk()->assertSee(e($type->sample()['name']), false)->assertSee(e($type->sample()['projects'][0]['name']), false);
        }
        $this->get('/demo/sample/nope')->assertNotFound();
    }
}
