<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Media;
use App\Models\PageView;
use App\Models\Profile;
use App\Models\Project;
use Database\Seeders\PortfolioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminInsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(PortfolioSeeder::class);
    }

    private function pdf(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n%test\n");
    }

    public function test_page_views_are_recorded_without_personal_data_and_skip_bots(): void
    {
        $this->get('/')->assertOk();
        $this->get('/rishesh/projects/hajir', ['Referer' => 'https://www.linkedin.com/feed/'])->assertOk();
        $this->get('/', ['User-Agent' => 'Googlebot/2.1'])->assertOk();
        $this->get('/', ['DNT' => '1'])->assertOk();

        $this->assertSame(2, PageView::count());
        $view = PageView::where('kind', 'project')->firstOrFail();
        $this->assertSame('hajir', $view->subject);
        $this->assertSame('www.linkedin.com', $view->referrer_host);
        $this->assertSame(16, strlen($view->visitor));
        $this->assertArrayNotHasKey('ip_address', $view->getAttributes());
    }

    public function test_resume_versions_activation_and_download_tracking(): void
    {
        $this->admin();
        $this->post('/admin/profile/upload', ['collection' => 'resume', 'file' => $this->pdf('cv-2026.pdf')])->assertSessionHasNoErrors();

        $versions = Media::where('collection', 'resume')->orderBy('id')->get();
        $this->assertCount(2, $versions, 'the seeded CV is kept as an older version');
        $this->assertSame([false, true], $versions->pluck('is_active')->all());
        $this->assertSame('cv-2026.pdf', Profile::first()->resume->original_name);

        $this->post("/admin/profile/resumes/{$versions[0]->id}/activate")->assertSessionHasNoErrors();
        $this->assertSame($versions[0]->id, Profile::first()->resume->id);

        auth()->logout();
        $this->get('/rishesh/resume')->assertOk();
        $this->assertSame((string) $versions[0]->id, PageView::where('kind', 'resume')->value('subject'));

        $this->admin();
        $this->get('/admin/profile')->assertOk()->assertSee('cv-2026.pdf')->assertSee('Make active');
        $this->delete("/admin/profile/resumes/{$versions[0]->id}");
        $this->get('/rishesh/resume')->assertNotFound(); // the active one was deleted; nothing active now
    }

    public function test_newer_uploads_of_another_kind_do_not_hide_older_ones(): void
    {
        $this->admin();
        // CV seeded first, then a photo: both must still resolve.
        $this->post('/admin/profile/upload', ['collection' => 'avatar', 'file' => UploadedFile::fake()->image('me.jpg', 300, 300)])->assertSessionHasNoErrors();
        $profile = Profile::with(['avatar', 'resume'])->first();
        $this->assertNotNull($profile->avatar);
        $this->assertNotNull($profile->resume);

        // Thumbnail first, then screenshots: the thumbnail must survive.
        $project = Project::where('slug', 'hajir')->firstOrFail();
        $this->post("/admin/projects/{$project->id}/media", ['collection' => 'thumbnail', 'images' => [UploadedFile::fake()->image('t.png', 400, 300)]]);
        $this->post("/admin/projects/{$project->id}/media", ['collection' => 'screenshot', 'images' => [UploadedFile::fake()->image('s.png', 400, 300)]]);
        $this->assertNotNull($project->fresh()->thumbnail);
    }

    public function test_analytics_and_dashboard_render_with_data(): void
    {
        $this->get('/rishesh/projects/hajir');
        $this->get('/rishesh/resume');
        ContactMessage::create(['name' => 'A', 'email' => 'a@example.test', 'message' => 'Hello there, nice site.']);

        $this->admin();
        $this->get('/admin/analytics?days=7')->assertOk()->assertSee('CV downloads')->assertSee('Hajir')->assertSee('Contact conversion');
        $this->get('/admin/analytics?days=12345')->assertOk(); // unknown ranges fall back to 30 days
        $this->get('/admin')->assertOk()->assertSee('CV downloads · 30 days');
        $this->get('/admin/posts')->assertOk();
        $this->get('/admin/testimonials/create')->assertOk();
    }

    public function test_message_states_and_archive(): void
    {
        $m = ContactMessage::create(['name' => 'Ram', 'email' => 'ram@example.test', 'subject' => 'Hiring', 'message' => 'We would like to talk.']);
        $this->admin();

        $this->get("/admin/messages/{$m->id}")->assertOk();
        $this->post("/admin/messages/{$m->id}/state", ['field' => 'replied_at', 'on' => 1]);
        $this->assertNotNull($m->fresh()->replied_at);

        $this->post("/admin/messages/{$m->id}/state", ['field' => 'read_at', 'on' => 0])->assertRedirect('/admin/messages');
        $this->assertNull($m->fresh()->read_at);

        $this->post("/admin/messages/{$m->id}/state", ['field' => 'archived_at', 'on' => 1]);
        $this->get('/admin/messages')->assertDontSee('Hiring');
        $this->get('/admin/messages?box=archived')->assertSee('Hiring');
        $this->post("/admin/messages/{$m->id}/state", ['field' => 'ip_address', 'on' => 1])->assertSessionHasErrors('field');
    }

    public function test_contact_form_emails_the_owner(): void
    {
        $this->post('/rishesh/contact', ['name' => 'Recruiter', 'email' => 'r@example.test', 'subject' => 'Role', 'message' => 'Are you open to a backend role?'])
            ->assertSessionHas('contact_sent');

        $sent = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $email = $sent[0]->getOriginalMessage();
        $this->assertSame(Profile::first()->email, $email->getTo()[0]->getAddress());
        $this->assertSame('r@example.test', $email->getReplyTo()[0]->getAddress());
        $this->assertStringContainsString('Are you open to a backend role?', $email->getTextBody());
    }
}
