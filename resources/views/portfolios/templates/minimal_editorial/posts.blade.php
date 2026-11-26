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
@endphp
@extends('portfolios.templates.minimal_editorial._layout')

@section('head')
  @if ($q !== '')<meta name="robots" content="noindex">@endif
@endsection

@section('body')
  <header class="me-article-head">
    <p class="me-kicker"><a href="{{ route('home') }}">Home</a></p>
    <h1 class="me-article-title">{{ $heading }}</h1>
  </header>

  <form class="me-search" method="get" action="{{ route($listRoute) }}" role="search" aria-label="Search {{ $noun }}">
    <div class="me-field">
      <label for="s-q">Search {{ $noun }}</label>
      <input id="s-q" type="search" name="q" value="{{ $q }}">
    </div>
    @if ($categories->isNotEmpty())
      <div class="me-field">
        <label for="s-cat">Category</label>
        <select id="s-cat" name="category">
          <option value="">All categories</option>
          @foreach ($categories as $c)<option value="{{ $c }}" @selected($c === $category)>{{ $c }}</option>@endforeach
        </select>
      </div>
    @endif
    @if ($tag)<input type="hidden" name="tag" value="{{ $tag }}">@endif
    <button class="me-button" type="submit">Search</button>
  </form>

  @if ($tags->isNotEmpty())
    <nav class="me-tags" aria-label="Filter by tag">
      <ul class="me-chips">
        <li><a href="{{ route($listRoute, array_filter(['q' => $q ?: null, 'category' => $category ?: null])) }}" @if ($tag === '') aria-current="page" @endif>All</a></li>
        @foreach ($tags as $t)
          <li><a href="{{ route($listRoute, array_filter(['tag' => $t, 'q' => $q ?: null, 'category' => $category ?: null])) }}" @if ($t === $tag) aria-current="page" @endif>#{{ $t }}</a></li>
        @endforeach
      </ul>
    </nav>
  @endif

  @if ($posts->isEmpty())
    <p class="me-muted">
      @if ($q !== '' || $tag || $category)Nothing matches. <a href="{{ route($listRoute) }}">See all {{ $noun }}</a>.@else Nothing published yet.@endif
    </p>
  @else
    <ol class="me-list">
      @foreach ($posts as $post)
        <li class="me-entry">
          <p class="me-gutter">@unless ($isNotes)<time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('j M Y') }}</time>@endunless</p>
          <div>
            <h2 class="me-h3"><a class="me-title-link" href="{{ $post->url() }}">{{ $post->title }}</a></h2>
            <p class="me-meta">{{ collect([$post->category, $post->readingMinutes().' min read'])->filter()->implode(' · ') }}</p>
            @if ($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
            @if ($post->tags)<ul class="me-chips" aria-label="Tags">@foreach ($post->tags as $t)<li>#{{ $t }}</li>@endforeach</ul>@endif
          </div>
        </li>
      @endforeach
    </ol>
    @if ($posts->hasPages())
      <nav class="me-pager" aria-label="Pagination">
        @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}" rel="prev">← Newer</a>@endif
        <span>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
        @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}" rel="next">Older →</a>@endif
      </nav>
    @endif
  @endif
@endsection
