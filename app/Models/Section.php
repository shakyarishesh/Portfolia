<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    use Auditable;

    public const KEYS = ['home', 'experience', 'projects', 'skills', 'education', 'contact'];

    protected $guarded = ['id'];

    protected $casts = ['is_visible' => 'boolean'];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
