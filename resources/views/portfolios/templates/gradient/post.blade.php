@php
    use Illuminate\Support\Str;
    $page = 'post';
    $listRoute = $post->isNote() ? 'notes.index' : 'blog.index';
    $crumb = [$post->isNote() ? 'Notes' : 'Writing', route($listRoute)];
    $meta = [
        'title' => "{$post->title} — {$profile->full_name}",
        'description' => $post->meta_description ?: ($post->excerpt ?: Str::limit(strip_tags($article['html']), 155)),
        'image' => $post->cover?->url() ?? $profile->avatarSrc(),
        'ogType' => 'article',
        'canonical' => $post->url(),
    ];
@endphp
@extends('portfolios.templates.gradient._layout')

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
  <div class="gd-wrap gd-narrow">
    @if ($preview)
      <p class="gd-notice" role="status">Preview — status: <b>{{ $post->status }}</b>@if ($post->published_at?->isFuture()), scheduled for {{ $post->published_at->format('j M Y H:i') }}@endif. Only you can see this page.</p>
    @endif

    <article class="gd-article">
      <header class="gd-article-head">
        <p class="gd-eyebrow"><a href="{{ route($listRoute) }}">{{ $crumb[0] }}</a></p>
        <h1 class="gd-article-title">{{ $post->title }}</h1>
        @if ($post->excerpt)<p class="gd-lede">{{ $post->excerpt }}</p>@endif
        <p class="gd-muted gd-small">
          @if ($post->published_at && ! $post->isNote())<time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->format('j F Y') }}</time> · @endif
          {{ collect([$post->category, $post->readingMinutes().' min read'])->filter()->implode(' · ') }}
        </p>
      </header>

      @if ($post->cover)
        <figure class="gd-frame"><img src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?: '' }}" width="1200" height="675"></figure>
      @endif

      @if (count($article['toc']) > 1)
        <nav class="gd-toc gd-card gd-edge-left" aria-label="On this page">
          <p class="gd-h4">On this page</p>
          <ol>@foreach ($article['toc'] as $h)<li class="lvl{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ol>
        </nav>
      @endif

      <div class="gd-prose gd-article-body">{!! $article['html'] !!}</div>

      @if ($post->tags)
        <ul class="gd-chips gd-chips-links gd-post-tags" aria-label="Tags">
          @foreach ($post->tags as $t)<li><a href="{{ route($listRoute, ['tag' => $t]) }}">#{{ $t }}</a></li>@endforeach
        </ul>
      @endif
    </article>

    @if ($related->isNotEmpty())
      <nav class="gd-block" aria-labelledby="related-h">
        <h2 id="related-h" class="gd-h2">Keep reading</h2>
        <ul class="gd-cards gd-cards-3">
          @foreach ($related as $r)
            <li class="gd-card gd-edge-top">
              <h3 class="gd-h4"><a class="gd-title-link" href="{{ $r->url() }}">{{ $r->title }}</a></h3>
              @if ($r->excerpt)<p class="gd-muted">{{ Str::limit($r->excerpt, 120) }}</p>@endif
            </li>
          @endforeach
        </ul>
      </nav>
    @endif
  </div>
@endsection
