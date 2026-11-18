@php
    $page = 'posts';
    $isNotes = ($kind ?? 'article') === 'note';
    $listRoute = $isNotes ? 'notes.index' : 'blog.index';
    $heading = $isNotes ? 'Notes' : 'Writing';
    $noun = $isNotes ? 'notes' : 'articles';
    $nav = [[route('home'), 'Home'], [route('home').'#contact', 'Contact']];
    $meta = [
        'title' => ($tag ? "#{$tag} · " : '')."{$heading} — {$profile->full_name}",
        'description' => $isNotes ? "Evergreen notes by {$profile->full_name}." : "Articles by {$profile->full_name}.",
        'ogType' => 'website',
        'canonical' => route($listRoute, array_filter(['tag' => $tag ?: null, 'category' => $category ?: null, 'page' => $posts->currentPage() > 1 ? $posts->currentPage() : null])),
    ];
@endphp
@extends('portfolios.templates.modern_personal.layout')

@section('head')
  @if ($q !== '')<meta name="robots" content="noindex">@endif
@endsection

@section('body')
  <header class="mp-page-head mp-grid">
    <p class="mp-track mp-meta"><a href="{{ route('home') }}">← Home</a></p>
    <div>
      <h1 class="mp-title">{{ $heading }}</h1>
      <form class="mp-search" method="get" action="{{ route($listRoute) }}" role="search" aria-label="Search {{ $noun }}">
        <div class="mp-field">
          <label for="s-q">Search {{ $noun }}</label>
          <input id="s-q" type="search" name="q" value="{{ $q }}">
        </div>
        @if ($categories->isNotEmpty())
          <div class="mp-field">
            <label for="s-cat">Category</label>
            <select id="s-cat" name="category">
              <option value="">All categories</option>
              @foreach ($categories as $c)<option value="{{ $c }}" @selected($c === $category)>{{ $c }}</option>@endforeach
            </select>
          </div>
        @endif
        @if ($tag)<input type="hidden" name="tag" value="{{ $tag }}">@endif
        <button class="mp-btn" type="submit">Search</button>
      </form>
      @if ($tags->isNotEmpty())
        <nav aria-label="Filter by tag">
          <ul class="mp-inline mp-meta">
            <li><a href="{{ route($listRoute, array_filter(['q' => $q ?: null, 'category' => $category ?: null])) }}" @if ($tag === '') aria-current="page" @endif>All</a></li>
            @foreach ($tags as $t)
              <li><a href="{{ route($listRoute, array_filter(['tag' => $t, 'q' => $q ?: null, 'category' => $category ?: null])) }}" @if ($t === $tag) aria-current="page" @endif>#{{ $t }}</a></li>
            @endforeach
          </ul>
        </nav>
      @endif
    </div>
  </header>

  <div class="mp-sec">
    @if ($posts->isEmpty())
      <div class="mp-grid"><span class="mp-track"></span><p class="mp-muted">
        @if ($q !== '' || $tag || $category)Nothing matches. <a href="{{ route($listRoute) }}">See all {{ $noun }}</a>.@else Nothing published yet.@endif
      </p></div>
    @else
      <ol class="mp-list mp-list-wide">
        @foreach ($posts as $post)
          <li class="mp-entry mp-grid">
            <p class="mp-date mp-track">@unless ($isNotes)<time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('j M Y') }}</time>@endunless</p>
            <div>
              <h2 class="mp-h3"><a href="{{ $post->url() }}">{{ $post->title }}</a></h2>
              @if ($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
              <p class="mp-meta">{{ collect([$post->category, $post->readingMinutes().' min read', $post->tags ? '#'.implode(' #', $post->tags) : null])->filter()->implode(' · ') }}</p>
            </div>
          </li>
        @endforeach
      </ol>
      @if ($posts->hasPages())
        <nav class="mp-pager" aria-label="Pagination">
          @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}" rel="prev">← Newer</a>@endif
          <span>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
          @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}" rel="next">Older →</a>@endif
        </nav>
      @endif
    @endif
  </div>
@endsection
