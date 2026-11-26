<?php

namespace Tests\Feature;

use App\Admin\Resources\ProjectResource;
use App\Models\Portfolio;
use App\Models\Project;
use App\Models\Section;
use App\Models\User;
use App\Support\CurrentPortfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Changing a portfolio's type never loses content; templates offered follow the type. */
class TypeChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_changes_type_and_back_without_losing_anything(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $portfolio = $this->portfolio(['user_id' => $owner->id]);
        $project = Project::create(['name' => 'API', 'slug' => 'api', 'status' => 'published', 'architecture' => 'Hexagonal', 'repo_url' => 'https://github.com/a/b']);
        Section::where('key', 'skills')->update(['title' => 'My own skills title']);
        $this->actingAs($owner);

        $this->post("/admin/portfolios/{$portfolio->id}/type", ['type' => 'education', 'relabel' => '1'])->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Nothing was removed') && str_contains($m, 'Philosophy') && str_contains($m, 'Publications')
                && str_contains($m, 'not one recommended')); // classic isn't an Education template

        $portfolio->refresh();
        $this->assertSame('education', $portfolio->type);
        CurrentPortfolio::using($portfolio, function () use ($project) {
            $this->assertSame(['Hexagonal', 'https://github.com/a/b'], [$project->fresh()->architecture, $project->fresh()->repo_url]); // kept, just not in the form
            $this->assertArrayNotHasKey('architecture', ProjectResource::fields());
            $this->assertSame(['Teaching', 'Expertise'], [Section::where('key', 'projects')->value('nav_label'), Section::where('key', 'skills')->value('nav_label')]);
            $this->assertSame(['home', 'experience', 'projects', 'skills', 'education', 'contact', 'blog', 'notes', 'philosophy', 'publications'],
                Section::ordered()->pluck('key')->all()); // added at the end, nothing removed or reordered
            $this->assertTrue(Section::where('key', 'blog')->value('is_visible')); // nothing hidden either
        });

        // Back to technical: the technical fields show their old values again.
        $this->post("/admin/portfolios/{$portfolio->id}/type", ['type' => 'technical'])->assertSessionHasNoErrors();
        CurrentPortfolio::using($portfolio->fresh(), fn () => $this->assertSame('Hexagonal', ProjectResource::formValues($project->fresh())['architecture']));
        $this->assertSame('Teaching', CurrentPortfolio::using($portfolio, fn () => Section::where('key', 'projects')->value('nav_label'))); // not relabelled without the box
    }

    public function test_type_change_validation_and_authorisation(): void
    {
        $owner = User::factory()->create(['is_admin' => false]);
        $mine = $this->portfolio(['user_id' => $owner->id]);
        $other = $this->portfolio(['username' => 'someone', 'name' => 'Someone']);
        $this->actingAs($owner);

        $this->post("/admin/portfolios/{$other->id}/type", ['type' => 'creative'])->assertForbidden();
        $this->post("/admin/portfolios/{$mine->id}/type", ['type' => 'nope'])->assertSessionHasErrors('type');
        $this->post("/admin/portfolios/{$mine->id}/type", ['type' => 'hybrid', 'disciplines' => ['creative']])->assertSessionHasErrors('disciplines');
        $this->post("/admin/portfolios/{$mine->id}/type", ['type' => 'hybrid', 'disciplines' => ['hybrid', 'creative']])->assertSessionHasErrors('disciplines.0');
        $this->post("/admin/portfolios/{$mine->id}/type", ['type' => 'hybrid', 'disciplines' => ['education', 'creative']])->assertSessionHasNoErrors();
        $this->assertSame(['hybrid', ['creative', 'education']], [$mine->fresh()->type, $mine->fresh()->disciplines]);

        // Back to a single type: disciplines are cleared.
        $this->post("/admin/portfolios/{$mine->id}/type", ['type' => 'general', 'disciplines' => ['education', 'creative']])->assertSessionHasNoErrors();
        $this->assertSame(['general', null], [$mine->fresh()->type, $mine->fresh()->disciplines]);
    }

    public function test_templates_offered_follow_the_type_but_the_current_one_stays(): void
    {
        $portfolio = $this->portfolio(['type' => 'management', 'template' => 'picto']); // e.g. chosen before the type existed
        $this->admin();

        $this->get("/admin/portfolios/{$portfolio->id}/edit")->assertOk()
            ->assertSee('Professional')->assertSee('Picto Portfolio') // current one is kept
            ->assertDontSee('templates/gradient/preview.svg', false)->assertDontSee('templates/classic/preview.svg', false);
        $this->post("/admin/portfolios/{$portfolio->id}/template", ['template' => 'gradient'])->assertSessionHasErrors('template');
        $this->post("/admin/portfolios/{$portfolio->id}/template", ['template' => 'professional'])->assertSessionHasNoErrors();
        $this->assertSame('professional', $portfolio->fresh()->draft_template);
    }

    public function test_admin_creates_a_portfolio_of_a_type(): void
    {
        $this->admin();
        $base = ['name' => 'Carol', 'username' => 'carol', 'status' => 'draft', 'type' => 'creative'];
        $this->get('/admin/portfolios/create')->assertOk()->assertSee('Portfolio type')->assertSee('Creative &amp; Design', false);
        $this->post('/admin/portfolios', $base + ['template' => 'classic'])->assertSessionHasErrors('template');
        $this->post('/admin/portfolios', $base + ['template' => 'picto'])->assertSessionHasNoErrors();

        $carol = Portfolio::where('username', 'carol')->sole();
        $this->assertSame('creative', $carol->type);
        CurrentPortfolio::using($carol, fn () => $this->assertSame('Work', Section::where('key', 'projects')->value('nav_label')));
    }
}
