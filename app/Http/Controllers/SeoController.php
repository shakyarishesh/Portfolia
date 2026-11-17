<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Project;

class SeoController extends Controller
{
    public function sitemap()
    {
        $urls = collect([['loc' => url('/'), 'lastmod' => null]]);
        $urls->push(['loc' => route('playground'), 'lastmod' => null]);
        foreach (Project::published()->ordered()->get(['slug', 'updated_at']) as $p) {
            $urls->push(['loc' => route('projects.show', $p), 'lastmod' => $p->updated_at]);
        }
        $posts = Post::live()->newest()->get(['slug', 'updated_at']);
        if ($posts->isNotEmpty()) {
            $urls->push(['loc' => route('blog.index'), 'lastmod' => $posts->max('updated_at')]);
        }
        foreach ($posts as $p) {
            $urls->push(['loc' => route('blog.show', $p), 'lastmod' => $p->updated_at]);
        }

        return response()->view('seo.sitemap', ['urls' => $urls])->header('Content-Type', 'application/xml');
    }

    public function robots()
    {
        $body = "User-agent: *\nDisallow: /admin\n\nSitemap: ".route('sitemap')."\n";

        return response($body)->header('Content-Type', 'text/plain');
    }
}
