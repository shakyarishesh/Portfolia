@php
    use Illuminate\Support\Str;
    $page = 'post';
    $listRoute = $post->isNote() ? 'notes.index' : 'blog.index';
    $meta = [
        'title' => "{$post->title} — {$profile->full_name}",
        'description' => $post->meta_description ?: ($post->excerpt ?: Str::limit(strip_tags($article['html']), 155)),
        'image' => $post->cover?->url() ?? $profile->avatarSrc(),
        'ogType' => 'article',
        'canonical' => $post->url(),
    ];
@endphp
@extends('portfolios.templates.minimal_editorial._layout')

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
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
@endsection

@section('body')
  @if ($preview)
    <p class="me-notice" role="status">Preview — status: <b>{{ $post->status }}</b>@if ($post->published_at?->isFuture()), scheduled for {{ $post->published_at->format('j M Y H:i') }}@endif. Only you can see this page.</p>
  @endif

  <article class="me-article">
    <header class="me-article-head">
      <p class="me-kicker"><a href="{{ route($listRoute) }}">{{ $post->isNote() ? 'Notes' : 'Writing' }}</a></p>
      <h1 class="me-article-title">{{ $post->title }}</h1>
      @if ($post->excerpt)<p class="me-lede">{{ $post->excerpt }}</p>@endif
      <p class="me-meta">
        @if ($post->published_at && ! $post->isNote())<time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->format('j F Y') }}</time> · @endif
        {{ collect([$post->category, $post->readingMinutes().' min read'])->filter()->implode(' · ') }}
      </p>
    </header>

    @if ($post->cover)
      <img class="me-hero" src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?: '' }}">
    @endif

    @if (count($article['toc']) > 1)
      <details class="me-toc" open>
        <summary>On this page</summary>
        <nav aria-label="On this page">
          <ol>@foreach ($article['toc'] as $h)<li class="lvl{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ol>
        </nav>
      </details>
    @endif

    <div class="me-body me-prose-long">{!! $article['html'] !!}</div>

    @if ($post->tags)
      <ul class="me-chips me-post-tags" aria-label="Tags">
        @foreach ($post->tags as $t)<li><a href="{{ route($listRoute, ['tag' => $t]) }}">#{{ $t }}</a></li>@endforeach
      </ul>
    @endif
  </article>

  @if ($related->isNotEmpty())
    <nav class="me-related" aria-labelledby="related-h">
      <h2 id="related-h" class="me-h2">Keep reading</h2>
      <ul class="me-plain">
        @foreach ($related as $r)
          <li><a class="me-title-link" href="{{ $r->url() }}">{{ $r->title }}</a>@if ($r->excerpt)<span class="me-muted"> — {{ Str::limit($r->excerpt, 120) }}</span>@endif</li>
        @endforeach
      </ul>
    </nav>
  @endif
@endsection
