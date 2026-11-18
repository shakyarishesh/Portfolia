<section class="sec about" id="home" aria-labelledby="h-home">
  <header class="sec-head"><p class="eyebrow">About</p><h2 id="h-home">{{ $section->title ?: 'About me' }}</h2></header>
  <div class="about-grid">
    <div class="about-copy">
      @foreach ($profile->aboutParagraphs() as $paragraph)
        <p @class(['big' => $loop->first]) data-reveal>{!! $md($paragraph) !!}</p>
      @endforeach
    </div>
    @if ($profile->avatarSrc())
      <figure class="portrait" data-reveal><img src="{{ $profile->avatarSrc() }}" alt="{{ $profile->full_name }}" width="320" height="320" loading="lazy"></figure>
    @endif
  </div>
  @if ($expertise->isNotEmpty())
    <h3 class="sub">What I'm good at</h3>
    <ul class="tiles">
      @foreach ($expertise as $area)
        <li data-reveal><span class="num" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span><b>{{ $area->title }}</b>@if ($area->description)<span>{{ $area->description }}</span>@endif</li>
      @endforeach
    </ul>
  @endif
  @if ($testimonials->isNotEmpty())
    <h3 class="sub">Kind words</h3>
    <div class="quotes">
      @foreach ($testimonials as $t)
        <figure class="quote" data-reveal>
          <blockquote><p>{{ $t->quote }}</p></blockquote>
          <figcaption>@if ($t->url)<a href="{{ $t->url }}" rel="noopener" target="_blank">{{ $t->name }}</a>@else{{ $t->name }}@endif <span class="muted">{{ collect([$t->position, $t->company])->filter()->implode(', ') }}</span></figcaption>
        </figure>
      @endforeach
    </div>
  @endif
</section>
