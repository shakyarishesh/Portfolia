@php
    $isNote = $kind === 'note';
    $listRoute = $isNote ? 'notes.index' : 'blog.index';
    $heading = $isNote ? 'Notes' : 'Writing';
    $noun = $isNote ? 'notes' : 'articles';
    $filtered = $q !== '' || $tag !== '' || $category !== '';
    $meta = [
        'title' => "{$heading} — {$profile->full_name}",
        'description' => $isNote ? "Evergreen notes by {$profile->full_name}." : "Articles by {$profile->full_name}.",
        'canonical' => route($listRoute),
    ];
@endphp
@extends('portfolios.templates.content_journal.layout')

@section('main')
  <header class="cj-pagehead">
    <h1>{{ $heading }}</h1>
    <p class="cj-sub">
      @if ($isNote)Evergreen notes — ideas, references and how-tos I keep up to date. Sorted by topic, not by date.
      @else Dated articles and longer write-ups, newest first.@endif
    </p>
  </header>

  <form class="cj-search" method="get" action="{{ route($listRoute) }}" role="search">
    <label for="cj-q">Search {{ $noun }}</label>
    <div class="cj-search-row">
      <input id="cj-q" type="search" name="q" value="{{ $q }}" maxlength="100">
      @if ($categories->isNotEmpty())
        <label for="cj-cat" class="cj-sr">{{ $isNote ? 'Topic' : 'Category' }}</label>
        <select id="cj-cat" name="category">
          <option value="">All {{ $isNote ? 'topics' : 'categories' }}</option>
          @foreach ($categories as $c)<option value="{{ $c }}" @selected($c === $category)>{{ $c }}</option>@endforeach
        </select>
      @endif
      @if ($tag !== '')<input type="hidden" name="tag" value="{{ $tag }}">@endif
      <button type="submit" class="cj-button">Search</button>
    </div>
  </form>

  @if ($tags->isNotEmpty())
    <nav class="cj-tags" aria-label="Tags">
      <ul>
        @foreach ($tags as $t)
          <li><a href="{{ route($listRoute, ['tag' => $t]) }}" @if ($t === $tag) aria-current="true" @endif>#{{ $t }}</a></li>
        @endforeach
      </ul>
    </nav>
  @endif

  @if ($filtered)
    <p class="cj-filter" role="status">
      {{ $posts->total() }} {{ \Illuminate\Support\Str::plural($isNote ? 'note' : 'article', $posts->total()) }}
      @if ($q !== '') matching “{{ $q }}”@endif
      @if ($tag !== '') tagged #{{ $tag }}@endif
      @if ($category !== '') in {{ $category }}@endif
      · <a href="{{ route($listRoute) }}">Clear filters</a>
    </p>
  @endif

  @if ($posts->isEmpty())
    <p class="cj-empty">{{ $filtered ? "No {$noun} match those filters." : ($isNote ? 'No notes published yet.' : 'No articles published yet.') }}</p>
  @elseif ($isNote)
    @include('portfolios.templates.content_journal.partials.note-list', ['notes' => $posts->getCollection(), 'level' => 2])
  @else
    @foreach ($posts->groupBy(fn ($p) => $p->published_at->year) as $year => $items)
      <section class="cj-year" aria-labelledby="y{{ $year }}">
        <h2 id="y{{ $year }}" class="cj-year-label">{{ $year }}</h2>
        <ol class="cj-entries">
          @foreach ($items as $post)
            @include('portfolios.templates.content_journal.partials.article-row', ['post' => $post])
          @endforeach
        </ol>
      </section>
    @endforeach
  @endif

  @if ($posts->hasPages())
    <nav class="cj-pager" aria-label="Pagination">
      @if ($posts->previousPageUrl())<a href="{{ $posts->previousPageUrl() }}" rel="prev">← {{ $isNote ? 'Previous' : 'Newer' }}</a>@endif
      <span>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
      @if ($posts->nextPageUrl())<a href="{{ $posts->nextPageUrl() }}" rel="next">{{ $isNote ? 'Next' : 'Older' }} →</a>@endif
    </nav>
  @endif
@endsection
