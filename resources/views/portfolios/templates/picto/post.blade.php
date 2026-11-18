@php
    use Illuminate\Support\Str;
    $page = 'post';
    $listRoute = $post->isNote() ? 'notes.index' : 'blog.index';
    $listLabel = $post->isNote() ? 'Notes' : 'Writing';
    $meta = [
        'title' => "{$post->title} — {$profile->full_name}",
        'description' => $post->meta_description ?: ($post->excerpt ?: Str::limit(strip_tags($article['html']), 155)),
        'image' => $post->cover?->url() ?? $profile->avatarSrc(),
        'ogType' => 'article',
        'canonical' => $post->url(),
    ];
    $nav = [
        ['href' => route('home'), 'emoji' => '🏠', 'label' => 'Home'],
        ['href' => route($listRoute), 'emoji' => $post->isNote() ? '🗒️' : '📝', 'label' => $listLabel],
    ];
@endphp
@extends('portfolios.templates.picto._layout')

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
  @if ($preview)
    <p class="pc-notice pc-wrap" role="status">Preview — status: <b>{{ $post->status }}</b>@if ($post->published_at?->isFuture()), scheduled for {{ $post->published_at->format('j M Y H:i') }}@endif. Only you can see this page.</p>
  @endif

  <article class="pc-article pc-wrap">
    <header class="pc-article-head">
      <p class="pc-eyebrow"><a href="{{ route($listRoute) }}"><span aria-hidden="true">{{ $post->isNote() ? '🗒️' : '📝' }}</span> {{ $listLabel }}</a></p>
      <h1>{{ $post->title }}</h1>
      @if ($post->excerpt)<p class="pc-lede">{{ $post->excerpt }}</p>@endif
      <p class="pc-meta">
        @if ($post->published_at && ! $post->isNote())<time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->format('j F Y') }}</time> · @endif
        {{ collect([$post->category, $post->readingMinutes().' min read'])->filter()->implode(' · ') }}
      </p>
    </header>

    @if ($post->cover)
      <img class="pc-hero-img" src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?: '' }}" width="1280" height="720">
    @endif

    @if (count($article['toc']) > 1)
      <details class="pc-toc pc-card c2" open>
        <summary><span aria-hidden="true">🧭</span> On this page</summary>
        <nav aria-label="On this page">
          <ol>@foreach ($article['toc'] as $h)<li class="lvl{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ol>
        </nav>
      </details>
    @endif

    <div class="pc-prose pc-long">{!! $article['html'] !!}</div>

    @if ($post->tags)
      <ul class="pc-tags pc-tag-filter" aria-label="Tags">
        @foreach ($post->tags as $t)<li><a href="{{ route($listRoute, ['tag' => $t]) }}">#{{ $t }}</a></li>@endforeach
      </ul>
    @endif
  </article>

  @if ($related->isNotEmpty())
    <nav class="pc-related pc-wrap" aria-labelledby="related-h">
      <h2 id="related-h"><span class="pc-mini-marker" aria-hidden="true">📚</span> Keep reading</h2>
      <ul class="pc-grid">
        @foreach ($related as $r)
          <li class="pc-card pc-tint c{{ $loop->index % 4 + 1 }}"><a class="pc-stretch" href="{{ $r->url() }}">{{ $r->title }}</a>@if ($r->excerpt)<p>{{ Str::limit($r->excerpt, 120) }}</p>@endif</li>
        @endforeach
      </ul>
    </nav>
  @endif
@endsection
