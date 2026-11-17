<?php

namespace App\Models\Concerns;

use App\Models\Technology;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasTechnologies
{
    public function technologies(): MorphToMany
    {
        return $this->morphToMany(Technology::class, 'technologable')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order');
    }

    /** @param  array<int, string>  $names */
    public function syncTechnologies(array $names): void
    {
        $names = array_values(array_unique(array_filter(array_map('trim', $names))));
        $sync = [];
        foreach ($names as $i => $name) {
            $tech = Technology::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first()
                ?? Technology::create(['name' => $name]);
            $sync[$tech->id] = ['sort_order' => $i];
        }
        $this->technologies()->sync($sync);
    }

    public function technologyList(): string
    {
        return $this->technologies->pluck('name')->implode(', ');
    }
}
