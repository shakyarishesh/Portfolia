<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\Publishable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Post extends Model
{
    use \App\Models\Concerns\BelongsToPortfolio;

    use Auditable, Publishable;

    protected $guarded = ['id'];

    protected $casts = ['tags' => 'array', 'published_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Published and not scheduled for later. */
    public function scopeLive(Builder $query): Builder
    {
        return $query->published()->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function scopeNewest(Builder $query): Builder
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function scopeKind(Builder $query, string $kind): Builder
    {
        return $query->where($this->qualifyColumn('kind'), $kind);
    }

    public function isNote(): bool
    {
        return $this->kind === 'note';
    }

    /** Public URL (articles under /blog, notes under /notes) of the current portfolio. */
    public function url(): string
    {
        return route($this->isNote() ? 'notes.show' : 'blog.show', ['post' => $this]);
    }

    public function isLive(): bool
    {
        return $this->isPublished() && $this->published_at?->lte(now());
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function cover(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')->ofMany(['id' => 'max'], fn ($q) => $q->where('collection', 'cover'));
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body)) / 220));
    }
}
