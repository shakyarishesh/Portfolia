@extends('portfolios.templates.creative_experience.layout')

@php
    $url = $post->url();
    $listRoute = $post->isNote() ? 'notes.index' : 'blog.index';
    $heading = $post->isNote() ? 'Notes' : 'Writing';
    $nav = [[route('home'), 'Home'], [route('home').'#projects', 'Work'], [route($listRoute), $heading]];
    $meta = [
        'title' => "{$post->title} — {$profile->full_name}",
        'description' => $post->meta_description ?: ($post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($article['html']), 155)),
        'image' => $post->cover?->url() ?? $profile->avatarSrc(),
        'ogType' => 'article',
        'canonical' => $url,
    ];
@endphp

@section('head')
  @if ($preview)<meta name="robots" content="noindex">@endif
  @if ($post->published_at)<meta property="article:published_time" content="{{ $post->published_at->toAtomString() }}">@endif
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
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('content')
  @if ($preview)
    <p class="note" role="status">Preview — status: <b>{{ $post->status }}</b>@if ($post->published_at?->isFuture()), scheduled for {{ $post->published_at->format('j M Y H:i') }}@endif</p>
  @endif
  <article class="case">
    <header class="page-hero">
      <div class="hero-art" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span></div>
      <a class="back" href="{{ route($listRoute) }}"><span aria-hidden="true">←</span> All {{ strtolower($heading) }}</a>
      <p class="eyebrow">{{ collect([$post->category, $post->published_at?->format('j F Y'), $post->readingMinutes().' min read'])->filter()->implode(' · ') }}</p>
      <h1 class="case-title">{{ $post->title }}</h1>
      @if ($post->excerpt)<p class="lead">{{ $post->excerpt }}</p>@endif
    </header>
    @if ($post->cover)
      <figure class="case-shot"><img src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?: '' }}" width="1200" height="630"></figure>
    @endif
    <div class="case-body">
      <aside class="case-toc">
        @if (count($article['toc']) > 1)
          <nav aria-label="On this page">
            <h2 class="eyebrow">On this page</h2>
            <ol>@foreach ($article['toc'] as $h)<li class="lvl{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ol>
          </nav>
        @endif
        @if ($post->tags)
          <h2 class="eyebrow">Tags</h2>
          <ul class="tags">@foreach ($post->tags as $t)<li><a href="{{ route($listRoute, ['tag' => $t]) }}">#{{ $t }}</a></li>@endforeach</ul>
        @endif
      </aside>
      <div class="prose">{!! $article['html'] !!}</div>
    </div>
    @if ($related->isNotEmpty())
      <section class="sec related" aria-labelledby="h-related">
        <h2 id="h-related">Keep reading</h2>
        <ul class="posts">
          @foreach ($related as $r)
            <li><span class="eyebrow">{{ $r->published_at?->format('j M Y') }}</span><h3><a href="{{ $r->url() }}">{{ $r->title }}</a></h3>@if ($r->excerpt)<p class="muted">{{ \Illuminate\Support\Str::limit($r->excerpt, 120) }}</p>@endif</li>
          @endforeach
        </ul>
      </section>
    @endif
  </article>
@endsection
