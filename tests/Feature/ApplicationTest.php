<?php

namespace Tests\Feature;

use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\ExpertiseArea;
use App\Models\Media;
use App\Models\Portfolio;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SocialLink;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\CurrentPortfolio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationTest extends TestCase
{
    use RefreshDatabase;

    private Portfolio $existing;

    private const CONTENT = [Experience::class, Project::class, SkillCategory::class, Skill::class, Education::class, Certification::class,
        SocialLink::class, ExpertiseArea::class, Testimonial::class, Media::class];

    protected function setUp(): void
    {
        parent::setUp();
        $this->existing = $this->portfolio(['name' => 'Alice', 'username' => 'alice']);
        Project::create(['name' => 'Alice Project', 'slug' => 'alice-project', 'status' => 'published']);
        CurrentPortfolio::set(null);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'username' => 'Dana-Dev',
            'template' => 'professional',
            'full_name' => 'Dana Developer', 'headline' => 'Backend Developer', 'intro' => 'I build APIs.', 'about' => "Para one.\n\nPara two.",
            'location' => 'Kathmandu', 'email' => 'dana@example.test', 'phone' => '+977 9800000000', 'show_phone' => '1', 'open_to_work' => '1',
            'experience' => [
                ['company' => 'Acme', 'company_url' => 'https://acme.test', 'role' => 'Engineer', 'employment_type' => 'full_time', 'location' => 'Remote',
                    'start_date' => '2022-01-01', 'end_date' => '', 'is_current' => '1', 'summary' => 'Built things.', 'highlights' => "Shipped X\nScaled Y", 'technologies' => 'Laravel, MySQL'],
                ['company' => '', 'company_url' => '', 'role' => '', 'employment_type' => 'full_time'], // blank row → ignored
            ],
            'projects' => [['name' => 'Rocket', 'tagline' => 'Goes up', 'category' => 'Space', 'description' => 'A rocket.', 'role' => 'Lead',
                'start_date' => '2023-01-01', 'end_date' => '2023-06-01', 'live_url' => 'https://rocket.test', 'repo_url' => 'https://github.com/d/rocket',
                'technologies' => 'Vue, Laravel', 'is_featured' => '1']],
            'skills' => [['name' => 'Backend', 'skills' => 'PHP, Laravel, PHP'], ['name' => '', 'skills' => '']],
            'education' => [['institution' => 'TU', 'degree' => 'BSc', 'field' => 'CS', 'location' => 'Kirtipur', 'start_year' => '', 'end_year' => '2020']],
            'certifications' => [['name' => 'AWS SA', 'issuer' => 'AWS', 'issued_on' => '2024-02-01', 'credential_url' => 'https://aws.test/c/1']],
            'links' => [['platform' => 'github', 'url' => 'https://github.com/dana'], ['platform' => 'linkedin', 'url' => '']],
            'expertise' => [['title' => 'APIs', 'description' => 'REST and GraphQL']],
            'testimonials' => [['name' => 'Bob', 'position' => 'CTO', 'company' => 'Acme', 'quote' => 'Great work.']],
        ];
    }

    private function counts(Portfolio $p): array
    {
        return array_map(fn ($m) => $m::withoutGlobalScopes()->where('portfolio_id', $p->id)->count(), self::CONTENT);
    }

    public function test_form_renders_every_group_and_template(): void
    {
        $this->get('/create')->assertOk()
            ->assertSee(url('/').'/', false)->assertSee('name="experience[0][company]"', false)->assertSee('name="testimonials[0][quote]"', false)
            ->assertSee('experience[__INDEX__][company]', false)->assertSee('templates/gradient/preview.svg')->assertSee('name="website"', false);
    }

    public function test_full_submission_creates_one_pending_portfolio_with_all_content(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $before = $this->counts($this->existing);

        $this->post('/create', $this->payload([
            'avatar' => UploadedFile::fake()->image('me.jpg', 200, 200),
            'resume' => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
        ]))->assertRedirect('/create/submitted')->assertSessionHas('applied_url', url('dana-dev'));

        $this->assertSame(2, Portfolio::count());
        $p = Portfolio::where('username', 'dana-dev')->firstOrFail();
        $this->assertSame(['pending', 'professional', 'Dana Developer', null, null], [$p->status, $p->template, $p->name, $p->user_id, $p->published_at]);
        $this->assertSame($before, $this->counts($this->existing), 'other portfolios untouched');

        CurrentPortfolio::using($p, function () {
            $profile = Profile::query()->with(['avatar', 'resume'])->sole();
            $this->assertSame(['Dana Developer', 'Backend Developer', 'dana@example.test', true, true], [$profile->full_name, $profile->headline, $profile->email, $profile->show_phone, $profile->open_to_work]);
            Storage::disk('public')->assertExists($profile->avatar->path);
            Storage::disk('local')->assertExists($profile->resume->path);

            $exp = Experience::with('technologies')->sole();
            $this->assertSame(['Acme', true, ['Shipped X', 'Scaled Y'], ['Laravel', 'MySQL'], 'published'], [$exp->company, $exp->is_current, $exp->highlights, $exp->technologies->pluck('name')->all(), $exp->status]);
            $project = Project::with('technologies')->sole();
            $this->assertSame(['rocket', true, ['Vue', 'Laravel'], 'published'], [$project->slug, $project->is_featured, $project->technologies->pluck('name')->all(), $project->status]);
            $this->assertSame(['PHP', 'Laravel'], SkillCategory::sole()->skills()->orderBy('sort_order')->pluck('name')->all());
            $this->assertSame([null, 2020], [Education::sole()->start_year, Education::sole()->end_year]);
            $this->assertSame(['GitHub', 'https://github.com/dana'], [SocialLink::sole()->label, SocialLink::sole()->url]);
        });
        $this->assertSame([1, 1, 1, 2, 1, 1, 1, 1, 1, 2], $this->counts($p));
    }

    public function test_pending_portfolio_is_hidden_until_approved_then_public(): void
    {
        $this->post('/create', $this->payload())->assertRedirect('/create/submitted');
        $p = Portfolio::where('username', 'dana-dev')->firstOrFail();
        $this->get('/dana-dev')->assertNotFound();

        $this->admin();
        $this->get('/admin/applications')->assertOk()->assertSee("/admin/applications/{$p->id}")->assertSee('/dana-dev')->assertSee('dana@example.test');
        $this->get("/admin/applications/{$p->id}")->assertOk()
            ->assertSee('Backend Developer')->assertSee('Shipped X')->assertSee('Rocket')->assertSee('Laravel')->assertSee('Backend')
            ->assertSee('TU')->assertSee('AWS SA')->assertSee('https://github.com/dana')->assertSee('APIs')->assertSee('Great work.')
            ->assertSee("/admin/portfolios/{$p->id}/preview")->assertDontSee('Alice Project');
        $this->get('/admin')->assertOk()->assertSee('Applications');

        $this->post("/admin/applications/{$p->id}/approve")->assertRedirect('/admin/applications')->assertSessionHas('success');
        $p->refresh();
        $this->assertSame('published', $p->status);
        $this->assertNotNull($p->published_at);

        auth()->logout();
        $this->get('/dana-dev')->assertOk()->assertSee('Dana Developer')->assertSee('Rocket');

        // Approving again is a no-op.
        $this->admin();
        $this->post("/admin/applications/{$p->id}/approve")->assertRedirect('/admin/applications')->assertSessionHasErrors('status');
        $this->assertEquals($p->published_at, $p->fresh()->published_at);
    }

    public function test_reject_keeps_data_hidden_and_can_still_be_approved(): void
    {
        $this->post('/create', $this->payload());
        $p = Portfolio::where('username', 'dana-dev')->firstOrFail();
        $this->admin();

        $this->post("/admin/applications/{$p->id}/reject")->assertRedirect('/admin/applications');
        $this->assertSame('rejected', $p->fresh()->status);
        $this->get('/admin/applications')->assertDontSee("/admin/applications/{$p->id}");
        $this->get('/admin/applications?status=rejected')->assertOk()->assertSee("/admin/applications/{$p->id}");
        $this->post("/admin/applications/{$p->id}/reject")->assertSessionHasErrors('status');

        auth()->logout();
        $this->get('/dana-dev')->assertNotFound();
        $this->assertSame(1, Project::withoutGlobalScopes()->where('portfolio_id', $p->id)->count());

        $this->admin();
        $this->post("/admin/applications/{$p->id}/approve");
        $this->assertSame('published', $p->fresh()->status);
    }

    public function test_only_open_applications_can_be_acted_on(): void
    {
        $this->admin();
        $this->post("/admin/applications/{$this->existing->id}/reject")->assertSessionHasErrors('status');
        $this->existing->update(['status' => 'draft']);
        $this->post("/admin/applications/{$this->existing->id}/approve")->assertSessionHasErrors('status');
        $this->assertSame('draft', $this->existing->fresh()->status);
    }

    public function test_username_validation(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        foreach (['ab', '-bad', 'has space', 'dot.name', 'admin', 'create', 'demo', 'templates', 'alice', 'ALICE'] as $bad) {
            $this->post('/create', $this->payload(['username' => $bad]))->assertSessionHasErrors('username');
        }
        $this->assertSame(1, Portfolio::count());
    }

    public function test_field_validation_errors_point_at_the_right_row(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('public');
        $payload = $this->payload([
            'email' => 'not-an-email',
            'template' => 'nope',
            'avatar' => UploadedFile::fake()->create('me.pdf', 10, 'application/pdf'),
            'resume' => UploadedFile::fake()->image('cv.png'),
            'projects' => [['name' => 'Ok', 'live_url' => 'https://ok.test'], ['name' => 'Bad', 'live_url' => 'javascript:alert(1)']],
            'education' => [['institution' => 'TU', 'degree' => 'BSc', 'start_year' => '2020', 'end_year' => '2019']],
            'experience' => [['company' => '', 'role' => '', 'start_date' => '', 'summary' => 'Only a summary']],
        ]);

        $this->post('/create', $payload)->assertSessionHasErrors([
            'email', 'template', 'avatar', 'resume', 'projects.1.live_url', 'education.0.end_year',
            'experience.0.company', 'experience.0.role', 'experience.0.start_date',
        ])->assertSessionDoesntHaveErrors(['projects.0.live_url', 'skills.1.name']);
        $this->assertSame(1, Portfolio::count());

        // The form re-renders the submitted rows with the error beside the field.
        $this->followingRedirects()->from('/create')->post('/create', $payload)
            ->assertSee('value="Bad"', false)->assertSee('id="projects-1-live_url-error"', false)->assertSee('live url (project 2)');
    }

    public function test_present_end_date_marks_the_entry_current_and_drops_any_end_date(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->get('/create')->assertSee('name="experience[0][is_current]"', false)->assertSee('name="projects[0][is_current]"', false)->assertSee('data-present', false);

        $this->post('/create', $this->payload([
            // A stale end date before "Present" was ticked (it would also fail after_or_equal) is ignored.
            'experience' => [['company' => 'Acme', 'role' => 'Engineer', 'employment_type' => 'full_time', 'start_date' => '2022-01-01', 'end_date' => '2021-01-01', 'is_current' => '1']],
            'projects' => [['name' => 'Rocket', 'start_date' => '2023-01-01', 'is_current' => '1'], ['name' => 'Done', 'start_date' => '2023-01-01', 'end_date' => '2023-06-01']],
        ]))->assertSessionHasNoErrors()->assertRedirect(route('apply.submitted'));

        CurrentPortfolio::using(Portfolio::where('username', 'dana-dev')->firstOrFail(), function () {
            $job = Experience::firstOrFail();
            $this->assertTrue($job->is_current);
            $this->assertNull($job->end_date);
            $this->assertSame('Jan 2022 – Present', $job->period());
            $this->assertSame([true, false], Project::orderBy('sort_order')->pluck('is_current')->all());
            $this->assertNull(Project::where('name', 'Rocket')->value('end_date'));
        });
    }

    public function test_errors_stay_on_their_row_when_blank_rows_and_gaps_come_first(): void
    {
        // Rows added/removed with JS can arrive as [0 => blank, 3 => filled]: the filled one is "job 1" and keeps its date.
        $payload = $this->payload(['experience' => [
            0 => ['company' => '', 'role' => '', 'employment_type' => 'full_time'],
            3 => ['company' => 'Hajir', 'role' => '', 'employment_type' => 'full_time', 'start_date' => '2026-03-01', 'end_date' => '2026-11-18'],
        ]]);
        $this->post('/create', $payload)->assertSessionHasErrors('experience.0.role')->assertSessionDoesntHaveErrors(['experience.0.start_date', 'experience.1.start_date']);
        $this->followingRedirects()->from('/create')->post('/create', $payload)
            ->assertSee('value="2026-03-01"', false)->assertSee('id="experience-0-role-error"', false)->assertDontSee('start date (job');
    }

    public function test_too_many_rows_are_rejected(): void
    {
        $this->post('/create', $this->payload(['expertise' => array_fill(0, 21, ['title' => 'X'])]))->assertSessionHasErrors('expertise');
    }

    public function test_honeypot_stores_nothing(): void
    {
        $this->post('/create', $this->payload(['website' => 'http://spam.test']))->assertRedirect('/create/submitted');
        $this->assertSame(1, Portfolio::count());
        $this->get('/create/submitted')->assertOk()->assertSee('An admin will review');
    }

    public function test_submissions_are_throttled_per_ip(): void
    {
        foreach (range(1, 3) as $i) {
            $this->post('/create', ['website' => 'x'])->assertRedirect();
        }
        $this->post('/create', ['website' => 'x'])->assertStatus(429);
    }

    public function test_admin_routes_need_an_admin(): void
    {
        $this->post('/create', $this->payload());
        $p = Portfolio::where('username', 'dana-dev')->firstOrFail();
        $routes = [['get', '/admin/applications'], ['get', "/admin/applications/{$p->id}"],
            ['post', "/admin/applications/{$p->id}/approve"], ['post', "/admin/applications/{$p->id}/reject"]];

        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertRedirect('/admin/login');
        }
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        foreach ($routes as [$method, $url]) {
            $this->{$method}($url)->assertForbidden();
        }
        $this->assertSame('pending', $p->fresh()->status);
    }
}
