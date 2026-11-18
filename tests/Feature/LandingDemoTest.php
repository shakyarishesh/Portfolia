<?php

namespace Tests\Feature;

use App\Support\Templates;
use App\Support\Username;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LandingDemoTest extends TestCase
{
    use RefreshDatabase;

    private function demoInput(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Demo Dana', 'headline' => 'Data Engineer', 'bio' => 'I build pipelines.', 'location' => 'Kathmandu',
            'email' => 'dana@example.test', 'skills' => 'Python, SQL, Airflow',
            'projects' => [
                ['name' => 'Stream Lab', 'description' => 'Realtime metrics.', 'technologies' => 'Kafka, Flink', 'url' => 'https://example.test/stream'],
                ['name' => '', 'description' => '', 'technologies' => '', 'url' => ''],
            ],
            'template' => 'classic',
        ];
    }

    public function test_landing_page_explains_the_platform(): void
    {
        $res = $this->get('/')->assertOk()
            ->assertSee(url('/').'/<b>your-username</b>', false)
            ->assertSee('href="'.route('apply.create').'"', false)
            ->assertSee('id="demo"', false)
            ->assertSee('action="'.route('demo.store').'"', false);
        foreach (Templates::available() as $t) {
            $res->assertSee($t['name'])->assertSee(asset($t['preview']), false);
        }
    }

    public function test_demo_store_validates(): void
    {
        $this->post('/demo', $this->demoInput([
            'name' => '', 'email' => 'nope', 'template' => 'not-a-template',
            'projects' => [['name' => '', 'description' => 'orphan', 'url' => 'javascript:alert(1)']],
        ]))->assertRedirect(route('root').'#demo')
            ->assertSessionHasErrors(['name', 'email', 'template', 'projects.0.name', 'projects.0.url']);
        $this->post('/demo', $this->demoInput(['projects' => array_fill(0, 3, ['name' => 'X'])]))->assertSessionHasErrors('projects');
        $this->post('/demo', $this->demoInput(['name' => str_repeat('a', 81)]))->assertSessionHasErrors('name');
        $this->assertNull(session('demo'));
    }

    public function test_demo_stores_in_session_only_and_renders_without_queries(): void
    {
        $tables = ['portfolios', 'profiles', 'projects', 'technologies', 'skills', 'sections', 'page_views', 'audit_logs'];
        $before = collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);

        $this->post('/demo', $this->demoInput())->assertRedirect(route('demo.show'));
        $this->assertSame('Demo Dana', session('demo.name'));
        $this->assertCount(1, session('demo.projects'), 'blank project rows are dropped');

        DB::enableQueryLog();
        $this->get('/demo')->assertOk();
        $this->get('/demo/projects/stream-lab')->assertOk();
        // Only schema metadata (Eloquent's guarded-column check when filling the in-memory models), never data.
        $queries = collect(DB::getQueryLog())->pluck('query')->reject(fn ($q) => str_contains($q, 'information_schema'));
        $this->assertSame([], $queries->all(), 'demo pages must not touch portfolio data');

        $this->assertEquals($before, collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]));
    }

    public function test_demo_without_session_redirects_to_the_form(): void
    {
        $this->get('/demo')->assertRedirect(route('root').'#demo');
        $this->get('/demo/projects/anything')->assertRedirect(route('root').'#demo');
    }

    public function test_demo_renders_with_every_template(): void
    {
        $this->post('/demo', $this->demoInput());
        DB::enableQueryLog();

        foreach (array_keys(Templates::available()) as $key) {
            $this->get('/demo?template='.$key)->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
                ->assertSee('DEMO — nothing is saved')
                ->assertSee('Demo Dana')->assertSee('Airflow')->assertSee('Stream Lab')
                ->assertSee('/demo/projects/stream-lab', false)->assertDontSee('/demo/playground', false)
                ->assertDontSee('Exit preview')
                ->assertDontSee('PREVIEW — not the live site');
            $this->assertSame($key, session('demo.template'));

            $this->get('/demo/projects/stream-lab')->assertOk()
                ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
                ->assertSee('Stream Lab')->assertSee('Realtime metrics.')->assertSee('Kafka')
                ->assertSee('DEMO — nothing is saved');
        }
        $this->assertSame([], collect(DB::getQueryLog())->pluck('query')->reject(fn ($q) => str_contains($q, 'information_schema'))->all());
    }

    public function test_admin_sees_demo_banner_not_preview_controls(): void
    {
        $this->admin();
        $this->post('/demo', $this->demoInput());
        $this->get('/demo')->assertOk()->assertSee('DEMO — nothing is saved')->assertDontSee('Exit preview');
    }

    public function test_template_switch_is_allow_listed(): void
    {
        $this->post('/demo', $this->demoInput(['template' => 'gradient']));
        $this->get('/demo?template=../../secret')->assertOk();
        $this->assertSame('gradient', session('demo.template'));
        $this->get('/demo?template=picto')->assertOk();
        $this->assertSame('picto', session('demo.template'));
    }

    public function test_unknown_demo_project_is_404(): void
    {
        $this->post('/demo', $this->demoInput());
        $this->get('/demo/projects/nope')->assertNotFound();
    }

    public function test_duplicate_project_names_get_unique_slugs(): void
    {
        $this->post('/demo', $this->demoInput(['projects' => [['name' => 'Same'], ['name' => 'Same']]]));
        $this->get('/demo')->assertSee('/demo/projects/same-2', false);
        $this->get('/demo/projects/same-2')->assertOk();
    }

    public function test_landing_prefills_from_session_and_delete_clears(): void
    {
        $this->post('/demo', $this->demoInput());
        $this->get('/')->assertSee('value="Demo Dana"', false)->assertSee('value="Python, SQL, Airflow"', false);

        $this->delete('/demo')->assertRedirect(route('root').'#demo');
        $this->assertNull(session('demo'));
        $this->get('/demo')->assertRedirect(route('root').'#demo');
    }

    public function test_demo_is_reserved_and_kept_out_of_seo(): void
    {
        $this->assertTrue(Username::isReserved('demo'));
        $this->get('/robots.txt')->assertSee("Disallow: /demo\$\n", false);
        $this->get('/sitemap.xml')->assertSee('<loc>'.url('/').'</loc>', false)->assertDontSee('/demo');
    }
}
