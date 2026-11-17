<?php

namespace Tests\Feature;

use App\Jobs\ExtractWithAi;
use App\Models\AuditLog;
use App\Models\ConversationImport;
use App\Models\ImportedConversation;
use App\Models\Profile;
use App\Models\ProposedChange;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\SocialLink;
use App\Services\Import\Extractors\Candidate;
use App\Services\Import\Extractors\LlmExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function export(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('conversations.json', json_encode([
            ['uuid' => 'a', 'name' => 'Caching', 'chat_messages' => [
                ['sender' => 'human', 'text' => 'I added Redis caching to the Laravel API.'],
                ['sender' => 'assistant', 'text' => 'You could try MongoDB too.'],
            ]],
            ['uuid' => 'b', 'name' => 'Links', 'chat_messages' => [
                ['sender' => 'human', 'text' => 'My GitHub is https://github.com/someone and I react quickly to bugs.'],
            ]],
        ]));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->profile(['headline' => 'Backend Developer']);
        $cat = SkillCategory::create(['name' => 'Backend', 'status' => 'published']);
        Skill::create(['skill_category_id' => $cat->id, 'name' => 'Laravel', 'status' => 'published']);
        $this->admin();
    }

    public function test_import_runs_keyword_scan_and_classifies_proposals(): void
    {
        $this->post('/admin/imports', ['mode' => 'file', 'file' => $this->export()])->assertRedirect();

        $import = ConversationImport::firstOrFail();
        $this->assertSame(2, $import->conversation_count);
        $this->assertSame('extracted', $import->status);

        $byName = ProposedChange::all()->keyBy(fn ($p) => $p->proposed['name'] ?? $p->proposed['url'] ?? '?');
        $this->assertSame('new', $byName['Redis']->classification);
        $this->assertSame('create', $byName['Redis']->action);
        $this->assertSame('duplicate', $byName['Laravel']->classification);
        $this->assertSame('new', $byName['https://github.com/someone']->classification);
        // Only the owner's messages are scanned, and "react" (lowercase verb) is not the React library.
        $this->assertFalse($byName->has('MongoDB'));
        $this->assertFalse($byName->has('React'));
        $this->assertSame(0, Skill::where('name', 'Redis')->count(), 'nothing is applied without approval');

        // Re-importing the same file does not duplicate open proposals.
        $this->post('/admin/imports', ['mode' => 'file', 'file' => $this->export()]);
        $this->assertSame(1, ProposedChange::where('proposed->name', 'Redis')->count());
    }

    public function test_upload_validation(): void
    {
        $this->post('/admin/imports', ['mode' => 'file', 'file' => UploadedFile::fake()->create('evil.php', 1, 'application/x-php')])->assertSessionHasErrors('file');
        $this->post('/admin/imports', ['mode' => 'file', 'file' => UploadedFile::fake()->createWithContent('broken.json', '{oops')])->assertSessionHasErrors('file');
        $this->post('/admin/imports', ['mode' => 'text', 'text' => ''])->assertSessionHasErrors('text');
        $this->assertSame(0, ConversationImport::count());
    }

    public function test_approving_a_new_skill_creates_a_draft_with_audit_trail(): void
    {
        $this->post('/admin/imports', ['mode' => 'file', 'file' => $this->export()]);
        $proposal = ProposedChange::where('proposed->name', 'Redis')->firstOrFail();

        $this->post("/admin/proposals/{$proposal->id}/approve")->assertRedirect();

        $skill = Skill::where('name', 'Redis')->firstOrFail();
        $this->assertSame('draft', $skill->status, 'imported records never go public automatically');
        $this->assertSame('Databases', $skill->category->name);
        $this->assertSame('draft', $skill->category->status);
        $this->assertSame('applied', $proposal->fresh()->status);
        $this->assertSame($skill->id, $proposal->fresh()->target_id);

        $log = AuditLog::where('auditable_type', 'skill')->where('auditable_id', $skill->id)->firstOrFail();
        $this->assertSame('import', $log->source);
        $this->assertSame($proposal->id, $log->proposed_change_id);

        $this->post("/admin/proposals/{$proposal->id}/approve")->assertSessionHasErrors('proposal');
    }

    public function test_contradictions_need_explicit_confirmation(): void
    {
        $this->post('/admin/proposals', ['entity_type' => 'profile', 'excerpt' => 'I am a staff engineer', 'values' => ['headline' => 'Staff Engineer']])->assertRedirect();
        $proposal = ProposedChange::firstOrFail();
        $this->assertSame('contradiction', $proposal->classification);
        $this->assertSame('manual', $proposal->extractor);
        $this->assertSame(['headline' => 'Backend Developer'], $proposal->current);

        $this->post("/admin/proposals/{$proposal->id}/approve")->assertSessionHasErrors('confirm_overwrite');
        $this->assertSame('Backend Developer', Profile::first()->headline);

        $this->post("/admin/proposals/{$proposal->id}/approve", ['confirm_overwrite' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('Staff Engineer', Profile::first()->headline);
        $this->assertSame('me@example.test', Profile::first()->email, 'other profile fields are untouched');
    }

    public function test_filling_an_empty_field_is_a_change_not_a_contradiction(): void
    {
        $this->post('/admin/proposals', ['entity_type' => 'profile', 'values' => ['location' => 'Lalitpur, Nepal']]);

        $this->assertSame('changed', ProposedChange::firstOrFail()->classification);
    }

    public function test_list_fields_are_merged_not_replaced(): void
    {
        $exp = \App\Models\Experience::create(['company' => 'Acme', 'role' => 'Dev', 'start_date' => '2024-01-01', 'highlights' => ['Built APIs'], 'status' => 'published']);

        $this->post('/admin/proposals', ['entity_type' => 'experience', 'values' => ['company' => 'acme', 'highlights' => "Built APIs\nAdded Redis caching"]]);
        $proposal = ProposedChange::firstOrFail();

        $this->assertSame('update', $proposal->action);
        $this->assertSame($exp->id, $proposal->target_id);
        $this->assertSame('changed', $proposal->classification);

        $this->post("/admin/proposals/{$proposal->id}/approve")->assertSessionHasNoErrors();
        $this->assertSame(['Built APIs', 'Added Redis caching'], $exp->fresh()->highlights);
        $this->assertSame('published', $exp->fresh()->status, 'updates keep the existing status');
    }

    public function test_reject_and_edit_proposals(): void
    {
        $this->post('/admin/imports', ['mode' => 'file', 'file' => $this->export()]);
        $link = ProposedChange::where('entity_type', 'social_link')->firstOrFail();

        $this->put("/admin/proposals/{$link->id}", ['values' => ['platform' => 'github', 'label' => 'My GitHub', 'url' => 'https://github.com/someone']])->assertRedirect();
        $this->assertSame('My GitHub', $link->fresh()->proposed['label']);

        $this->post("/admin/proposals/{$link->id}/reject")->assertRedirect();
        $this->assertSame('rejected', $link->fresh()->status);
        $this->assertSame(0, SocialLink::count());

        $this->post('/admin/proposals/dismiss-duplicates')->assertRedirect();
        $this->assertSame(0, ProposedChange::pending()->where('classification', 'duplicate')->count());
    }

    public function test_approval_validates_edited_values(): void
    {
        $this->post('/admin/proposals', ['entity_type' => 'social_link', 'values' => ['platform' => 'github', 'url' => 'https://github.com/x']]);
        $proposal = ProposedChange::firstOrFail();

        // "label" is required on links, so applying fails validation instead of saving a broken record.
        $this->post("/admin/proposals/{$proposal->id}/approve")->assertSessionHasErrors('label');
        $this->assertTrue($proposal->fresh()->isPending());
    }

    public function test_ai_extraction_is_unavailable_without_configuration(): void
    {
        config(['portfolio.ai.provider' => null]);
        $this->post('/admin/imports', ['mode' => 'text', 'text' => 'Human: hi']);
        $import = ConversationImport::firstOrFail();

        $this->get("/admin/imports/{$import->id}")->assertDontSee('Run AI extraction');
        $this->post("/admin/imports/{$import->id}/ai", ['conversations' => [$import->conversations()->first()->id]])->assertForbidden();
    }

    public function test_ai_extraction_job_creates_reviewable_proposals(): void
    {
        config(['portfolio.ai.provider' => 'anthropic', 'portfolio.ai.anthropic.api_key' => 'test-key']);
        $this->app->instance(LlmExtractor::class, new class implements LlmExtractor
        {
            public function name(): string
            {
                return 'fake';
            }

            public function extract(ImportedConversation $conversation, string $portfolioSnapshot): array
            {
                return [new Candidate('project', ['name' => 'Payroll Engine', 'tagline' => 'Tax and salary'], 'I built the payroll engine', 0.9, 'Stated directly', $conversation->id)];
            }
        });

        Queue::fake();
        $this->post('/admin/imports', ['mode' => 'text', 'text' => 'Human: I built the payroll engine']);
        $import = ConversationImport::firstOrFail();
        $this->post("/admin/imports/{$import->id}/ai", ['conversations' => [$import->conversations()->first()->id]])->assertRedirect();
        Queue::assertPushed(ExtractWithAi::class);
        $this->assertSame('ai_queued', $import->fresh()->status);

        (new ExtractWithAi($import->id, [$import->conversations()->first()->id]))->handle(app(LlmExtractor::class), app(\App\Services\Import\ProposalBuilder::class));

        $proposal = ProposedChange::where('extractor', 'llm')->firstOrFail();
        $this->assertSame('project', $proposal->entity_type);
        $this->assertSame(0.9, $proposal->confidence);
        $this->assertContains('llm', $import->fresh()->extractors);
        $this->assertSame(0, \App\Models\Project::count());
    }
}
