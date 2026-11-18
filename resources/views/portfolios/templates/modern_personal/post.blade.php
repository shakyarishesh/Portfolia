@php
    use Illuminate\Support\Str;
    $page = 'post';
    $listRoute = $post->isNote() ? 'notes.index' : 'blog.index';
    $nav = [[route('home'), 'Home'], [route($listRoute), $post->isNote() ? 'Notes' : 'Writing']];
    $meta = [
        'title' => "{$post->title} — {$profile->full_name}",
        'description' => $post->meta_description ?: ($post->excerpt ?: Str::limit(strip_tags($article['html']), 155)),
        'image' => $post->cover?->url() ?? $profile->avatarSrc(),
        'ogType' => 'article',
        'canonical' => $post->url(),
    ];
@endphp
@extends('portfolios.templates.modern_personal.layout')

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
    <p class="mp-notice" role="status">Preview — status: <b>{{ $post->status }}</b>@if ($post->published_at?->isFuture()), scheduled for {{ $post->published_at->format('j M Y H:i') }}@endif. Only you can see this page.</p>
  @endif

  <article>
    <header class="mp-page-head mp-grid">
      <p class="mp-track mp-meta"><a href="{{ route($listRoute) }}">← {{ $post->isNote() ? 'Notes' : 'Writing' }}</a></p>
      <div>
        <h1 class="mp-title">{{ $post->title }}</h1>
        @if ($post->excerpt)<p class="mp-statement">{{ $post->excerpt }}</p>@endif
      </div>
    </header>

    <div class="mp-grid mp-sec">
      <aside class="mp-track mp-meta" aria-label="About this {{ $post->isNote() ? 'note' : 'article' }}">
        <p>
          @if ($post->published_at && ! $post->isNote())<time datetime="{{ $post->published_at->toAtomString() }}">{{ $post->published_at->format('j F Y') }}</time><br>@endif
          {{ collect([$post->category, $post->readingMinutes().' min read'])->filter()->implode(' · ') }}
        </p>
        @if ($post->tags)
          <ul class="mp-plain" aria-label="Tags">@foreach ($post->tags as $t)<li><a href="{{ route($listRoute, ['tag' => $t]) }}">#{{ $t }}</a></li>@endforeach</ul>
        @endif
        @if (count($article['toc']) > 1)
          <nav class="mp-toc" aria-label="On this page">
            <p class="mp-strong">On this page</p>
            <ol>@foreach ($article['toc'] as $h)<li class="lvl{{ $h['level'] }}"><a href="#{{ $h['id'] }}">{{ $h['text'] }}</a></li>@endforeach</ol>
          </nav>
        @endif
      </aside>

      <div class="mp-article">
        @if ($post->cover)
          <img class="mp-hero" src="{{ $post->cover->url() }}" alt="{{ $post->cover->alt ?: '' }}" width="1200" height="630">
        @endif
        {!! $article['html'] !!}
      </div>
    </div>
  </article>

  @if ($related->isNotEmpty())
    <nav class="mp-sec mp-grid" aria-labelledby="related-h">
      <h2 id="related-h" class="mp-h2">Keep reading</h2>
      <ul class="mp-rows mp-body">
        @foreach ($related as $r)
          <li><a href="{{ $r->url() }}">{{ $r->title }}</a>@if ($r->published_at && ! $r->isNote())<span class="mp-date">{{ $r->published_at->format('M Y') }}</span>@endif</li>
        @endforeach
      </ul>
    </nav>
  @endif
@endsection
