<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A kind of portfolio (Technical & IT, Creative & Design …), managed in Admin → Portfolio types. Decides which
 * profession-specific modules (config portfolio.modules) a portfolio uses, its starting sections and compatible templates.
 * Hybrid combines the modules of the disciplines the person picks (Portfolio::$disciplines).
 */
class PortfolioType extends Model
{
    public const HYBRID = 'hybrid';

    public const DEFAULT = 'technical';

    protected $guarded = ['id'];

    protected $casts = [
        'categories' => 'array',
        'modules' => 'array',
        'sections' => 'array',
        'templates' => 'array',
        'is_active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isHybrid(): bool
    {
        return $this->slug === self::HYBRID;
    }

    /** Types a Hybrid portfolio can combine: every other active type. */
    public static function disciplineOptions(): array
    {
        return static::active()->ordered()->where('slug', '!=', self::HYBRID)->pluck('name', 'slug')->all();
    }

    /** Module keys that still exist in config (a removed module is ignored, never an error). */
    public function moduleKeys(): array
    {
        return array_values(array_intersect((array) $this->modules, array_keys(config('portfolio.modules', []))));
    }

    /** Sample content of the "See a sample" demo (config only). */
    public function sample(): array
    {
        return config("portfolio.types.{$this->slug}.sample") ?? config('portfolio.types.'.self::DEFAULT.'.sample');
    }
}
