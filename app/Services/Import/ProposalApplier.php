<?php

namespace App\Services\Import;

use App\Admin\ProfileForm;
use App\Admin\Resources;
use App\Models\Profile;
use App\Models\ProposedChange;
use App\Models\SkillCategory;
use App\Models\User;
use App\Support\AuditContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Applies an approved proposal through the same validation as the admin forms, with an audit trail. */
class ProposalApplier
{
    /**
     * @param  array|null  $edited  proposed values after admin edits (comparable shape)
     * @param  string  $newStatus  status for records created from this proposal
     *
     * @throws ValidationException
     */
    public function approve(ProposedChange $change, User $reviewer, ?array $edited = null, string $newStatus = 'draft', bool $confirmOverwrite = false): void
    {
        if (! $change->isPending()) {
            throw ValidationException::withMessages(['proposal' => 'This proposal was already reviewed.']);
        }
        if ($change->classification === 'contradiction' && ! $confirmOverwrite) {
            throw ValidationException::withMessages(['confirm_overwrite' => 'This proposal changes values you already have. Tick "overwrite existing values" to apply it.']);
        }

        $values = $edited ?? $change->proposed;

        AuditContext::run('import', function () use ($change, $reviewer, $values, $newStatus) {
            DB::transaction(function () use ($change, $reviewer, $values, $newStatus) {
                $change->entity_type === 'profile'
                    ? $this->applyProfile($values)
                    : $this->applyResource($change, $values, $newStatus);

                $change->forceFill([
                    'proposed' => $values,
                    'status' => 'applied',
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => now(),
                ])->save();
            });
        }, $change->id);
    }

    public function reject(ProposedChange $change, User $reviewer): void
    {
        if ($change->isPending()) {
            $change->forceFill(['status' => 'rejected', 'reviewed_by' => $reviewer->id, 'reviewed_at' => now()])->save();
        }
    }

    private function applyProfile(array $values): void
    {
        $profile = Profile::query()->firstOrFail();
        $data = array_merge(ProfileForm::formValues($profile), $this->toFormShape($values, ProfileForm::fields()));
        $validated = Validator::make($data, ProfileForm::rules())->validate();
        ProfileForm::save($profile, $validated);
    }

    private function applyResource(ProposedChange $change, array $values, string $newStatus): void
    {
        $resource = Resources::byKey($change->entity_type);

        if (isset($values['skill_category']) && empty($values['skill_category_id'])) {
            $name = trim($values['skill_category']);
            $category = SkillCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                ?? SkillCategory::create(['name' => $name, 'status' => 'draft', 'sort_order' => (int) SkillCategory::max('sort_order') + 1,
                    'source_note' => 'Created while applying an imported proposal.']);
            $values['skill_category_id'] = $category->id;
        }
        unset($values['skill_category']);

        $target = $change->target_id ? $resource::query()->find($change->target_id) : null;
        if ($change->action === 'update' && ! $target) {
            throw ValidationException::withMessages(['proposal' => 'The record this proposal updates no longer exists.']);
        }

        $base = $resource::formValues($target);
        if (! $target) {
            $base['status'] = $newStatus;
            $base['source_note'] = trim('Imported from a conversation (proposal #'.$change->id.'). '.($change->source_excerpt ? 'Excerpt: '.mb_substr($change->source_excerpt, 0, 500) : ''));
        }
        $data = array_merge($base, $this->toFormShape($values, $resource::fields()));

        $validated = Validator::make($data, $resource::rules())->validate();
        $record = $resource::save($target ?? $resource::newModel(), $validated);
        $change->target_id = $record->getKey();
    }

    /** Arrays (lines/tags) back to the textarea/comma format the form rules expect. */
    private function toFormShape(array $values, array $fields): array
    {
        $out = [];
        foreach ($values as $name => $value) {
            $type = $fields[$name]['type'] ?? null;
            $out[$name] = match ($type) {
                'lines' => is_array($value) ? implode("\n", $value) : $value,
                'tags' => is_array($value) ? implode(', ', $value) : $value,
                'checkbox' => (bool) $value,
                default => $value,
            };
        }

        return $out;
    }
}
