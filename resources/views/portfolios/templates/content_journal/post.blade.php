@php
    $url = $post->url();
    $isNote = $post->isNote();
    $listRoute = $isNote ? 'notes.index' : 'blog.index';
    $date = $isNote ? ($post->updated_at ?? $post->published_at) : $post->published_at;
    $meta = [
        'title' => "{$post->title} — {$profile->full_name}",
        'description' => $post->meta_description ?: ($post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($article['html']), 155)),
        'image' => $post->cover?->url() ?? $profile->avatarSrc(),
        'ogType' => 'article',
        'canonical' => $url,
    ];
@endphp
@extends('portfolios.templates.content_journal.layout')

@push('head')
  @if ($post->published_at)<meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">@endif
  @if ($post->updated_at)<meta property="article:modified_time" content="{{ $post->updated_at->toAtomString() }}">@endif
  <script type="application/ld+json">{!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'BlogPosting',
      'headline' => $post->title,
      'description' => $post->excerpt,
      'datePublished' => $post->published_at?->toAtomString(),
      'dateModified' => $post->updated_at?->toAtomString(),
      'image' => $post->cover?->url(),
      'url' => $url,
      'keywords' => $post->tags ? implode(', ', $post->tags) : null,
      'author' => ['@type' => 'Person', 'name' => $profile->full_name, 'url' => route('home')],
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endpush

@section('main')
  @if ($preview)
    <p class="cj-notice" role="status">Preview — status: <strong>{{ $post->status }}</strong>@if ($post->published_at?->isFuture()), scheduled for {{ $post->published_at->format('j M Y H:i') }}@endif.</p>
  @endif

  <article class="cj-article">
    <header class="cj-pagehead">
      <p class="cj-kicker"><a href="{{ route($listRoute) }}">{{ $isNote ? 'Notes' : 'Writing' }}</a>@if ($post->category) / <a href="{{ route($listRoute, ['category' => $post->category]) }}">{{ $post->category }}</a>@endif</p>
      <h1>{{ $post->title }}</h1>
      <p class="cj-meta">
        @if ($date)
          {{ $isNote ? 'Last updated' : 'Published' }} <time datetime="{{ $date->toAtomString() }}">{{ $date->format('j F Y') }}</time> ·
        @endif
        {{ $post->readingMinutes() }} min read
      </p>
      @if ($post->excerpt)<p class="cj-lede">{{ $post->excerpt }}</p>@endif
    </header>

    @if ($post->cover)
      <img class="cj-cover" src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?: '' }}" loading="lazy">
    @endif

    @if (count($article['toc']) > 1)
      <nav class="cj-toc" aria-labelledby="toc-h">
        <h2 id="toc-h">Contents</h2>
        <ol>@foreach ($article['toc'] as $h)<li class="cj-toc-{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ol>
      </nav>
    @endif

    <div class="cj-prose">{!! $article['html'] !!}</div>

    @if ($post->tags)
      <ul class="cj-tags" aria-label="Tags">@foreach ($post->tags as $t)<li><a href="{{ route($listRoute, ['tag' => $t]) }}">#{{ $t }}</a></li>@endforeach</ul>
    @endif
  </article>

  <aside class="cj-byline" aria-label="About the author">
    @if ($profile->avatarSrc())<img class="cj-avatar" src="{{ $profile->avatarSrc() }}" alt="" width="48" height="48" loading="lazy">@endif
    <p><a href="{{ route('home') }}">{{ $profile->full_name }}</a>@if ($profile->headline) — {{ $profile->headline }}@endif</p>
  </aside>

  @if ($related->isNotEmpty())
    <section class="cj-block" aria-labelledby="related-h">
      <h2 id="related-h">{{ $isNote ? 'Related notes' : 'Related articles' }}</h2>
      <ul class="cj-plain">
        @foreach ($related as $r)
          <li><a href="{{ $r->url() }}">{{ $r->title }}</a>@unless ($isNote) <span class="cj-period">{{ $r->published_at?->format('M Y') }}</span>@endunless</li>
        @endforeach
      </ul>
    </section>
  @endif
@endsection
