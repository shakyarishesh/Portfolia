@php
    use Illuminate\Support\Str;
    $page = 'posts';
    $isNotes = ($kind ?? 'article') === 'note';
    $listRoute = $isNotes ? 'notes.index' : 'blog.index';
    $heading = $isNotes ? 'Notes' : 'Writing';
    $noun = $isNotes ? 'notes' : 'articles';
    $crumb = [$heading, route($listRoute)];
    $meta = [
        'title' => ($tag ? "#{$tag} · " : '')."{$heading} — {$profile->full_name}",
        'description' => $isNotes ? "Evergreen notes by {$profile->full_name}." : "Articles by {$profile->full_name}.",
        'ogType' => 'website',
        'canonical' => route($listRoute, array_filter(['tag' => $tag ?: null, 'category' => $category ?: null, 'page' => $posts->currentPage() > 1 ? $posts->currentPage() : null])),
    ];
@endphp
@extends('portfolios.templates.gradient._layout')

@section('head')
  @if ($q !== '')<meta name="robots" content="noindex">@endif
@endsection

@section('body')
  <div class="gd-wrap gd-narrow">
    <header class="gd-article-head">
      <p class="gd-eyebrow"><a href="{{ route('home') }}">{{ $profile->full_name }}</a></p>
      <h1 class="gd-article-title">{{ $heading }}</h1>
    </header>

    <form class="gd-search gd-card" method="get" action="{{ route($listRoute) }}" role="search" aria-label="Search {{ $noun }}">
      <div class="gd-field">
        <label for="s-q">Search {{ $noun }}</label>
        <input id="s-q" type="search" name="q" value="{{ $q }}">
      </div>
      @if ($categories->isNotEmpty())
        <div class="gd-field">
          <label for="s-cat">Category</label>
          <select id="s-cat" name="category">
            <option value="">All categories</option>
            @foreach ($categories as $c)<option value="{{ $c }}" @selected($c === $category)>{{ $c }}</option>@endforeach
          </select>
        </div>
      @endif
      @if ($tag)<input type="hidden" name="tag" value="{{ $tag }}">@endif
      <button class="gd-btn" type="submit">Search</button>
    </form>

    @if ($tags->isNotEmpty())
      <nav class="gd-tags" aria-label="Filter by tag">
        <ul class="gd-chips gd-chips-links">
          <li><a href="{{ route($listRoute, array_filter(['q' => $q ?: null, 'category' => $category ?: null])) }}" @if ($tag === '') aria-current="page" @endif>All</a></li>
          @foreach ($tags as $t)
            <li><a href="{{ route($listRoute, array_filter(['tag' => $t, 'q' => $q ?: null, 'category' => $category ?: null])) }}" @if ($t === $tag) aria-current="page" @endif>#{{ $t }}</a></li>
          @endforeach
        </ul>
      </nav>
    @endif

    @if ($posts->isEmpty())
      <p class="gd-muted gd-empty">
        @if ($q !== '' || $tag || $category)Nothing matches. <a href="{{ route($listRoute) }}">See all {{ $noun }}</a>.@else Nothing published yet.@endif
      </p>
    @else
      <ol class="gd-post-list">
        @foreach ($posts as $post)
          <li class="gd-card gd-edge-left">
            <p class="gd-small gd-muted">
              @if (! $isNotes && $post->published_at)<time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->format('j M Y') }}</time> · @endif
              {{ collect([$post->category, $post->readingMinutes().' min read'])->filter()->implode(' · ') }}
            </p>
            <h2 class="gd-h3"><a class="gd-title-link" href="{{ $post->url() }}">{{ $post->title }}</a></h2>
            @if ($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
            @if ($post->tags)<ul class="gd-chips" aria-label="Tags">@foreach ($post->tags as $t)<li>#{{ $t }}</li>@endforeach</ul>@endif
          </li>
        @endforeach
      </ol>
      @if ($posts->hasPages())
        <nav class="gd-pager" aria-label="Pagination">
          @if ($posts->previousPageUrl())<a class="gd-btn gd-btn-ghost" href="{{ $posts->previousPageUrl() }}" rel="prev">← Newer</a>@endif
          <span>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
          @if ($posts->nextPageUrl())<a class="gd-btn gd-btn-ghost" href="{{ $posts->nextPageUrl() }}" rel="next">Older →</a>@endif
        </nav>
      @endif
    @endif
  </div>
@endsection
