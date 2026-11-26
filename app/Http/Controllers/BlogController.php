<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use App\Models\Portfolio;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Section;
use App\Support\Markdown;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        return $this->list($request, 'article');
    }

    public function notes(Request $request)
    {
        return $this->list($request, 'note');
    }

    /** Articles (dated, /blog) and notes (evergreen, /notes) share the list page; each needs its section switched on. */
    private function list(Request $request, string $kind)
    {
        abort_unless(self::enabled($kind), 404);
        $q = trim((string) $request->query('q'));
        $tag = (string) $request->query('tag');
        $category = (string) $request->query('category');

        $posts = Post::live()->kind($kind)->with('cover')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('title', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('excerpt', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('body', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->when($tag !== '', fn ($query) => $query->whereJsonContains('tags', $tag))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->when($kind === 'note', fn ($query) => $query->reorder()->orderBy('title'))
            ->when($kind === 'article', fn ($query) => $query->newest())
            ->paginate($kind === 'note' ? 50 : 9)->withQueryString();

        $all = Post::live()->kind($kind)->get(['tags', 'category']);
        PageView::record($request, 'page', $kind === 'note' ? '/notes' : '/blog');

        return PortfolioController::render('posts', [
            'kind' => $kind,
            'profile' => Profile::query()->with('avatar')->firstOrFail(),
            'posts' => $posts,
            'tags' => $all->pluck('tags')->flatten()->filter()->countBy()->sortDesc()->keys()->take(20),
            'categories' => $all->pluck('category')->filter()->unique()->sort()->values(),
            'q' => $q, 'tag' => $tag, 'category' => $category,
        ]);
    }

    public function show(Request $request, Portfolio $portfolio, Post $post)
    {
        return $this->showKind($request, $post, 'article');
    }

    public function note(Request $request, Portfolio $portfolio, Post $post)
    {
        return $this->showKind($request, $post, 'note');
    }

    private function showKind(Request $request, Post $post, string $kind)
    {
        abort_unless($post->isLive() && self::enabled($kind), 404);
        if (($post->kind ?? 'article') !== $kind) {
            return redirect($post->url(), 301);
        }
        PageView::record($request, 'post', $post->slug);

        return $this->render($post, false);
    }

    /** Blog/notes are on when their section is visible. */
    public static function enabled(string $kind): bool
    {
        return Section::query()->where('key', $kind === 'note' ? 'notes' : 'blog')->where('is_visible', true)->exists();
    }

    public function render(Post $post, bool $preview)
    {
        $post->loadMissing('cover');
        $tags = (array) $post->tags;
        $related = Post::live()->kind($post->kind ?? 'article')->whereKeyNot($post->id)->newest()->limit(30)->get()
            ->map(fn ($p) => [$p, count(array_intersect((array) $p->tags, $tags)) * 2 + ($post->category && $p->category === $post->category ? 1 : 0)])
            ->filter(fn ($pair) => $pair[1] > 0)->sortByDesc(fn ($pair) => $pair[1])->take(3)->pluck(0);

        return PortfolioController::render('post', [
            'profile' => Profile::query()->with('avatar')->firstOrFail(),
            'post' => $post,
            'article' => Markdown::article($post->body),
            'related' => $related,
            'preview' => $preview,
        ]);
    }
}
