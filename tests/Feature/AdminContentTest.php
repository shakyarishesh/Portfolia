<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Experience;
use App\Models\Skill;
use App\Models\SkillCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->profile();
        $this->admin();
    }

    private function experiencePayload(array $overrides = []): array
    {
        return $overrides + [
            'company' => 'Acme', 'role' => 'Backend Developer', 'employment_type' => 'full_time',
            'start_date' => '2024-01-01', 'highlights' => "Built APIs\nCut query time", 'technologies' => 'Laravel, MySQL',
            'status' => 'draft', 'is_current' => '1',
        ];
    }

    public function test_every_admin_page_renders(): void
    {
        foreach (['/admin', '/admin/profile', '/admin/sections', '/admin/imports', '/admin/proposals', '/admin/audit', '/admin/messages'] as $url) {
            $this->get($url)->assertOk();
        }
        foreach (\App\Admin\Resources::ALL as $resource) {
            $this->get("/admin/".$resource::SLUG)->assertOk();
            $this->get("/admin/".$resource::SLUG."/create")->assertOk();
        }
        $this->get('/admin/not-a-resource')->assertNotFound();
    }

    public function test_create_update_and_delete_with_audit_trail(): void
    {
        $this->post('/admin/experiences', $this->experiencePayload())->assertRedirect();
        $exp = Experience::firstOrFail();
        $this->assertSame(['Built APIs', 'Cut query time'], $exp->highlights);
        $this->assertSame(['Laravel', 'MySQL'], $exp->technologies->pluck('name')->all());
        $this->assertTrue($exp->is_current);

        $this->put("/admin/experiences/{$exp->id}", $this->experiencePayload(['role' => 'Senior Backend Developer', 'is_current' => '0']))->assertRedirect();
        $this->assertSame('Senior Backend Developer', $exp->fresh()->role);
        $this->assertFalse($exp->fresh()->is_current);

        $update = AuditLog::where('event', 'updated')->firstOrFail();
        $this->assertSame('Backend Developer', $update->old_values['role']);
        $this->assertSame('Senior Backend Developer', $update->new_values['role']);
        $this->assertSame('admin', $update->source);

        $this->delete("/admin/experiences/{$exp->id}")->assertRedirect('/admin/experiences');
        $this->assertNull(Experience::find($exp->id));
        $this->assertDatabaseHas('audit_logs', ['event' => 'deleted', 'auditable_id' => $exp->id]);
    }

    public function test_server_side_validation(): void
    {
        $this->post('/admin/experiences', $this->experiencePayload(['company' => '', 'company_url' => 'javascript:alert(1)', 'end_date' => '2023-01-01']))
            ->assertSessionHasErrors(['company', 'company_url', 'end_date']);
        $this->post('/admin/experiences', $this->experiencePayload(['status' => 'live']))->assertSessionHasErrors('status');
        $this->assertSame(0, Experience::count());
    }

    public function test_publish_unpublish_and_reorder(): void
    {
        $cat = SkillCategory::create(['name' => 'Backend', 'status' => 'published']);
        $a = Skill::create(['skill_category_id' => $cat->id, 'name' => 'A', 'sort_order' => 1, 'status' => 'draft']);
        $b = Skill::create(['skill_category_id' => $cat->id, 'name' => 'B', 'sort_order' => 2, 'status' => 'draft']);

        $this->post("/admin/skills/{$a->id}/status", ['status' => 'published'])->assertRedirect();
        $this->assertSame('published', $a->fresh()->status);
        $this->post("/admin/skills/{$a->id}/status", ['status' => 'bogus'])->assertSessionHasErrors('status');

        $this->post("/admin/skills/{$b->id}/move", ['direction' => 'up'])->assertRedirect();
        $this->assertSame(['B', 'A'], Skill::ordered()->pluck('name')->all());
    }

    public function test_deleted_record_can_be_restored_from_audit_log(): void
    {
        $this->post('/admin/experiences', $this->experiencePayload());
        $exp = Experience::firstOrFail();
        $this->delete("/admin/experiences/{$exp->id}");

        $log = AuditLog::where('event', 'deleted')->firstOrFail();
        $this->post("/admin/audit/{$log->id}/restore")->assertRedirect();

        $restored = Experience::findOrFail($exp->id);
        $this->assertSame('Acme', $restored->company);
        $this->assertSame(['Built APIs', 'Cut query time'], $restored->highlights);
        $this->assertNotNull($log->fresh()->restored_at);
        $this->post("/admin/audit/{$log->id}/restore")->assertSessionHasErrors('audit');
    }

    public function test_updated_values_can_be_restored(): void
    {
        $this->post('/admin/experiences', $this->experiencePayload());
        $exp = Experience::firstOrFail();
        $this->put("/admin/experiences/{$exp->id}", $this->experiencePayload(['role' => 'Changed']));

        $log = AuditLog::where('event', 'updated')->firstOrFail();
        $this->post("/admin/audit/{$log->id}/restore");

        $this->assertSame('Backend Developer', $exp->fresh()->role);
        $this->assertSame('restore', AuditLog::latest('id')->first()->source);
    }

    public function test_profile_and_sections_are_editable(): void
    {
        $this->put('/admin/profile', ['full_name' => 'New Name', 'headline' => 'Engineer', 'typed_phrases' => "APIs.\nTools.", 'email' => 'not-an-email'])
            ->assertSessionHasErrors('email');
        $this->put('/admin/profile', ['full_name' => 'New Name', 'headline' => 'Engineer', 'typed_phrases' => "APIs.\nTools."])->assertRedirect();
        $this->assertSame(['APIs.', 'Tools.'], \App\Models\Profile::first()->typed_phrases);

        $section = \App\Models\Section::where('key', 'skills')->firstOrFail();
        $this->put("/admin/sections/{$section->id}", ['nav_label' => 'Stack', 'title' => 'Toolbox', 'is_visible' => '0'])->assertRedirect();
        $this->assertFalse($section->fresh()->is_visible);
        $this->assertSame('Stack', $section->fresh()->nav_label);
    }
}
