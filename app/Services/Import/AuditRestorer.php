<?php

namespace App\Services\Import;

use App\Models\AuditLog;
use App\Support\AuditContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;

/** Reverts one audited change. Technology tags and uploaded files are not part of the audit trail. */
class AuditRestorer
{
    public function restore(AuditLog $log): void
    {
        $class = Relation::getMorphedModel($log->auditable_type);
        if (! $class || $log->restored_at) {
            throw ValidationException::withMessages(['audit' => 'This change cannot be restored.']);
        }

        AuditContext::run('restore', function () use ($log, $class) {
            $model = $class::query()->find($log->auditable_id);

            match ($log->event) {
                'updated' => $model
                    ? $model->forceFill($log->old_values ?? [])->save()
                    : throw ValidationException::withMessages(['audit' => 'The record was deleted since; restore its deletion first.']),
                'deleted' => $model
                    ? throw ValidationException::withMessages(['audit' => 'A record with this ID already exists.'])
                    : (new $class)->forceFill($log->old_values ?? [])->save(),
                'created' => $model?->delete(),
            };
        });

        $log->update(['restored_at' => now()]);
    }
}
