<?php

namespace App\Admin\Resources;

use App\Admin\Resource;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class PostResource extends Resource
{
    public const KEY = 'post';

    public const SLUG = 'posts';

    public const LABEL = 'Blog articles';

    public const SINGULAR = 'Article';

    public const MODEL = Post::class;

    public const SEARCH = ['title', 'excerpt', 'body'];

    public const IMPORTABLE = false;

    public const NATURAL_KEY = ['title'];

    public static function fields(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Title', 'rules' => ['required', 'string', 'max:200']],
            'slug' => ['type' => 'text', 'label' => 'URL slug', 'help' => 'Leave blank to generate from the title.',
                'rules' => ['nullable', 'alpha_dash', 'max:200', Rule::unique('posts', 'slug')->ignore(request()->route('id'))]],
            'excerpt' => ['type' => 'textarea', 'label' => 'Excerpt', 'rows' => 2, 'help' => 'Shown on the article list and in search results.', 'rules' => ['nullable', 'string', 'max:500']],
            'body' => ['type' => 'textarea', 'label' => 'Article (Markdown)', 'rows' => 22, 'help' => 'Markdown: ## headings build the table of contents; ```php fences get syntax highlighting. Raw HTML is escaped.',
                'rules' => ['nullable', 'string', 'max:200000']],
            'category' => ['type' => 'text', 'label' => 'Category', 'rules' => ['nullable', 'string', 'max:60']],
            'tags' => ['type' => 'lines', 'label' => 'Tags', 'help' => 'One per line.', 'rules' => ['nullable', 'string', 'max:1000']],
            'published_at' => ['type' => 'datetime', 'label' => 'Publish date', 'help' => 'A future date schedules the article. Blank = now, when published.', 'rules' => ['nullable', 'date']],
            'meta_description' => ['type' => 'text', 'label' => 'SEO description', 'wide' => true, 'rules' => ['nullable', 'string', 'max:300']],
            'status' => static::statusField(),
            'source_note' => static::sourceNoteField(),
        ];
    }

    public static function columns(): array
    {
        return [
            'title' => 'Article',
            'published' => fn ($r) => $r->published_at ? ($r->published_at->isFuture() ? 'Scheduled '.$r->published_at->format('j M Y H:i') : $r->published_at->format('j M Y')) : '—',
        ];
    }

    public static function query(): Builder
    {
        return Post::query();
    }

    protected static function beforeSave(Model $record): void
    {
        if (blank($record->slug)) {
            $record->slug = static::uniqueSlug($record->title, $record->id);
        }
        if ($record->status === 'published' && ! $record->published_at) {
            $record->published_at = now();
        }
    }
}
