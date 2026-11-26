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
        'details' => 'array',
    ];

    /** Profession-specific fields (config portfolio.project_details) kept in the `details` JSON column. */
    public static function detailKeys(): array
    {
        return array_keys(config('portfolio.project_details', []));
    }

    // $project->client etc. read and write `details`, so forms, imports and views treat them like columns.
    // Keys a form doesn't show are left alone, so switching portfolio type never loses them.
    public function getAttribute($key)
    {
        if (is_string($key) && in_array($key, self::detailKeys(), true)) {
            return $this->details[$key] ?? null;
        }

        return parent::getAttribute($key);
    }

    // Laravel only mass-assigns real columns; detail keys are virtual columns of `details`.
    protected function isGuardableColumn($key)
    {
        return in_array($key, self::detailKeys(), true) || parent::isGuardableColumn($key);
    }

    public function setAttribute($key, $value)
    {
        if (is_string($key) && in_array($key, self::detailKeys(), true)) {
            $details = (array) $this->details;
            if ($value === null || $value === '' || $value === []) {
                unset($details[$key]);
            } else {
                $details[$key] = $value;
            }

            return parent::setAttribute('details', $details ?: null);
        }

        return parent::setAttribute($key, $value);
    }

    /** "Conversion rate: 4.2%" lines → [label, value, percent|null]; lines without a colon are skipped. */
    public function metricRows(): array
    {
        $rows = [];
        foreach ((array) $this->metrics as $line) {
            if (! str_contains((string) $line, ':')) {
                continue;
            }
            [$label, $value] = array_map('trim', explode(':', (string) $line, 2));
            preg_match('/^([+-]?\d+(?:\.\d+)?)\s*%$/', $value, $m);
            $rows[] = [$label, $value, isset($m[1]) ? max(0, min(100, abs((float) $m[1]))) : null];
        }

        return $rows;
    }

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

    /** Supporting documents (PDF), shown as downloads on the project page. */
    public function documents(): MorphMany
    {
        return $this->media()->where('collection', 'document');
    }

    public function period(): string
    {
        $end = $this->is_current ? 'Present' : ($this->end_date?->format('M Y') ?? '');

        return trim($this->start_date?->format('M Y').' – '.$end, ' –');
    }
}
