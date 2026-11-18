<?php

namespace App\Http\Middleware;

use App\Models\Portfolio;
use App\Support\CurrentPortfolio;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin content screens edit one portfolio at a time: the one chosen with the switcher (session),
 * else the oldest. Runs before route-model binding (see bootstrap/app.php priority) so bound
 * records are already scoped — a record id from another portfolio is a 404.
 */
class ResolveAdminPortfolio
{
    public const SESSION_KEY = 'admin_portfolio_id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->session()->get(self::SESSION_KEY);
        $portfolio = ($id ? Portfolio::query()->find($id) : null) ?? Portfolio::query()->orderBy('id')->first();

        if (! $portfolio) {
            // Nothing to edit yet: only portfolio management makes sense.
            // Non-admins fall through so `can:admin` (which runs after this middleware) still answers 403.
            if (! $request->routeIs('admin.portfolios.*') && Gate::allows('admin')) {
                return redirect()->route('admin.portfolios.create')->with('success', 'Create a portfolio first.');
            }
        } else {
            CurrentPortfolio::set($portfolio);
        }

        return $next($request);
    }
}
