<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

// Draft / needs-review / published status plus manual ordering.
trait Publishable
{
    public function scopePublished(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('status'), 'published');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn('sort_order'))->orderBy($this->qualifyColumn('id'));
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }
}
