@php
    use Illuminate\Support\Str;
    $page = 'posts';
    $isNotes = ($kind ?? 'article') === 'note';
    $listRoute = $isNotes ? 'notes.index' : 'blog.index';
    $heading = $isNotes ? 'Notes' : 'Writing';
    $noun = $isNotes ? 'notes' : 'articles';
    $meta = [
        'title' => ($tag ? "#{$tag} · " : '')."{$heading} — {$profile->full_name}",
        'description' => $isNotes ? "Evergreen notes by {$profile->full_name}." : "Articles by {$profile->full_name}.",
        'ogType' => 'website',
        'canonical' => route($listRoute, array_filter(['tag' => $tag ?: null, 'category' => $category ?: null, 'page' => $posts->currentPage() > 1 ? $posts->currentPage() : null])),
    ];
    $nav = [
        ['href' => route('home'), 'emoji' => '🏠', 'label' => 'Home'],
        ['href' => route($listRoute), 'emoji' => $isNotes ? '🗒️' : '📝', 'label' => $heading],
    ];
@endphp
@extends('portfolios.templates.picto._layout')

@section('head')
  @if ($q !== '')<meta name="robots" content="noindex">@endif
@endsection

@section('body')
  <div class="pc-wrap">
    <header class="pc-article-head">
      <p class="pc-eyebrow"><a href="{{ route('home') }}"><span aria-hidden="true">🏠</span> Home</a></p>
      <h1><span class="pc-mini-marker" aria-hidden="true">{{ $isNotes ? '🗒️' : '📝' }}</span> {{ $heading }}</h1>
    </header>

    <form class="pc-search pc-card" method="get" action="{{ route($listRoute) }}" role="search" aria-label="Search {{ $noun }}">
      <div class="pc-field">
        <label for="s-q">Search {{ $noun }}</label>
        <input id="s-q" type="search" name="q" value="{{ $q }}">
      </div>
      @if ($categories->isNotEmpty())
        <div class="pc-field">
          <label for="s-cat">Category</label>
          <select id="s-cat" name="category">
            <option value="">All categories</option>
            @foreach ($categories as $c)<option value="{{ $c }}" @selected($c === $category)>{{ $c }}</option>@endforeach
          </select>
        </div>
      @endif
      @if ($tag)<input type="hidden" name="tag" value="{{ $tag }}">@endif
      <button class="pc-btn pc-btn-main" type="submit"><span aria-hidden="true">🔍</span> Search</button>
    </form>

    @if ($tags->isNotEmpty())
      <nav aria-label="Filter by tag">
        <ul class="pc-tags pc-tag-filter">
          <li><a href="{{ route($listRoute, array_filter(['q' => $q ?: null, 'category' => $category ?: null])) }}" @if ($tag === '') aria-current="page" @endif>All</a></li>
          @foreach ($tags as $t)
            <li><a href="{{ route($listRoute, array_filter(['tag' => $t, 'q' => $q ?: null, 'category' => $category ?: null])) }}" @if ($t === $tag) aria-current="page" @endif>#{{ $t }}</a></li>
          @endforeach
        </ul>
      </nav>
    @endif

    @if ($posts->isEmpty())
      <p class="pc-card pc-empty"><span aria-hidden="true">🦗</span>
        @if ($q !== '' || $tag || $category)Nothing matches. <a href="{{ route($listRoute) }}">See all {{ $noun }}</a>.@else Nothing published yet.@endif
      </p>
    @else
      <ol class="pc-grid pc-posts">
        @foreach ($posts as $post)
          <li class="pc-card pc-post c{{ $loop->index % 4 + 1 }}">
            @unless ($isNotes)<p class="pc-when"><span aria-hidden="true">📝</span> <time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('j M Y') }}</time></p>@endunless
            <h2><a class="pc-stretch" href="{{ $post->url() }}">{{ $post->title }}</a></h2>
            <p class="pc-meta">{{ collect([$post->category, $post->readingMinutes().' min read'])->filter()->implode(' · ') }}</p>
            @if ($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
            @if ($post->tags)<ul class="pc-tags" aria-label="Tags">@foreach ($post->tags as $t)<li>#{{ $t }}</li>@endforeach</ul>@endif
          </li>
        @endforeach
      </ol>
      @if ($posts->hasPages())
        <nav class="pc-pager" aria-label="Pagination">
          @if ($posts->previousPageUrl())<a class="pc-btn" href="{{ $posts->previousPageUrl() }}" rel="prev"><span aria-hidden="true">⬅️</span> Newer</a>@endif
          <span>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
          @if ($posts->nextPageUrl())<a class="pc-btn" href="{{ $posts->nextPageUrl() }}" rel="next">Older <span aria-hidden="true">➡️</span></a>@endif
        </nav>
      @endif
    @endif
  </div>
@endsection
