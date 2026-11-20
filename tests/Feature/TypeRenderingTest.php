<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Portfolio;
use App\Models\PortfolioType;
use App\Models\Project;
use App\Models\Section;
use App\Support\Templates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Public pages render the type's words, free-text sections and profession-specific project details — in every template. */
class TypeRenderingTest extends TestCase
{
    use RefreshDatabase;

    /** One representative portfolio per type, with a published project carrying that type's own fields. */
    private const SAMPLES = [
        'technical' => ['architecture' => 'Event-driven queue workers', 'repo_url' => 'https://github.com/x/api'],
        'creative' => ['client' => 'Himal Coffee', 'process' => 'Moodboards, then sketches.', 'video_url' => 'https://youtu.be/dQw4w9WgXcQ'],
        'business' => ['industry' => 'Retail', 'metrics' => ['Revenue: +62%', 'ROAS: 5.4x'], 'budget' => 'TOP-SECRET-BUDGET'],
        'management' => ['team_size' => 35, 'impact' => 'Saved 18% per year.', 'responsibilities' => ['Hiring', 'Budget']],
        'education' => ['subject' => 'Ecology', 'methodology' => 'Field trips first.', 'problem' => 'Read a landscape.'],
        'general' => ['client' => 'A health NGO', 'outcome' => 'Delivered early.'],
        'hybrid' => ['disciplines' => ['technical', 'creative'], 'process' => 'Sketch, build, test.', 'architecture' => 'Static site'],
    ];

    private function portfolioOfType(string $type): Portfolio
    {
        $portfolio = $this->portfolio(['type' => $type, 'disciplines' => $type === 'hybrid' ? ['technical', 'creative'] : null, 'username' => "p-{$type}", 'name' => ucfirst($type)]);
        Project::create(['name' => 'Showcase', 'slug' => 'showcase', 'status' => 'published', 'description' => 'Overview text.'] + self::SAMPLES[$type]);

        return $portfolio;
    }

    public function test_every_type_renders_in_each_of_its_templates(): void
    {
        foreach (array_keys(self::SAMPLES) as $type) {
            $portfolio = $this->portfolioOfType($type);
            foreach (array_keys(Templates::forType($type)) as $template) {
                $portfolio->update(['template' => $template]);
                $home = $this->get("/p-{$type}")->assertOk();
                $page = $this->get("/p-{$type}/projects/showcase")->assertOk()->assertDontSee('TOP-SECRET-BUDGET');
                $where = "{$type} in {$template}";
                match ($type) {
                    'creative' => $page->assertSee('Himal Coffee')->assertSee('Creative process')->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false),
                    'business' => $page->assertSee('Performance metrics')->assertSee('+62%')->assertSee('width: 62%', false),
                    'management' => $page->assertSee('Team size')->assertSee('Business impact')->assertSee('Hiring'),
                    'education' => $page->assertSee('Learning objectives')->assertSee('Teaching methodology')->assertDontSee('Problem &amp; objectives', false),
                    'general' => $page->assertSee('A health NGO')->assertSee('Delivered early.'),
                    'hybrid' => $page->assertSee('Technical &amp; IT', false)->assertSee('Creative process')->assertSee('Static site'),
                    'technical' => $page->assertSee('Event-driven queue workers'),
                };
                $this->assertTrue($home->isOk(), $where);
            }
        }
    }

    public function test_non_technical_pages_use_the_types_words_not_developer_ones(): void
    {
        $this->portfolioOfType('education')->update(['template' => 'professional']);
        Project::query()->update(['live_url' => 'https://example.test/course']);

        $this->get('/p-education')->assertSee('View Teaching')->assertSee('View online')->assertDontSee('Live demo')->assertDontSee('Projects shipped');
        $this->get('/p-education/projects/showcase')->assertSee('View online')->assertDontSee('Live demo');

        Portfolio::where('username', 'p-education')->update(['template' => 'classic']);
        $this->get('/p-education')->assertDontSee('/p-education/playground', false); // the API playground is a developer extra
    }

    public function test_technical_portfolio_pages_are_unchanged(): void
    {
        $portfolio = $this->portfolioOfType('technical');
        Project::query()->update(['problem' => 'Slow reports.', 'challenges' => 'Locking.', 'live_url' => 'https://example.test']);
        foreach (['classic', 'professional'] as $template) {
            $portfolio->update(['template' => $template]);
            $this->get('/p-technical/projects/showcase')->assertSee('Problem &amp; objectives', false)->assertSee('Challenges &amp; solutions', false)
                ->assertDontSee('pf-details', false); // no profession-specific block for a plain technical project
        }
        $this->get('/p-technical')->assertSee('View Projects')->assertSee('Live demo')->assertSee('Projects shipped');
        $portfolio->update(['template' => 'classic']);
        $this->get('/p-technical')->assertSee('/p-technical/playground', false)->assertSee('Case study');
    }

    public function test_free_text_sections_render_in_every_template_only_with_text(): void
    {
        $portfolio = $this->portfolio(['type' => 'education']);
        Section::where('key', 'philosophy')->update(['body' => "Students learn by **doing**.\n\n- Ask\n- Try"]);
        Section::where('key', 'publications')->update(['body' => '']); // empty: not shown
        Section::create(['key' => 'custom-x', 'nav_label' => 'Script', 'title' => 'Script test', 'body' => '<script>alert(1)</script> [x](javascript:alert(1))', 'sort_order' => 99]);

        foreach (array_keys(Templates::available()) as $template) {
            $portfolio->update(['template' => $template]);
            $this->get('/test-person')->assertOk()
                ->assertSee('How I teach')->assertSee('<strong>doing</strong>', false)
                ->assertDontSee('Publications &amp; research', false)
                ->assertSee('Script test')->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('href="javascript:', false);
        }
    }

    public function test_fields_kept_from_an_earlier_type_are_not_shown_publicly(): void
    {
        $portfolio = $this->portfolioOfType('business');
        Project::sole()->update(['methodology' => 'OLD-TEACHING-NOTE', 'architecture' => null]);
        $this->get('/p-business/projects/showcase')->assertSee('Retail')->assertDontSee('OLD-TEACHING-NOTE');

        $portfolio->update(['type' => 'education']); // switch: now the teaching field shows, the business one is kept but hidden
        $this->get('/p-business/projects/showcase')->assertSee('OLD-TEACHING-NOTE')->assertDontSee('Retail');
        $this->assertSame('Retail', Project::sole()->industry);
    }

    public function test_documents_are_listed_on_the_project_page(): void
    {
        $this->portfolioOfType('business');
        $project = Project::sole();
        Media::create(['model_type' => $project->getMorphClass(), 'model_id' => $project->id, 'collection' => 'document', 'disk' => 'public',
            'path' => 'projects/1/report.pdf', 'original_name' => 'report.pdf', 'mime_type' => 'application/pdf', 'size' => 20480, 'alt' => 'Campaign report']);

        $this->get('/p-business/projects/showcase')->assertSee('Documents')->assertSee('Campaign report')->assertSee('PDF · 20 KB');
    }
}
