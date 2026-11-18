<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use \App\Models\Concerns\BelongsToPortfolio;

    protected $guarded = ['id'];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'restored_at' => 'datetime',
    ];

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function proposedChange(): BelongsTo
    {
        return $this->belongsTo(ProposedChange::class);
    }

    /** Field => [old, new] for display. */
    public function diff(): array
    {
        $keys = array_unique(array_merge(array_keys($this->old_values ?? []), array_keys($this->new_values ?? [])));

        return collect($keys)->reject(fn ($k) => $k === 'id')
            ->mapWithKeys(fn ($k) => [$k => [$this->old_values[$k] ?? null, $this->new_values[$k] ?? null]])
            ->all();
    }
}
