<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasTechnologies;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Project extends Model
{
    use \App\Models\Concerns\BelongsToPortfolio;

    use Auditable, HasTechnologies, Publishable;

    protected $guarded = ['id'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
        'is_featured' => 'boolean',
        'features' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model')->orderBy('sort_order')->orderBy('id');
    }

    public function thumbnail(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')->ofMany(['id' => 'max'], fn ($q) => $q->where('collection', 'thumbnail'));
    }

    public function screenshots(): MorphMany
    {
        return $this->media()->where('collection', 'screenshot');
    }

    public function period(): string
    {
        $end = $this->is_current ? 'Present' : ($this->end_date?->format('M Y') ?? '');

        return trim($this->start_date?->format('M Y').' – '.$end, ' –');
    }
}
