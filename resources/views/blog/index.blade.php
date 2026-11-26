@php($listRoute = ($kind ?? 'article') === 'note' ? 'notes.index' : 'blog.index')
<!doctype html>
<html lang="en" class="doc">
<head>
  @include('portfolio.partials.head', [
      'title' => ($tag ? "#{$tag} · " : '').'Engineering journal — '.$profile->full_name,
      'description' => "Notes on backend engineering, Laravel, databases and integrations by {$profile->full_name}.",
      'ogType' => 'website',
      'canonical' => route($listRoute, array_filter(['tag' => $tag ?: null, 'category' => $category ?: null, 'page' => $posts->currentPage() > 1 ? $posts->currentPage() : null])),
  ])
  @if ($q !== '')<meta name="robots" content="noindex">@endif
</head>
<body>
  @include('portfolios.partials.preview-banner')
  @include('portfolios.partials.cursor')
  <div class="bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><div class="grid"></div></div>
  <main class="page">
    <div class="wrap">
      <a class="back" href="{{ route('home') }}">← {{ $profile->full_name }}</a>
      <p class="eyebrow">Engineering journal</p>
      <h1 class="title big">{{ $listRoute === 'notes.index' ? 'Notes' : 'Writing' }}</h1>

      <form class="project-tools" method="get" role="search" aria-label="Search articles">
        <label class="sr-only" for="b-q">Search articles</label>
        <input id="b-q" type="search" name="q" value="{{ $q }}" placeholder="Search articles…">
        @if ($categories->isNotEmpty())
          <label class="sr-only" for="b-cat">Category</label>
          <select id="b-cat" name="category" onchange="this.form.submit()"><option value="">All categories</option>@foreach ($categories as $c)<option value="{{ $c }}" @selected($c === $category)>{{ $c }}</option>@endforeach</select>
        @endif
        @if ($tag)<input type="hidden" name="tag" value="{{ $tag }}">@endif
        <button class="btn" type="submit">Search</button>
      </form>

      @if ($tags->isNotEmpty())
        <nav class="filters" aria-label="Tags">
          <a class="chip {{ $tag === '' ? 'active' : '' }}" href="{{ route($listRoute, array_filter(['q' => $q ?: null, 'category' => $category ?: null])) }}">All</a>
          @foreach ($tags as $t)
            <a class="chip {{ $t === $tag ? 'active' : '' }}" href="{{ route($listRoute, array_filter(['tag' => $t, 'q' => $q ?: null, 'category' => $category ?: null])) }}" @if ($t === $tag) aria-current="page" @endif>#{{ $t }}</a>
          @endforeach
        </nav>
      @endif

      @if ($posts->isEmpty())
        <p class="empty-state">@if ($q !== '' || $tag || $category)No articles match. <a href="{{ route($listRoute) }}">See all articles</a>.@else No articles published yet.@endif</p>
      @else
        <div class="cards">
          @foreach ($posts as $post)
            <article class="card post-card">
              @if ($post->cover)<img class="thumb" src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?: '' }}" loading="lazy">@endif
              <span class="tag">{{ collect([$post->category, $post->published_at->format('j M Y'), $post->readingMinutes().' min read'])->filter()->implode(' · ') }}</span>
              <h2><a href="{{ $post->url() }}">{{ $post->title }}</a></h2>
              @if ($post->excerpt)<p>{{ $post->excerpt }}</p>@endif
              @if ($post->tags)<div class="techs">@foreach ($post->tags as $t)<span>#{{ $t }}</span>@endforeach</div>@endif
            </article>
          @endforeach
        </div>
        @if ($posts->hasPages())
          <nav class="pager" aria-label="Pagination">
            @if ($posts->previousPageUrl())<a class="btn" href="{{ $posts->previousPageUrl() }}" rel="prev">← Newer</a>@endif
            <span>Page {{ $posts->currentPage() }} of {{ $posts->lastPage() }}</span>
            @if ($posts->nextPageUrl())<a class="btn" href="{{ $posts->nextPageUrl() }}" rel="next">Older →</a>@endif
          </nav>
        @endif
      @endif
    </div>
  </main>
</body>
</html>
