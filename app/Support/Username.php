<?php

namespace App\Support;

use App\Models\Portfolio;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/** Public portfolio usernames: /{username}. */
final class Username
{
    /** 3–40 chars, lower-case letters, digits and single hyphens, no leading/trailing hyphen. */
    public const PATTERN = '[a-z0-9](?:[a-z0-9]|-(?=[a-z0-9])){2,39}';

    /** Route constraint: also accepts upper case so /Rishesh can redirect to /rishesh. */
    public const ROUTE_PATTERN = '[A-Za-z0-9][A-Za-z0-9-]{1,38}[A-Za-z0-9]';

    public const RESERVED = [
        'admin', 'api', 'login', 'logout', 'register', 'dashboard', 'settings', 'profile', 'projects', 'blog', 'notes',
        'contact', 'resume', 'assets', 'storage', 'system', 'preview', 'playground', 'assistant', 'sitemap', 'robots',
        'up', 'build', 'templates', 'admin-assets', 'favicon', 'www', 'mail', 'root', 'about', 'help', 'support', 'static',
        'public', 'user', 'users', 'portfolio', 'portfolios', 'null', 'undefined',
    ];

    /** Static reserved words + first path segment of every registered route + top-level files/dirs in public/. */
    public static function reserved(): array
    {
        $fromRoutes = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => mb_strtolower(explode('/', trim($r->uri(), '/'))[0]))
            ->reject(fn ($s) => $s === '' || str_starts_with($s, '{'));
        $fromPublic = collect(File::exists(public_path()) ? scandir(public_path()) : [])
            ->reject(fn ($f) => $f[0] === '.')
            ->map(fn ($f) => mb_strtolower(pathinfo($f, PATHINFO_FILENAME)));

        return collect(self::RESERVED)->merge($fromRoutes)->merge($fromPublic)->unique()->values()->all();
    }

    public static function isReserved(string $username): bool
    {
        return in_array(mb_strtolower($username), self::reserved(), true);
    }

    /** Validation rules; input is lower-cased by the caller (or Portfolio::saving). */
    public static function rules(?Portfolio $ignore = null): array
    {
        return [
            'required', 'string', 'min:3', 'max:40', 'regex:/^'.self::PATTERN.'$/',
            Rule::unique('portfolios', 'username')->ignore($ignore?->id),
            function (string $attribute, mixed $value, \Closure $fail) {
                if (self::isReserved((string) $value)) {
                    $fail('That username is reserved by the system. Please choose another.');
                }
            },
        ];
    }
}
