<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SkillCategory extends Model
{
    use \App\Models\Concerns\BelongsToPortfolio;

    use Auditable, Publishable;

    protected $guarded = ['id'];

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }
}
