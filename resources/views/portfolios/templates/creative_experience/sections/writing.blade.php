<section class="sec" id="writing" aria-labelledby="h-writing">
  <header class="sec-head"><p class="eyebrow">Writing</p><h2 id="h-writing">{{ $hasBlog ? 'Latest writing' : 'Notes' }}</h2></header>
  @if ($hasBlog)
    <ul class="posts">
      @foreach ($posts->take(3) as $post)
        <li data-reveal>
          <span class="eyebrow">{{ collect([$post->published_at?->format('j M Y'), $post->readingMinutes().' min read'])->filter()->implode(' · ') }}</span>
          <h3><a href="{{ $post->url() }}">{{ $post->title }}</a></h3>
          @if ($post->excerpt)<p class="muted">{{ $post->excerpt }}</p>@endif
        </li>
      @endforeach
    </ul>
  @endif
  @if ($hasNotes && ! $hasBlog)
    <ul class="tags notes">@foreach ($notes->take(12) as $note)<li><a href="{{ $note->url() }}">{{ $note->title }}</a></li>@endforeach</ul>
  @endif
  <p class="cta">
    @if ($hasBlog)<a class="btn" href="{{ route('blog.index') }}">All articles <span aria-hidden="true">→</span></a>@endif
    @if ($hasNotes)<a class="btn" href="{{ route('notes.index') }}">All notes <span aria-hidden="true">→</span></a>@endif
  </p>
</section>
