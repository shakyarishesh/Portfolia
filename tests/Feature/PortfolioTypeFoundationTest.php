<?php

namespace Tests\Feature;

use App\Admin\Resources\ProjectResource;
use App\Models\Portfolio;
use App\Models\PortfolioType;
use App\Models\Project;
use App\Models\Section;
use App\Support\CurrentPortfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Portfolio types: seeded defaults, technical backward compatibility, modules, sections and profession-specific fields. */
class PortfolioTypeFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_seven_types_are_seeded_and_existing_portfolios_are_technical(): void
    {
        $this->assertSame(['technical', 'creative', 'business', 'management', 'education', 'general', 'hybrid'],
            PortfolioType::ordered()->pluck('slug')->all());

        $portfolio = $this->portfolio();
        $this->assertSame('technical', $portfolio->fresh()->type);
        $this->assertSame(['technical'], $portfolio->moduleKeys());
    }

    public function test_technical_project_form_is_unchanged_apart_from_outcomes(): void
    {
        $this->portfolio();
        $this->assertSame(['name', 'slug', 'tagline', 'category', 'description', 'problem', 'features', 'architecture', 'challenges', 'diagram',
            'outcome', 'role', 'experience_id', 'start_date', 'end_date', 'is_current', 'live_url', 'repo_url', 'technologies',
            'meta_description', 'is_featured', 'status', 'source_note'], array_keys(ProjectResource::fields()));
        $this->assertSame('Problem & objectives', ProjectResource::fields()['problem']['label']);
    }

    public function test_technical_portfolio_starts_with_the_original_sections(): void
    {
        $this->portfolio();
        $this->assertSame(array_keys(Portfolio::DEFAULT_SECTIONS), Section::ordered()->pluck('key')->all());
        $this->assertTrue(Section::query()->get()->every->is_visible);
    }

    public function test_each_type_gets_its_own_project_fields_without_technical_ones(): void
    {
        $expect = [
            'creative' => [['client', 'process', 'video_url'], 'Tools used'],
            'business' => [['industry', 'audience', 'strategy', 'execution', 'metrics', 'budget'], 'Tools and platforms'],
            'management' => [['team_size', 'responsibilities', 'impact'], 'Tools and methodologies'],
            'education' => [['subject', 'audience', 'methodology', 'materials'], 'Platforms and tools'],
            'general' => [['client', 'outcome'], 'Skills and tools used'],
        ];
        foreach ($expect as $type => [$has, $toolsLabel]) {
            $fields = ProjectResource::fieldsFor(Portfolio::modulesFor($type));
            foreach ($has as $name) {
                $this->assertArrayHasKey($name, $fields, "{$type} has {$name}");
            }
            foreach (['architecture', 'diagram', 'repo_url'] as $tech) {
                $this->assertArrayNotHasKey($tech, $fields, "{$type} has no {$tech}");
            }
            $this->assertSame($toolsLabel, $fields['technologies']['label']);
        }
    }

    public function test_new_portfolio_of_a_type_gets_its_sections_and_labels_and_hidden_built_ins(): void
    {
        $portfolio = $this->portfolio(['type' => 'education']);
        $sections = Section::ordered()->get()->keyBy('key');

        $this->assertSame(['home', 'philosophy', 'experience', 'projects', 'publications', 'education', 'skills', 'contact', 'blog', 'notes'], $sections->keys()->all());
        $this->assertSame('Teaching', $sections['projects']->nav_label);
        $this->assertSame('How I teach', $sections['philosophy']->title);
        $this->assertFalse($sections['blog']->is_visible); // not part of the type, but can be switched on
        $this->assertTrue($sections['philosophy']->is_visible);
        $this->assertSame(['education'], $portfolio->moduleKeys());
    }

    public function test_hybrid_combines_modules_without_duplicate_fields(): void
    {
        $portfolio = $this->portfolio(['type' => 'hybrid', 'disciplines' => ['technical', 'creative']]);

        $this->assertSame(['technical', 'creative'], $portfolio->moduleKeys());
        $fields = ProjectResource::fields();
        $this->assertSame(['technical' => 'Technical & IT', 'creative' => 'Creative & Design'], $fields['disciplines']['options']);
        $this->assertArrayHasKey('architecture', $fields);
        $this->assertArrayHasKey('process', $fields);
        $this->assertSame('Technologies', $fields['technologies']['label']); // first discipline's label wins
        $this->assertContains('approach', Section::query()->pluck('key')->all()); // creative's section added too
    }

    public function test_details_are_stored_in_json_and_survive_fields_the_form_does_not_show(): void
    {
        $this->portfolio(['type' => 'business']);
        $project = ProjectResource::save(new Project, ['name' => 'Launch', 'status' => 'published', 'industry' => 'Retail',
            'metrics' => "Revenue: +40%\nLeads: 1,200\nno colon", 'budget' => 'NPR 2,00,000']);

        $project = Project::find($project->id);
        $this->assertSame('Retail', $project->industry);
        $this->assertSame([['Revenue', '+40%', 40.0], ['Leads', '1,200', null]], $project->metricRows());

        // Switch to creative: the business-only fields are not in the form, so saving keeps them.
        CurrentPortfolio::get()->update(['type' => 'creative']);
        ProjectResource::save($project, ['name' => 'Launch', 'status' => 'published', 'client' => 'Shop']);
        $this->assertEquals(['industry' => 'Retail', 'metrics' => ['Revenue: +40%', 'Leads: 1,200', 'no colon'], 'budget' => 'NPR 2,00,000', 'client' => 'Shop'],
            Project::find($project->id)->details);
    }
}
