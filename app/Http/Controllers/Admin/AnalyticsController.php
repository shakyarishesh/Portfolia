<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public const RANGES = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days', 365 => 'Last 12 months'];

    public function __invoke(Request $request)
    {
        $days = array_key_exists((int) $request->query('days'), self::RANGES) ? (int) $request->query('days') : 30;
        $from = now()->startOfDay()->subDays($days - 1);
        $views = fn () => PageView::query()->where('created_at', '>=', $from)->whereIn('kind', ['page', 'project', 'post']);

        $pageViews = $views()->count();
        // The visitor hash rotates daily, so this counts visitor-days rather than people.
        $visitors = $views()->distinct()->count('visitor');
        $messages = ContactMessage::query()->where('created_at', '>=', $from)->count();

        $perDay = $views()->selectRaw('DATE(created_at) as d, COUNT(*) as n')->groupBy('d')->pluck('n', 'd');
        $series = collect(range(0, $days - 1))->map(function ($i) use ($from, $perDay) {
            $date = $from->copy()->addDays($i)->toDateString();

            return ['date' => $date, 'n' => (int) ($perDay[$date] ?? 0)];
        });

        $top = fn (string $kind) => PageView::query()->where('created_at', '>=', $from)->where('kind', $kind)
            ->selectRaw('subject, COUNT(*) as n')->groupBy('subject')->orderByDesc('n')->limit(8)->pluck('n', 'subject');

        return view('admin.analytics', [
            'days' => $days,
            'ranges' => self::RANGES,
            'cards' => [
                ['Page views', $pageViews],
                ['Daily unique visitors (sum)', $visitors],
                ['CV downloads', PageView::query()->where('created_at', '>=', $from)->where('kind', 'resume')->count()],
                ['Contact messages', $messages],
                ['Contact conversion', $visitors ? round($messages / $visitors * 100, 1).'%' : '—'],
            ],
            'series' => $series,
            'max' => max(1, $series->max('n')),
            'projects' => $top('project'),
            'projectNames' => Project::query()->pluck('name', 'slug'),
            'posts' => $top('post'),
            'postNames' => Post::query()->pluck('title', 'slug'),
            'pages' => $top('page'),
            'referrers' => PageView::query()->where('created_at', '>=', $from)->whereNotNull('referrer_host')
                ->selectRaw('referrer_host, COUNT(*) as n')->groupBy('referrer_host')->orderByDesc('n')->limit(8)->pluck('n', 'referrer_host'),
            'from' => $from,
        ]);
    }
}
