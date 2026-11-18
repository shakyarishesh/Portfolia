@php
    use Illuminate\Support\Str;
    $icon = fn (string $name) => '<svg class="pro-i" aria-hidden="true" focusable="false"><use href="#pro-i-'.e($name).'"/></svg>';
    $page = 'posts';
    $isNotes = ($kind ?? 'article') === 'note';
    $listRoute = $isNotes ? 'notes.index' : 'blog.index';
    $heading = $isNotes ? 'Notes' : 'Articles';
    $noun = $isNotes ? 'notes' : 'articles';
    $meta = [
        'title' => ($tag ? "#{$tag} · " : '')."{$heading} — {$profile->full_name}",
        'description' => $isNotes ? "Evergreen notes by {$profile->full_name}." : "Articles by {$profile->full_name}.",
        'ogType' => 'website',
        'canonical' => route($listRoute, array_filter(['tag' => $tag ?: null, 'category' => $category ?: null, 'page' => $posts->currentPage() > 1 ? $posts->currentPage() : null])),
    ];
    $navItems = [[route('home'), 'Overview'], [route($listRoute), $heading]];
@endphp
@extends('portfolios.templates.professional.layout')

@section('head')
  @if ($q !== '')<meta name="robots" content="noindex">@endif
@endsection

@section('body')
  <div class="pro-wrap pro-page">
    <nav class="pro-crumbs" aria-label="Breadcrumb">
      <ol><li><a href="{{ route('home') }}">{{ $profile->full_name }}</a></li><li aria-current="page">{{ $heading }}</li></ol>
    </nav>
    <header class="pro-page-head">
      <h1 class="pro-page-title">{{ $heading }}</h1>
      <p class="pro-lead">{{ $isNotes ? 'Evergreen notes, kept up to date.' : 'Articles on engineering and the work behind it.' }}</p>
    </header>

    <form class="pro-card pro-search" method="get" action="{{ route($listRoute) }}" role="search" aria-label="Search {{ $noun }}">
      <div class="pro-field">
        <label for="s-q">Search {{ $noun }}</label>
        <input id="s-q" type="search" name="q" value="{{ $q }}">
      </div>
      @if ($categories->isNotEmpty())
        <div class="pro-field">
          <label for="s-cat">Category</label>
          <select id="s-cat" name="category">
            <option value="">All categories</option>
            @foreach ($categories as $c)<option value="{{ $c }}" @selected($c === $category)>{{ $c }}</option>@endforeach
          </select>
        </div>
      @endif
      @if ($tag)<input type="hidden" name="tag" value="{{ $tag }}">@endif
      <button class="pro-btn pro-btn-primary" type="submit">{!! $icon('search') !!}Search</button>
    </form>

    @if ($tags->isNotEmpty())
      <nav class="pro-filter" aria-label="Filter by tag">
        <ul class="pro-tags">
          <li><a href="{{ route($listRoute, array_filter(['q' => $q ?: null, 'category' => $category ?: null])) }}" @if ($tag === '') aria-current="page" @endif>All</a></li>
          @foreach ($tags as $t)
            <li><a href="{{ route($listRoute, array_filter(['tag' => $t, 'q' => $q ?: null, 'category' => $category ?: null])) }}" @if ($t === $tag) aria-current="page" @endif>#{{ $t }}</a></li>
          @endforeach
        </ul>
      </nav>
    @endif

    @if ($posts->isEmpty())
      <p class="pro-muted">
        @if ($q !== '' || $tag || $category)Nothing matches. <a href="{{ route($listRoute) }}">See all {{ $noun }}</a>.@else Nothing published yet.@endif
      </p>
    @else
      <div class="pro-grid pro-grid-2">
        @foreach ($posts as $post)
          <article class="pro-card pro-post">
            <p class="pro-kicker">{{ collect([$isNotes ? null : $post->published_at?->format('j M Y'), $post->category, $post->readingMinutes().' min read'])->filter()->implode(' · ') }}</p>
            <h2 class="pro-card-title"><a class="pro-title-link" href="{{ $post->url() }}">{{ $post->title }}</a></h2>
            @if ($post->excerpt)<p class="pro-muted">{{ $post->excerpt }}</p>@endif
            @if ($post->tags)<ul class="pro-tags" aria-label="Tags">@foreach ($post->tags as $t)<li>#{{ $t }}</li>@endforeach</ul>@endif
          </article>
        @endforeach
      </div>
      @if ($posts->hasPages())
        <nav class="pro-pager" aria-label="Pagination">
          @if ($posts->previousPageUrl())<a class="pro-btn pro-btn-ghost" href="{{ $posts->previousPageUrl() }}" rel="prev">← Newer</a>@endif
          <span>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
          @if ($posts->nextPageUrl())<a class="pro-btn pro-btn-ghost" href="{{ $posts->nextPageUrl() }}" rel="next">Older →</a>@endif
        </nav>
      @endif
    @endif
  </div>
@endsection
