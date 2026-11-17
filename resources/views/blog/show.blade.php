@php
  $url = route('blog.show', $post);
  $hasCode = str_contains($article['html'], '<pre><code');
@endphp
<!doctype html>
<html lang="en" class="doc">
<head>
  @include('portfolio.partials.head', [
      'title' => "{$post->title} — {$profile->full_name}",
      'description' => $post->meta_description ?: ($post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($article['html']), 155)),
      'image' => $post->cover?->url() ?? $profile->avatarSrc(),
      'ogType' => 'article',
      'canonical' => $preview ? null : $url,
  ])
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
      'author' => ['@type' => 'Person', 'name' => $profile->full_name, 'url' => url('/')],
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
  @if ($hasCode)
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/github-dark.min.css">
  @endif
</head>
<body>
  @if ($preview)
    <div class="preview-bar" role="status">Preview — status: <b>{{ $post->status }}</b>@if ($post->published_at?->isFuture()), scheduled for {{ $post->published_at->format('j M Y H:i') }}@endif. <a href="{{ route('admin.resources.edit', ['posts', $post->id]) }}">Back to editor</a></div>
  @endif
  <div class="bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><div class="grid"></div></div>
  <main class="page">
    <div class="wrap narrow">
      <a class="back" href="{{ route('blog.index') }}">← All articles</a>
      <p class="eyebrow">{{ collect([$post->category, $post->published_at?->format('j F Y'), $post->readingMinutes().' min read'])->filter()->implode(' · ') }}</p>
      <h1 class="title big">{{ $post->title }}</h1>
      @if ($post->excerpt)<p class="lead">{{ $post->excerpt }}</p>@endif
      @if ($post->cover)<img class="hero-shot" src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?: '' }}">@endif

      <div class="doc-grid article-grid">
        <article class="prose">{!! $article['html'] !!}</article>
        <aside>
          @if (count($article['toc']) > 1)
            <nav class="toc" aria-label="On this page">
              <h2>On this page</h2>
              <ol>@foreach ($article['toc'] as $h)<li class="lvl{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ol>
            </nav>
          @endif
          @if ($post->tags)
            <h2>Tags</h2>
            <div class="techs">@foreach ($post->tags as $t)<a href="{{ route('blog.index', ['tag' => $t]) }}">#{{ $t }}</a>@endforeach</div>
          @endif
          <h2>Share</h2>
          <div class="share">
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ rawurlencode($url) }}" target="_blank" rel="noopener">LinkedIn</a>
            <a href="https://twitter.com/intent/tweet?url={{ rawurlencode($url) }}&text={{ rawurlencode($post->title) }}" target="_blank" rel="noopener">X</a>
            <button type="button" class="linkish" data-copy="{{ $url }}">Copy link</button>
          </div>
        </aside>
      </div>

      @if ($related->isNotEmpty())
        <h2>Related articles</h2>
        <div class="related">
          @foreach ($related as $r)
            <a class="card" href="{{ route('blog.show', $r) }}"><span class="tag">{{ $r->published_at->format('j M Y') }}</span><h3>{{ $r->title }}</h3>@if ($r->excerpt)<p>{{ \Illuminate\Support\Str::limit($r->excerpt, 120) }}</p>@endif</a>
          @endforeach
        </div>
      @endif
    </div>
  </main>
  @if ($hasCode)
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/highlight.min.js"></script>
    <script>hljs.highlightAll();</script>
  @endif
  <script>
    document.querySelector('[data-copy]')?.addEventListener('click', async e => {
      try { await navigator.clipboard.writeText(e.target.dataset.copy); e.target.textContent = 'Copied ✓'; }
      catch { e.target.textContent = 'Copy failed'; }
      setTimeout(() => e.target.textContent = 'Copy link', 2000);
    });
  </script>
</body>
</html>
