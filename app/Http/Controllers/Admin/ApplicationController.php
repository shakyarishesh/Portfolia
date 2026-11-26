<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Portfolio;
use App\Services\Portfolio\PortfolioData;
use App\Support\CurrentPortfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Review portfolios submitted through the public /create form: inspect, approve (publish) or reject. */
class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('viewAny', Portfolio::class);
        $status = $request->query('status') === 'rejected' ? 'rejected' : 'pending';

        return view('admin.applications.index', [
            'status' => $status,
            'counts' => Portfolio::query()->whereIn('status', ['pending', 'rejected'])->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            // Profiles belong to their own portfolio, not the one the admin is editing.
            'applications' => Portfolio::query()->where('status', $status)->with(['profile' => fn ($q) => $q->withoutGlobalScope('portfolio')])
                ->latest()->latest('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function show(Portfolio $portfolio, PortfolioData $data)
    {
        Gate::authorize('update', $portfolio);

        return view('admin.applications.show', ['portfolio' => $portfolio] + CurrentPortfolio::using($portfolio, fn () => $data->get()));
    }

    public function approve(Portfolio $portfolio)
    {
        Gate::authorize('update', $portfolio);
        if (! in_array($portfolio->status, ['pending', 'rejected'], true)) {
            return $this->notOpen($portfolio);
        }
        $portfolio->update(['status' => 'published', 'published_at' => now()]);

        return redirect()->route('admin.applications.index')->with('success', "Approved “{$portfolio->name}” — it is now live at {$portfolio->url()}");
    }

    public function reject(Portfolio $portfolio)
    {
        Gate::authorize('update', $portfolio);
        if ($portfolio->status !== 'pending') {
            return $this->notOpen($portfolio);
        }
        $portfolio->update(['status' => 'rejected']);

        return redirect()->route('admin.applications.index')->with('success', "Rejected “{$portfolio->name}”. Its data is kept; delete it from Portfolios if it is not needed.");
    }

    private function notOpen(Portfolio $portfolio)
    {
        return redirect()->route('admin.applications.index')
            ->withErrors(['status' => "“{$portfolio->name}” is ".strtolower(Portfolio::STATUSES[$portfolio->status] ?? $portfolio->status).' — nothing to do.']);
    }
}
