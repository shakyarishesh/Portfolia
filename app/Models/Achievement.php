<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achievement extends Model
{
    use Auditable, Publishable;

    public const KINDS = ['highlight' => 'Engineering highlight', 'milestone' => 'Career milestone'];

    protected $guarded = ['id'];

    protected $casts = ['details' => 'array'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
