<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Support\Templates;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Preview = the real public pages, viewed by someone allowed to preview, optionally with a
 * not-yet-published template held in their session. Visitors never see the override.
 */
class PreviewController extends Controller
{
    public function start(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('preview', $portfolio);
        $template = $request->query('template', $portfolio->draft_template ?? $portfolio->template);
        abort_unless(is_string($template) && Templates::exists($template), 404);

        $request->session()->put(Templates::previewKey($portfolio), $template);

        return redirect($portfolio->url());
    }

    public function exit(Request $request, Portfolio $portfolio)
    {
        Gate::authorize('preview', $portfolio);
        $request->session()->forget(Templates::previewKey($portfolio));

        return redirect()->route('admin.portfolios.edit', $portfolio);
    }
}
