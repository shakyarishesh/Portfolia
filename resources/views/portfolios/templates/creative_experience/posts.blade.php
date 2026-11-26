@extends('portfolios.templates.creative_experience.layout')

@php
    $isNote = $kind === 'note';
    $listRoute = $isNote ? 'notes.index' : 'blog.index';
    $heading = $isNote ? 'Notes' : 'Writing';
    $filters = fn (array $extra = []) => array_filter($extra + ['q' => $q ?: null, 'category' => $category ?: null]);
    $nav = [[route('home'), 'Home'], [route('home').'#projects', 'Work'], [route($listRoute), $heading]];
    $meta = [
        'title' => ($tag ? "#{$tag} · " : '')."{$heading} — {$profile->full_name}",
        'description' => ($isNote ? 'Evergreen notes' : 'Articles')." by {$profile->full_name}.",
        'ogType' => 'website',
        'canonical' => route($listRoute, array_filter(['tag' => $tag ?: null, 'category' => $category ?: null, 'page' => $posts->currentPage() > 1 ? $posts->currentPage() : null])),
    ];
@endphp

@section('head')
  @if ($q !== '')<meta name="robots" content="noindex">@endif
@endsection

@section('content')
  <header class="page-hero">
    <div class="hero-art" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span></div>
    <p class="eyebrow">{{ $profile->full_name }}</p>
    <h1 class="case-title">{{ $heading }}</h1>
    <form class="search" method="get" action="{{ route($listRoute) }}" role="search" aria-label="Search {{ strtolower($heading) }}">
      <label class="sr-only" for="s-q">Search</label>
      <input id="s-q" type="search" name="q" value="{{ $q }}" placeholder="Search {{ strtolower($heading) }}…">
      @if ($categories->isNotEmpty())
        <label class="sr-only" for="s-cat">Category</label>
        <select id="s-cat" name="category"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c }}" @selected($c === $category)>{{ $c }}</option>@endforeach</select>
      @endif
      @if ($tag)<input type="hidden" name="tag" value="{{ $tag }}">@endif
      <button class="btn primary" type="submit">Search</button>
    </form>
    @if ($tags->isNotEmpty())
      <nav aria-label="Filter by tag">
        <ul class="tags filter">
          <li><a href="{{ route($listRoute, $filters()) }}" @if ($tag === '') aria-current="page" @endif>All</a></li>
          @foreach ($tags as $t)
            <li><a href="{{ route($listRoute, $filters(['tag' => $t])) }}" @if ($t === $tag) aria-current="page" @endif>#{{ $t }}</a></li>
          @endforeach
        </ul>
      </nav>
    @endif
  </header>

  <section class="sec" aria-label="{{ $heading }}">
    @if ($posts->isEmpty())
      <p class="empty">@if ($q !== '' || $tag || $category)Nothing matches. <a href="{{ route($listRoute) }}">See everything</a>.@else No {{ $isNote ? 'notes' : 'articles' }} published yet.@endif</p>
    @else
      <ul class="posts grid">
        @foreach ($posts as $post)
          <li data-reveal>
            @if ($post->cover)<img src="{{ $post->cover->url() }}" alt="" width="640" height="360" loading="lazy">@endif
            <span class="eyebrow">{{ collect([$post->category, $isNote ? null : $post->published_at?->format('j M Y'), $post->readingMinutes().' min read'])->filter()->implode(' · ') }}</span>
            <h2><a href="{{ $post->url() }}">{{ $post->title }}</a></h2>
            @if ($post->excerpt)<p class="muted">{{ $post->excerpt }}</p>@endif
            @if ($post->tags)<ul class="tags" aria-label="Tags">@foreach ($post->tags as $t)<li>#{{ $t }}</li>@endforeach</ul>@endif
          </li>
        @endforeach
      </ul>
      @if ($posts->hasPages())
        <nav class="pager" aria-label="Pagination">
          @if ($posts->previousPageUrl())<a class="btn" href="{{ $posts->previousPageUrl() }}" rel="prev"><span aria-hidden="true">←</span> Newer</a>@endif
          <span>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
          @if ($posts->nextPageUrl())<a class="btn" href="{{ $posts->nextPageUrl() }}" rel="next">Older <span aria-hidden="true">→</span></a>@endif
        </nav>
      @endif
    @endif
  </section>
@endsection
