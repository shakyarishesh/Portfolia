<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use App\Models\Post;
use App\Models\Profile;
use App\Support\Markdown;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $tag = (string) $request->query('tag');
        $category = (string) $request->query('category');

        $posts = Post::live()->with('cover')
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('title', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('excerpt', 'like', '%'.addcslashes($q, '%_\\').'%')
                ->orWhere('body', 'like', '%'.addcslashes($q, '%_\\').'%')))
            ->when($tag !== '', fn ($query) => $query->whereJsonContains('tags', $tag))
            ->when($category !== '', fn ($query) => $query->where('category', $category))
            ->newest()->paginate(9)->withQueryString();

        $all = Post::live()->get(['tags', 'category']);
        PageView::record($request, 'page', '/blog');

        return view('blog.index', [
            'profile' => Profile::query()->with('avatar')->firstOrFail(),
            'posts' => $posts,
            'tags' => $all->pluck('tags')->flatten()->filter()->countBy()->sortDesc()->keys()->take(20),
            'categories' => $all->pluck('category')->filter()->unique()->sort()->values(),
            'q' => $q, 'tag' => $tag, 'category' => $category,
        ]);
    }

    public function show(Request $request, Post $post)
    {
        abort_unless($post->isLive(), 404);
        PageView::record($request, 'post', $post->slug);

        return $this->render($post, false);
    }

    public function render(Post $post, bool $preview)
    {
        $post->loadMissing('cover');
        $tags = (array) $post->tags;
        $related = Post::live()->whereKeyNot($post->id)->newest()->limit(30)->get()
            ->map(fn ($p) => [$p, count(array_intersect((array) $p->tags, $tags)) * 2 + ($post->category && $p->category === $post->category ? 1 : 0)])
            ->filter(fn ($pair) => $pair[1] > 0)->sortByDesc(fn ($pair) => $pair[1])->take(3)->pluck(0);

        return view('blog.show', [
            'profile' => Profile::query()->with('avatar')->firstOrFail(),
            'post' => $post,
            'article' => Markdown::article($post->body),
            'related' => $related,
            'preview' => $preview,
        ]);
    }
}
