@php
    use Illuminate\Support\Str;
    $page = 'post';
    $listRoute = $post->isNote() ? 'notes.index' : 'blog.index';
    $listLabel = $post->isNote() ? 'Notes' : 'Articles';
    $meta = [
        'title' => "{$post->title} — {$profile->full_name}",
        'description' => $post->meta_description ?: ($post->excerpt ?: Str::limit(strip_tags($article['html']), 155)),
        'image' => $post->cover?->url() ?? $profile->avatarSrc(),
        'ogType' => 'article',
        'canonical' => $post->url(),
    ];
    $navItems = [[route('home'), 'Overview'], [route($listRoute), $listLabel]];
@endphp
@extends('portfolios.templates.professional.layout')

@section('head')
  @if ($post->published_at)<meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">@endif
  <script type="application/ld+json">{!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'BlogPosting',
      'headline' => $post->title,
      'description' => $post->excerpt,
      'datePublished' => $post->published_at?->toAtomString(),
      'dateModified' => $post->updated_at?->toAtomString(),
      'image' => $post->cover?->url(),
      'url' => $post->url(),
      'keywords' => $post->tags ? implode(', ', $post->tags) : null,
      'author' => ['@type' => 'Person', 'name' => $profile->full_name, 'url' => route('home')],
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('body')
  <div class="pro-wrap pro-page">
    @if ($preview)
      <p class="pro-notice" role="status">Preview — status: <b>{{ $post->status }}</b>@if ($post->published_at?->isFuture()), scheduled for {{ $post->published_at->format('j M Y H:i') }}@endif. Only you can see this page.</p>
    @endif

    <nav class="pro-crumbs" aria-label="Breadcrumb">
      <ol><li><a href="{{ route('home') }}">{{ $profile->full_name }}</a></li><li><a href="{{ route($listRoute) }}">{{ $listLabel }}</a></li><li aria-current="page">{{ $post->title }}</li></ol>
    </nav>

    <header class="pro-page-head">
      <p class="pro-kicker">
        @if ($post->published_at && ! $post->isNote())<time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->format('j F Y') }}</time> · @endif
        {{ collect([$post->category, $post->readingMinutes().' min read'])->filter()->implode(' · ') }}
      </p>
      <h1 class="pro-page-title">{{ $post->title }}</h1>
      @if ($post->excerpt)<p class="pro-lead">{{ $post->excerpt }}</p>@endif
    </header>

    <div class="pro-detail @if (count($article['toc']) > 1) has-aside @endif">
      <article class="pro-article">
        @if ($post->cover)
          <img class="pro-cover" src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?: '' }}" width="1280" height="720">
        @endif
        <div class="pro-prose pro-prose-long">{!! $article['html'] !!}</div>
        @if ($post->tags)
          <ul class="pro-tags pro-post-tags" aria-label="Tags">
            @foreach ($post->tags as $t)<li><a href="{{ route($listRoute, ['tag' => $t]) }}">#{{ $t }}</a></li>@endforeach
          </ul>
        @endif
      </article>

      @if (count($article['toc']) > 1)
        <aside class="pro-card pro-toc">
          <nav aria-labelledby="toc-h">
            <p id="toc-h" class="pro-h4">On this page</p>
            <ol>@foreach ($article['toc'] as $h)<li class="lvl{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ol>
          </nav>
        </aside>
      @endif
    </div>

    @if ($related->isNotEmpty())
      <section class="pro-related" aria-labelledby="related-h">
        <h2 id="related-h" class="pro-h3">Keep reading</h2>
        <div class="pro-grid pro-grid-3">
          @foreach ($related as $r)
            <article class="pro-card pro-mini">
              <h3 class="pro-card-title"><a class="pro-title-link" href="{{ $r->url() }}">{{ $r->title }}</a></h3>
              @if ($r->excerpt)<p class="pro-muted">{{ Str::limit($r->excerpt, 120) }}</p>@endif
            </article>
          @endforeach
        </div>
      </section>
    @endif
  </div>
@endsection
