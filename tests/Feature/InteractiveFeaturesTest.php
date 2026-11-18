<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Testimonial;
use App\Services\Portfolio\Assistant;
use App\Services\Portfolio\PortfolioData;
use Database\Seeders\PortfolioSeeder;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InteractiveFeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(PortfolioSeeder::class);
    }

    public function test_home_has_project_filters_terminal_and_tech_links(): void
    {
        $this->get('/rishesh')->assertOk()
            ->assertSee('class="project-tools', false)
            ->assertSee('data-techs="|', false)
            ->assertSee('<dialog class="terminal"', false)
            ->assertSee('data-accent="blue"', false)
            ->assertSee('class="skill-link"', false)
            ->assertDontSee('class="assistant"', false); // assistant is off by default
    }

    public function test_project_page_shows_case_study_fields_diagram_and_related(): void
    {
        Project::where('slug', 'hajir')->update([
            'category' => 'HR & Payroll', 'problem' => 'Manual payroll took **days**.', 'challenges' => '**Challenge:** tax slabs change yearly.',
            'diagram' => "flowchart LR\n  A[Client]-->B[API]", 'meta_description' => 'Hajir case study',
        ]);

        $this->get('/rishesh/projects/hajir')->assertOk()
            ->assertSee('Problem &amp; objectives', false)
            ->assertSee('<strong>days</strong>', false)
            ->assertSee('Challenges &amp; solutions', false)
            ->assertSee('class="mermaid"', false)
            ->assertSee('A[Client]--&gt;B[API]', false) // escaped, Mermaid reads textContent
            ->assertSee('content="Hajir case study"', false)
            ->assertSee('Related projects');
    }

    public function test_testimonials_show_only_when_published(): void
    {
        Testimonial::create(['name' => 'Sita Sharma', 'company' => 'Acme', 'quote' => 'Great to work with.', 'status' => 'draft']);
        $this->get('/rishesh')->assertDontSee('Sita Sharma');

        Testimonial::query()->update(['status' => 'published']);
        $this->get('/rishesh')->assertSee('Sita Sharma')->assertSee('Great to work with.');
    }

    public function test_demo_api_lists_filters_validates_and_404s(): void
    {
        $this->getJson('/api/demo/employees?department=support')->assertOk()
            ->assertJsonPath('meta.count', 2)->assertJsonPath('meta.sample_data', true);
        $this->get('/api/demo/employees?department=marketing')->assertStatus(422)->assertJsonValidationErrors('department');
        $this->getJson('/api/demo/employees/99')->assertNotFound()->assertJsonPath('message', 'Employee 99 not found.');
    }

    public function test_demo_payroll_calculation(): void
    {
        $res = $this->postJson('/api/demo/payroll/calculate', [
            'basic_salary' => 52000, 'allowances' => 0, 'working_days' => 26, 'unpaid_leave_days' => 1,
            'overtime_hours' => 8, 'overtime_multiplier' => 1.5, 'provident_fund_percent' => 10,
        ])->assertOk();

        // hourly = 52000/26/8 = 250 → overtime 8*250*1.5 = 3000; leave = 2000; gross = 53000; pf = 5000
        $res->assertJsonPath('data.earnings.overtime', 3000)
            ->assertJsonPath('data.earnings.gross', 53000)
            ->assertJsonPath('data.deductions.provident_fund', 5000);
        // annual taxable 576000: 500000*1% + 76000*10% = 12600 → 1050/month
        $res->assertJsonPath('data.deductions.income_tax', 1050)->assertJsonPath('data.net_pay', 46950);

        $this->postJson('/api/demo/payroll/calculate', ['basic_salary' => -1])->assertStatus(422)
            ->assertJsonValidationErrors(['basic_salary', 'working_days']);
    }

    public function test_playground_page_renders(): void
    {
        $this->get('/rishesh/playground')->assertOk()->assertSee('API playground')->assertSee('Calculate a payslip');
    }

    public function test_assistant_is_disabled_without_configuration(): void
    {
        $this->postJson('/rishesh/assistant', ['question' => 'Where do you work?'])->assertNotFound();
    }

    public function test_assistant_answers_from_published_content_only(): void
    {
        Project::create(['name' => 'Unreleased Zephyr Draft', 'slug' => 'zephyr', 'status' => 'draft']);
        $history = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200, ['Content-Type' => 'application/json'], json_encode([
            'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => 'Rishesh works at Velox Labs.']],
            'stop_reason' => 'end_turn', 'stop_sequence' => null, 'usage' => ['input_tokens' => 10, 'output_tokens' => 5],
        ]))]));
        $stack->push(Middleware::history($history));
        $this->app->instance(Assistant::class, new Assistant(app(PortfolioData::class), [
            'enabled' => true, 'api_key' => 'test-key', 'model' => 'claude-opus-5-5', 'base_url' => 'https://api.anthropic.com',
        ], new Guzzle(['handler' => $stack])));

        $this->get('/rishesh')->assertSee('class="assistant"', false);
        $this->postJson('/rishesh/assistant', ['question' => 'Where does Rishesh work?', 'history' => [['role' => 'assistant', 'text' => 'Hi!']]])
            ->assertOk()->assertJsonPath('answer', 'Rishesh works at Velox Labs.');

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('claude-opus-5-5', $body['model']);
        $this->assertSame('user', $body['messages'][0]['role'], 'leading assistant turns are dropped');
        $this->assertStringContainsString('Hajir', $body['system'][0]['text']);
        $this->assertStringNotContainsString('Zephyr', $body['system'][0]['text'], 'drafts never reach the model');

        $this->postJson('/rishesh/assistant', ['question' => str_repeat('a', 501)])->assertStatus(422);
    }
}
