<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Skill extends Model
{
    use Auditable, Publishable;

    public const LEVELS = [1 => 'Familiar', 2 => 'Working knowledge', 3 => 'Strong', 4 => 'Expert'];

    protected $guarded = ['id'];

    protected $casts = ['level' => 'integer'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillCategory::class, 'skill_category_id');
    }

    public function levelLabel(): ?string
    {
        return self::LEVELS[$this->level] ?? null;
    }
}
