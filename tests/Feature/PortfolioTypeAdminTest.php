<?php

namespace Tests\Feature;

use App\Models\Portfolio;
use App\Models\PortfolioType;
use App\Models\Section;
use App\Models\User;
use App\Support\CurrentPortfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Admin → Portfolio types: manage types, modules, sections, template compatibility; filter and badge portfolios by type. */
class PortfolioTypeAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_edits_a_type_and_it_changes_what_new_portfolios_get(): void
    {
        $this->admin();
        $this->get('/admin/portfolio-types')->assertOk()->assertSee('Template compatibility')->assertSee('Campaigns &amp; results', false);
        $this->get('/admin/portfolio-types/creative/edit')->assertOk()->assertSee('Photographers');

        $this->put('/admin/portfolio-types/creative', ['name' => 'Creative & Design', 'icon' => '🖌️', 'categories' => "Photographers\n\nTattoo artists",
            'modules' => ['creative', 'business'], 'sections' => ['projects', 'contact', 'services'], 'templates' => ['gradient', 'picto'], 'is_active' => '1'])
            ->assertSessionHasNoErrors();
        $type = PortfolioType::where('slug', 'creative')->sole();
        $this->assertSame([['Photographers', 'Tattoo artists'], ['creative', 'business'], ['home', 'projects', 'contact', 'services'], ['picto', 'gradient']],
            [$type->categories, $type->modules, $type->sections, $type->templates]); // existing order kept, home always first

        $this->get('/create')->assertSee('Tattoo artists');
        $this->get('/create?type=creative')->assertSee('name="projects[0][metrics]"', false)->assertDontSee('value="minimal_editorial"', false);

        $p = $this->portfolio(['type' => 'creative', 'username' => 'new-one']);
        CurrentPortfolio::using($p, fn () => $this->assertSame(['home', 'projects', 'contact', 'services'], Section::where('is_visible', true)->ordered()->pluck('key')->all()));
    }

    public function test_type_validation_creation_switching_off_and_deleting(): void
    {
        $this->admin();
        $base = ['name' => 'Healthcare', 'modules' => ['general'], 'sections' => ['home'], 'templates' => ['professional'], 'is_active' => '1'];
        $this->post('/admin/portfolio-types', ['slug' => 'Bad Slug'] + $base)->assertSessionHasErrors('slug');
        $this->post('/admin/portfolio-types', ['slug' => 'technical'] + $base)->assertSessionHasErrors('slug');
        $this->post('/admin/portfolio-types', ['slug' => 'health', 'modules' => ['nope']] + $base)->assertSessionHasErrors('modules.0');
        $this->post('/admin/portfolio-types', ['slug' => 'health', 'templates' => []] + $base)->assertSessionHasErrors('templates');
        $this->post('/admin/portfolio-types', ['slug' => 'health'] + $base)->assertSessionHasNoErrors();
        $this->get('/create')->assertSee('value="health"', false);

        // Switched off: no longer offered, but its portfolios keep working.
        $p = $this->portfolio(['type' => 'health', 'username' => 'nurse', 'template' => 'professional']);
        $this->put('/admin/portfolio-types/health', ['is_active' => '0'] + $base)->assertSessionHasNoErrors();
        $this->get('/create')->assertDontSee('value="health"', false);
        $this->get('/nurse')->assertOk();

        $this->delete('/admin/portfolio-types/health')->assertSessionHasErrors('type'); // in use
        $p->update(['type' => 'general']);
        $this->delete('/admin/portfolio-types/health')->assertRedirect('/admin/portfolio-types');
        $this->delete('/admin/portfolio-types/technical')->assertSessionHasErrors('type'); // built in
    }

    public function test_compatibility_matrix(): void
    {
        $this->admin();
        $compat = PortfolioType::all()->mapWithKeys(fn ($t) => [$t->slug => $t->templates])->all();
        $compat['technical'] = [...$compat['technical'], 'content_journal', 'picto'];
        $this->put('/admin/portfolio-types/compatibility', ['compat' => $compat])->assertSessionHasNoErrors();
        $this->assertContains('picto', PortfolioType::where('slug', 'technical')->value('templates'));
        $this->assertSame('classic', PortfolioType::where('slug', 'technical')->sole()->templates[0]); // order kept

        $compat['business'] = [];
        $this->put('/admin/portfolio-types/compatibility', ['compat' => $compat])->assertSessionHasErrors();
        $this->assertNotEmpty(PortfolioType::where('slug', 'business')->sole()->templates); // nothing half-saved
    }

    public function test_portfolio_list_filters_and_badges_by_type(): void
    {
        $this->portfolio(['name' => 'Dev', 'username' => 'dev']);
        $this->portfolio(['name' => 'Teacher', 'username' => 'teacher', 'type' => 'education']);
        $this->portfolio(['name' => 'Mixed', 'username' => 'mixed', 'type' => 'hybrid', 'disciplines' => ['education', 'creative']]);
        $this->admin();

        $this->get('/admin/portfolios')->assertSee('Technical &amp; IT', false)->assertSee('Education &amp; Training + Creative &amp; Design', false);
        $this->get('/admin/portfolios?type=education')->assertSee('/teacher')->assertSee('/mixed')->assertDontSee('>/dev ↗', false);
    }

    public function test_owners_cannot_manage_types(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        $this->portfolio(['user_id' => auth()->id()]);
        foreach (['/admin/portfolio-types', '/admin/portfolio-types/creative/edit'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->put('/admin/portfolio-types/creative', ['name' => 'x'])->assertForbidden();
    }
}
