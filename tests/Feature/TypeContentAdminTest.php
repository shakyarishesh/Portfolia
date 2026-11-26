<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Project;
use App\Models\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Admin content screens follow the portfolio type: project form, labels, documents, free-text sections. */
class TypeContentAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_screens_use_the_types_fields_and_words(): void
    {
        $this->portfolio(['type' => 'creative']);
        $this->admin();

        $this->get('/admin/projects')->assertOk()->assertSee('Work')->assertSee('+ Add work sample');
        $this->get('/admin/projects/create')->assertOk()->assertSee('Project title')->assertSee('Creative process')->assertSee('Tools used')
            ->assertDontSee('name="repo_url"', false)->assertDontSee('name="architecture"', false);

        $this->post('/admin/projects', ['name' => 'Poster', 'status' => 'published', 'video_url' => 'javascript:alert(1)'])->assertSessionHasErrors('video_url');
        $this->post('/admin/projects', ['name' => 'Poster', 'status' => 'published', 'client' => 'Gallery', 'video_url' => 'https://youtu.be/abc123'])->assertSessionHasNoErrors();
        $project = Project::sole();
        $this->assertSame(['Gallery', 'https://youtu.be/abc123'], [$project->client, $project->video_url]);

        $this->get("/admin/projects/{$project->id}/edit")->assertSee('Image gallery')->assertSee('Supporting documents');
    }

    public function test_technical_admin_screens_keep_their_words(): void
    {
        $this->portfolio();
        $this->admin();
        $this->get('/admin/projects')->assertSee('+ Add project');
        $this->get('/admin/projects/create')->assertSee('name="repo_url"', false)->assertSee('Repository URL');
    }

    public function test_profession_specific_fields_are_validated(): void
    {
        $this->portfolio(['type' => 'management']);
        $this->admin();
        $this->post('/admin/projects', ['name' => 'Rollout', 'status' => 'draft', 'team_size' => 'lots'])->assertSessionHasErrors('team_size');
        $this->post('/admin/projects', ['name' => 'Rollout', 'status' => 'draft', 'team_size' => '0'])->assertSessionHasErrors('team_size');
        $this->post('/admin/projects', ['name' => 'Rollout', 'status' => 'draft', 'impact' => str_repeat('x', 5001)])->assertSessionHasErrors('impact');

        $mix = $this->portfolio(['type' => 'hybrid', 'disciplines' => ['business', 'management'], 'username' => 'mix', 'name' => 'Mix']);
        $this->withSession([\App\Http\Middleware\ResolveAdminPortfolio::SESSION_KEY => $mix->id]);
        $this->post('/admin/projects', ['name' => 'Both', 'status' => 'draft', 'disciplines' => ['business', 'creative']])->assertSessionHasErrors('disciplines.1');
        $this->post('/admin/projects', ['name' => 'Both', 'status' => 'draft', 'disciplines' => ['business', 'management']])->assertSessionHasNoErrors();
        $this->assertSame(['business', 'management'], Project::where('name', 'Both')->sole()->disciplines);
    }

    public function test_supporting_documents_are_pdf_only(): void
    {
        Storage::fake('public');
        $this->portfolio(['type' => 'business']);
        $this->admin();
        $project = Project::create(['name' => 'Report', 'slug' => 'report', 'status' => 'published']);

        $this->post("/admin/projects/{$project->id}/media", ['collection' => 'document', 'images' => [UploadedFile::fake()->image('x.png')]])->assertSessionHasErrors('images.0');
        $this->post("/admin/projects/{$project->id}/media", ['collection' => 'document', 'alt' => 'Q3 report',
            'images' => [UploadedFile::fake()->create('q3.pdf', 100, 'application/pdf')]])->assertSessionHasNoErrors();
        $doc = Media::sole();
        $this->assertSame(['document', 'Q3 report'], [$doc->collection, $doc->alt]);
        Storage::disk('public')->assertExists($doc->path);
    }

    public function test_free_text_sections_can_be_added_written_and_removed(): void
    {
        $this->portfolio();
        $this->admin();

        $this->get('/admin/sections')->assertOk()->assertSee('Add a section')->assertSee('Publications &amp; research', false);
        $this->post('/admin/sections', ['key' => 'publications'])->assertSessionHasNoErrors();
        $this->post('/admin/sections', ['key' => 'publications'])->assertSessionHasErrors('key');
        $this->post('/admin/sections', ['key' => 'custom'])->assertSessionHasErrors('title');
        $this->post('/admin/sections', ['key' => 'custom', 'title' => 'Volunteering'])->assertSessionHasNoErrors();
        $this->post('/admin/sections', ['key' => 'projects'])->assertSessionHasErrors('key'); // built-ins already exist / aren't text

        $custom = Section::where('key', 'custom-volunteering')->sole();
        $this->put("/admin/sections/{$custom->id}", ['nav_label' => 'Volunteering', 'title' => 'Volunteering', 'is_visible' => '1', 'body' => "Red Cross, **2019–2024**."])
            ->assertSessionHasNoErrors();
        $this->assertSame('Red Cross, **2019–2024**.', $custom->fresh()->body);

        $projects = Section::where('key', 'projects')->sole();
        $this->put("/admin/sections/{$projects->id}", ['nav_label' => 'Projects', 'title' => 'Work', 'is_visible' => '1', 'body' => 'ignored']);
        $this->assertNull($projects->fresh()->body);
        $this->delete("/admin/sections/{$projects->id}")->assertForbidden();
        $this->delete("/admin/sections/{$custom->id}")->assertSessionHasNoErrors();
        $this->assertNull(Section::where('key', 'custom-volunteering')->first());
    }
}
