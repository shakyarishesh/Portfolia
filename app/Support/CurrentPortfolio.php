<?php

namespace App\Support;

use App\Models\Portfolio;
use Illuminate\Support\Facades\URL;

/**
 * The portfolio this request (or job) works on. Bound as a container singleton, set by the
 * username route binding (public), ResolveAdminPortfolio (admin) or explicitly in jobs/seeders.
 * Every BelongsToPortfolio model is filtered by it.
 */
class CurrentPortfolio
{
    private ?Portfolio $portfolio = null;

    public static function instance(): self
    {
        return app(self::class);
    }

    public static function get(): ?Portfolio
    {
        return self::instance()->portfolio;
    }

    public static function id(): ?int
    {
        return self::instance()->portfolio?->id;
    }

    public static function set(?Portfolio $portfolio): void
    {
        self::instance()->portfolio = $portfolio;
        // route('projects.show', $p) etc. fill {username} from here — the one place public URLs get their prefix.
        URL::defaults(['username' => $portfolio?->username]);
    }

    /** Run $callback with $portfolio as current, then restore the previous one. */
    public static function using(Portfolio $portfolio, callable $callback): mixed
    {
        $previous = self::get();
        self::set($portfolio);

        try {
            return $callback();
        } finally {
            self::set($previous);
        }
    }
}
