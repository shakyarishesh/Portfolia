<?php

namespace App\Http\Controllers;

use App\Models\Portfolio;
use App\Models\Post;
use App\Models\Project;
use App\Models\Section;
use App\Support\CurrentPortfolio;

class SeoController extends Controller
{
    /** Every published portfolio's public pages. Drafts/disabled/archived never appear. */
    public function sitemap()
    {
        $urls = collect();
        foreach (Portfolio::query()->where('status', 'published')->orderBy('id')->get() as $portfolio) {
            CurrentPortfolio::using($portfolio, function () use ($urls, $portfolio) {
                $urls->push(['loc' => route('home'), 'lastmod' => $portfolio->updated_at]);
                foreach (Project::published()->ordered()->get(['slug', 'updated_at']) as $p) {
                    $urls->push(['loc' => route('projects.show', ['project' => $p]), 'lastmod' => $p->updated_at]);
                }
                $visible = Section::query()->where('is_visible', true)->pluck('key');
                foreach (['article' => 'blog', 'note' => 'notes'] as $kind => $section) {
                    if (! $visible->contains($section)) {
                        continue;
                    }
                    $posts = Post::live()->kind($kind)->newest()->get(['slug', 'kind', 'updated_at']);
                    if ($posts->isNotEmpty()) {
                        $urls->push(['loc' => route($section === 'blog' ? 'blog.index' : 'notes.index'), 'lastmod' => $posts->max('updated_at')]);
                    }
                    foreach ($posts as $p) {
                        $urls->push(['loc' => $p->url(), 'lastmod' => $p->updated_at]);
                    }
                }
            });
        }

        return response()->view('seo.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    public function robots()
    {
        $body = "User-agent: *\nDisallow: /admin\n\nSitemap: ".route('sitemap')."\n";

        return response($body)->header('Content-Type', 'text/plain');
    }
}
