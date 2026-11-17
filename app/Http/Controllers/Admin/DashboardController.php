<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Resources;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Models\Post;
use App\Models\Profile;
use App\Models\ProposedChange;
use App\Services\Portfolio\ProfileCompleteness;

class DashboardController extends Controller
{
    public function __invoke(ProfileCompleteness $completeness)
    {
        $stats = collect(Resources::ALL)->map(fn ($resource) => [
            'resource' => $resource,
            'counts' => $resource::newModel()->newQuery()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);

        return view('admin.dashboard', [
            'stats' => $stats,
            'completeness' => $completeness->check(Profile::query()->with(['avatar', 'resume'])->firstOrFail()),
            'pendingProposals' => ProposedChange::pending()->count(),
            'unreadMessages' => ContactMessage::whereNull('read_at')->whereNull('archived_at')->count(),
            'traffic' => [
                'views' => PageView::query()->where('created_at', '>=', now()->subDays(30))->whereIn('kind', ['page', 'project', 'post'])->count(),
                'downloads' => PageView::query()->where('created_at', '>=', now()->subDays(30))->where('kind', 'resume')->count(),
                'messages' => ContactMessage::query()->where('created_at', '>=', now()->subDays(30))->count(),
                'articles' => Post::live()->count(),
                'top' => PageView::query()->where('created_at', '>=', now()->subDays(30))->whereIn('kind', ['project', 'post'])
                    ->selectRaw('kind, subject, COUNT(*) as n')->groupBy('kind', 'subject')->orderByDesc('n')->limit(5)->get(),
            ],
            'needsReview' => $stats->sum(fn ($s) => $s['counts']['needs_review'] ?? 0),
            'recent' => AuditLog::query()->with('user')->latest('id')->limit(8)->get(),
        ]);
    }
}
