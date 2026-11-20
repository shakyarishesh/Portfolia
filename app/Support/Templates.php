<?php

namespace App\Support;

use App\Models\Portfolio;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Template registry (config/portfolio.php → templates). Template keys from the database are only
 * ever looked up in this allow-list; view names are built here, never taken from input.
 *
 * Pages every template renders: home, project, posts (article/note list), post (article/note detail).
 */
final class Templates
{
    public const DEFAULT = 'classic';

    public const PAGES = ['home', 'project', 'posts', 'post'];

    /** Template 1 keeps its original view files. */
    private const CLASSIC_VIEWS = [
        'home' => 'portfolio.index',
        'project' => 'portfolio.project',
        'posts' => 'blog.index',
        'post' => 'blog.show',
    ];

    /** @return array<string, array<string, mixed>> key => metadata */
    public static function all(): array
    {
        return config('portfolio.templates', []);
    }

    /** @return array<string, array<string, mixed>> */
    public static function available(): array
    {
        return array_filter(self::all(), fn ($t) => $t['active'] ?? true);
    }

    /**
     * Templates compatible with a portfolio type (Admin → Portfolio types), in registry order. $keep (e.g. the
     * portfolio's current template) is always included, so an existing choice never disappears.
     */
    public static function forType(?string $type, array $keep = []): array
    {
        $compatible = (array) (\App\Models\PortfolioType::query()->where('slug', (string) $type)->first()?->templates ?? []);
        if ($compatible === []) {
            return self::available();
        }

        return array_filter(self::available(), fn ($k) => in_array($k, $compatible, true) || in_array($k, $keep, true), ARRAY_FILTER_USE_KEY);
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::available());
    }

    public static function meta(string $key): array
    {
        return self::available()[$key] ?? self::available()[self::DEFAULT];
    }

    public static function view(string $key, string $page): string
    {
        abort_unless(in_array($page, self::PAGES, true), 404);
        $key = self::exists($key) ? $key : self::DEFAULT;

        return $key === self::DEFAULT ? self::CLASSIC_VIEWS[$page] : "portfolios.templates.{$key}.{$page}";
    }

    /** Template to render for this request: an authorised preview override from the session, else the live one. */
    public static function active(Portfolio $portfolio): string
    {
        $override = session(self::previewKey($portfolio));
        if ($override && self::exists($override) && Gate::allows('preview', $portfolio)) {
            return $override;
        }

        return self::exists((string) $portfolio->template) ? $portfolio->template : self::DEFAULT;
    }

    public static function previewKey(Portfolio $portfolio): string
    {
        return "portfolio_preview.{$portfolio->id}";
    }

    /** Settings schema of a template: its own settings plus the ones every template shares (config portfolio.shared_settings). */
    public static function fields(string $key): array
    {
        $shared = config('portfolio.shared_settings', []);
        foreach (self::meta($key)['shared_defaults'] ?? [] as $name => $default) {
            $shared[$name]['default'] = $default; // e.g. classic starts with the cursor trail on
        }

        return (self::meta($key)['settings'] ?? []) + $shared;
    }

    /** @return list<string> names of the shared settings (stored under template_settings._shared) */
    public static function sharedKeys(): array
    {
        return array_keys(config('portfolio.shared_settings', []));
    }

    /** Welcome-intro words from the settings' "intro_words" (one per line), else the default list. */
    public static function introWords(array $settings): array
    {
        $words = array_filter(array_map(fn ($w) => mb_substr(trim($w), 0, 40), preg_split('/\R/', (string) ($settings['intro_words'] ?? ''))), 'strlen');

        return array_slice(array_values($words) ?: config('portfolio.intro_words'), 0, 20);
    }

    /** Stored values merged over defaults; unknown keys dropped. */
    public static function settings(string $key, array $stored): array
    {
        $out = [];
        foreach (self::fields($key) as $name => $field) {
            $value = $stored[$name] ?? $field['default'] ?? null;
            if (($field['type'] ?? null) === 'select' && ! array_key_exists((string) $value, $field['options'])) {
                $value = $field['default'];
            }
            $out[$name] = ($field['type'] ?? null) === 'boolean' ? (bool) $value : $value;
        }

        return $out;
    }

    /** Validation rules for a template's settings form, prefixed "settings.". */
    public static function rules(string $key): array
    {
        $rules = ['settings' => ['nullable', 'array']];
        foreach (self::fields($key) as $name => $field) {
            $rules["settings.{$name}"] = match ($field['type']) {
                'boolean' => ['nullable', 'boolean'],
                'select' => ['nullable', Rule::in(array_keys($field['options']))],
                'textarea' => ['nullable', 'string', 'max:'.($field['max'] ?? 2000)],
                default => ['nullable', 'string', 'max:'.($field['max'] ?? 160)],
            };
        }

        return $rules;
    }

    /** Validated "settings" input → normalised array to store under template_settings[$key]. */
    public static function clean(string $key, array $input): array
    {
        $out = [];
        foreach (self::fields($key) as $name => $field) {
            $out[$name] = $field['type'] === 'boolean' ? ! empty($input[$name]) : ($input[$name] ?? $field['default'] ?? null);
        }

        return $out;
    }
}
