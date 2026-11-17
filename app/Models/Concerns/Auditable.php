<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Support\AuditContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

// Records every create/update/delete in audit_logs so changes can be reviewed and restored.
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn (Model $m) => $m->writeAudit('created', null, $m->auditValues($m->getAttributes())));
        static::updated(function (Model $m) {
            $changed = $m->auditValues($m->getChanges());
            if ($changed) {
                $m->writeAudit('updated', Arr::only($m->auditValues($m->getOriginal()), array_keys($changed)), $changed);
            }
        });
        static::deleted(fn (Model $m) => $m->writeAudit('deleted', $m->auditValues($m->getOriginal()), null));
    }

    public function auditLogs()
    {
        return $this->morphMany(AuditLog::class, 'auditable')->latest('id');
    }

    protected function auditValues(array $attributes): array
    {
        $values = Arr::except($attributes, ['created_at', 'updated_at']);

        // Raw JSON columns come back as strings; store them decoded so diffs are readable.
        foreach ($this->getCasts() as $key => $cast) {
            if (in_array($cast, ['array', 'json'], true) && is_string($values[$key] ?? null)) {
                $values[$key] = json_decode($values[$key], true);
            }
        }

        return $values;
    }

    protected function writeAudit(string $event, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
            'source' => AuditContext::source(),
            'proposed_change_id' => AuditContext::proposedChangeId(),
        ]);
    }
}
