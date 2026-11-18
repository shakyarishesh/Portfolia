{{-- One dated article in a list. Vars: $post, $listRoute ('blog.index'), $level (heading level, default 3). --}}
@php $h = 'h'.($level ?? 3); @endphp
<li class="cj-entry">
  <time class="cj-date" datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->format('M j, Y') }}</time>
  <div>
    <{{ $h }} class="cj-entry-title"><a href="{{ $post->url() }}">{{ $post->title }}</a></{{ $h }}>
    @if ($post->excerpt)<p class="cj-excerpt">{{ $post->excerpt }}</p>@endif
    <p class="cj-meta">
      @if ($post->category)<a href="{{ route('blog.index', ['category' => $post->category]) }}">{{ $post->category }}</a> · @endif
      {{ $post->readingMinutes() }} min read
    </p>
  </div>
</li>
