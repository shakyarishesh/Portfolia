<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadsAndContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_project_images_are_validated_and_stored(): void
    {
        $this->profile();
        $this->admin();
        $project = Project::create(['name' => 'P', 'slug' => 'p', 'status' => 'published']);

        $this->post("/admin/projects/{$project->id}/media", ['collection' => 'screenshot', 'images' => [UploadedFile::fake()->create('evil.svg', 5, 'image/svg+xml')]])
            ->assertSessionHasErrors('images.0');
        $this->post("/admin/projects/{$project->id}/media", ['collection' => 'screenshot', 'images' => [UploadedFile::fake()->createWithContent('fake.png', 'not an image')]])
            ->assertSessionHasErrors('images.0');

        $this->post("/admin/projects/{$project->id}/media", ['collection' => 'thumbnail', 'images' => [UploadedFile::fake()->image('a.png', 1200, 675)], 'alt' => 'Dashboard'])
            ->assertSessionHasNoErrors();
        $thumb = $project->fresh()->thumbnail;
        Storage::disk('public')->assertExists($thumb->path);
        $this->assertSame('Dashboard', $thumb->alt);

        // Replacing the thumbnail removes the old file.
        $this->post("/admin/projects/{$project->id}/media", ['collection' => 'thumbnail', 'images' => [UploadedFile::fake()->image('b.png', 800, 450)]]);
        Storage::disk('public')->assertMissing($thumb->path);
        $this->assertSame(1, $project->media()->where('collection', 'thumbnail')->count());

        $this->get('/test-person/projects/p')->assertSee('class="hero-shot"', false);
    }

    public function test_resume_must_be_a_pdf_and_is_served_privately(): void
    {
        $this->profile(['full_name' => 'Rishesh Shakya']);
        $this->admin();

        $this->post('/admin/profile/upload', ['collection' => 'resume', 'file' => UploadedFile::fake()->image('cv.png')])->assertSessionHasErrors('file');
        $this->post('/admin/profile/upload', ['collection' => 'resume', 'file' => UploadedFile::fake()->createWithContent('cv.pdf', "%PDF-1.4\n%test\n")])->assertSessionHasNoErrors();

        $resume = \App\Models\Profile::first()->resume;
        $this->assertSame('local', $resume->disk);
        $this->get('/test-person/resume')->assertOk()->assertDownload('rishesh-shakya-cv.pdf');
    }

    public function test_contact_form_stores_valid_messages(): void
    {
        $this->profile();

        $this->post('/test-person/contact', ['name' => 'A', 'email' => 'bad', 'message' => 'short'])
            ->assertRedirect(url('/test-person').'#contact')->assertSessionHasErrors(['email', 'message']);
        $this->post('/test-person/contact', ['name' => 'Recruiter', 'email' => 'r@example.test', 'message' => 'Hello, let us talk about a role.'])
            ->assertRedirect(url('/test-person').'#contact')->assertSessionHas('contact_sent');

        $this->assertSame(1, ContactMessage::count());
    }

    public function test_contact_honeypot_and_disabled_form(): void
    {
        $profile = $this->profile();
        $this->post('/test-person/contact', ['name' => 'Bot', 'email' => 'b@example.test', 'message' => 'Buy things now please', 'website' => 'spam.example'])
            ->assertSessionHas('contact_sent');
        $this->assertSame(0, ContactMessage::count());

        $profile->update(['contact_form_enabled' => false]);
        $this->post('/test-person/contact', ['name' => 'A', 'email' => 'a@example.test', 'message' => 'Hello there, friend.'])->assertNotFound();
    }
}
