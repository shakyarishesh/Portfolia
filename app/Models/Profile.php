<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Profile extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = [
        'typed_phrases' => 'array',
        'show_phone' => 'boolean',
        'open_to_work' => 'boolean',
        'contact_form_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return static::query()->firstOrFail();
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'model');
    }

    public function avatar(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')->ofMany(['id' => 'max'], fn ($q) => $q->where('collection', 'avatar'));
    }

    public function resume(): MorphOne
    {
        return $this->morphOne(Media::class, 'model')->ofMany(['id' => 'max'], fn ($q) => $q->where('collection', 'resume')->where('is_active', true));
    }

    /** Every uploaded CV, newest first. */
    public function resumes(): MorphMany
    {
        return $this->media()->where('collection', 'resume')->latest('id');
    }

    public function avatarSrc(): ?string
    {
        return $this->avatar?->url() ?? $this->avatar_url;
    }

    /** @return array<int, string> */
    public function aboutParagraphs(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', (string) $this->about))));
    }
}
