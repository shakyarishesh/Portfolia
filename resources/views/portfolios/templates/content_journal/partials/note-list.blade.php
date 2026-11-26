{{-- Evergreen notes: grouped by topic (category), alphabetical, no dates. Vars: $notes, $level (topic heading level). --}}
@php
    $groups = $notes->sortBy(fn ($n) => mb_strtolower($n->title))->groupBy(fn ($n) => $n->category ?: 'Miscellaneous')->sortKeys();
    $h = 'h'.($level ?? 3);
@endphp
<div class="cj-garden">
  @foreach ($groups as $topic => $items)
    <div class="cj-topic">
      @if ($groups->count() > 1)<{{ $h }} class="cj-topic-name">{{ $topic }}</{{ $h }}>@endif
      <ul>
        @foreach ($items as $note)
          <li><a href="{{ $note->url() }}">{{ $note->title }}</a>@if ($note->excerpt)<span class="cj-gloss"> — {{ \Illuminate\Support\Str::limit($note->excerpt, 90) }}</span>@endif</li>
        @endforeach
      </ul>
    </div>
  @endforeach
</div>
