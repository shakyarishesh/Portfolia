<div class="wrap hero">
  @if ($profile->avatarSrc())
    <img class="avatar reveal" src="{{ $profile->avatarSrc() }}" alt="{{ $profile->full_name }}" width="320" height="320">
  @endif
  <p class="eyebrow reveal">{{ $section->eyebrow ?: "Hello, I'm" }}</p>
  @if ($currentJob)
    <p class="prompt reveal"><span aria-hidden="true">$ </span>whoami <span class="muted">→ {{ $currentJob->role }} @ {{ $currentJob->company }}@if ($profile->location) · {{ $profile->location }}@endif</span></p>
  @endif
  <h1 class="name reveal">@foreach ($names as $n)<span class="split">{{ $n }}</span>@if (! $loop->last) @endif @endforeach</h1>
  @if ($profile->typed_phrases)
    <h2 class="role reveal">I build <span class="typed" data-words="{{ implode('|', array_map(fn ($p) => str_replace('|', '/', $p), $profile->typed_phrases)) }}">{{ $profile->typed_phrases[0] }}</span><span class="caret" aria-hidden="true">|</span></h2>
  @else
    <h2 class="role reveal">{{ $profile->headline }}</h2>
  @endif
  @if ($profile->intro)
    <p class="lead reveal">{!! $md($profile->intro) !!}</p>
  @endif
  <div class="cta reveal">
    @if ($sections->contains('key', 'projects'))
      <a class="btn primary magnetic" href="#projects" data-tab="projects">Explore projects</a>
    @endif
    @if ($sections->contains('key', 'contact'))
      <a class="btn magnetic" href="#contact" data-tab="contact">Get in touch</a>
    @endif
    @if ($profile->resume)
      <a class="btn magnetic" href="{{ route('resume') }}">Download CV</a>
    @endif
  </div>
  @php
    $icons = [
      'github' => '<path d="M12 .5a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.4-1.8-1.4-1.8-1.1-.7.1-.7.1-.7 1.2.1 1.9 1.2 1.9 1.2 1.1 1.9 2.9 1.3 3.6 1 .1-.8.4-1.3.8-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2-.1-.3-.5-1.5.1-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0C17.3 4.7 18.3 5 18.3 5c.7 1.7.2 2.9.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .5Z"/>',
      'linkedin' => '<path d="M20.4 20.5h-3.6v-5.6c0-1.3 0-3-1.8-3s-2.1 1.4-2.1 2.9v5.7H9.4V9h3.4v1.6c.5-.9 1.6-1.8 3.4-1.8 3.6 0 4.3 2.4 4.3 5.5v6.2ZM5.3 7.4a2.1 2.1 0 1 1 0-4.2 2.1 2.1 0 0 1 0 4.2ZM7.1 20.5H3.5V9h3.6v11.5ZM22.2 0H1.8C.8 0 0 .8 0 1.7v20.6c0 .9.8 1.7 1.8 1.7h20.4c1 0 1.8-.8 1.8-1.7V1.7C24 .8 23.2 0 22.2 0Z"/>',
    ];
  @endphp
  @if ($links->isNotEmpty() || $profile->email)
    <ul class="socials reveal" aria-label="Elsewhere">
      @foreach ($links as $link)
        <li><a href="{{ $link->url }}" target="_blank" rel="noopener me" aria-label="{{ $link->label }}">@isset($icons[$link->platform])<svg viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$link->platform] !!}</svg>@endisset<span>{{ $link->label }}</span></a></li>
      @endforeach
      @if ($profile->email)
        <li><a href="mailto:{{ $profile->email }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2 5h20v14H2V5Zm2 2v.4l8 5.3 8-5.3V7H4Zm16 2.8-8 5.3-8-5.3V17h16V9.8Z"/></svg><span>{{ $profile->email }}</span></a></li>
      @endif
    </ul>
  @endif
  @if ($profile->open_to_work)
    <p class="status reveal"><span class="status-dot" aria-hidden="true"></span> Open to new opportunities</p>
  @endif
</div>

@if ($profile->about || $expertise->isNotEmpty() || $testimonials->isNotEmpty())
  <div class="wrap about">
    @if ($profile->about)
      <h3 class="section-title reveal">{{ $section->title ?: 'About me' }}</h3>
      <div class="about-grid">
        @foreach ($profile->aboutParagraphs() as $paragraph)
          <p class="reveal">{!! $md($paragraph) !!}</p>
        @endforeach
      </div>
    @endif
    @if ($expertise->isNotEmpty())
      <h3 class="section-title sub reveal">Technical expertise</h3>
      <ul class="traits">
        @foreach ($expertise as $area)
          <li class="reveal tilt"><i>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</i><b>{{ $area->title }}</b><span>{{ $area->description }}</span></li>
        @endforeach
      </ul>
    @endif
    @if ($testimonials->isNotEmpty())
      <h3 class="section-title sub reveal">What colleagues say</h3>
      <div class="quotes">
        @foreach ($testimonials as $t)
          <figure class="quote reveal">
            <blockquote>{{ $t->quote }}</blockquote>
            <figcaption>@if ($t->url)<a href="{{ $t->url }}" target="_blank" rel="noopener">{{ $t->name }}</a>@else{{ $t->name }}@endif<span>{{ collect([$t->position, $t->company])->filter()->implode(', ') }}</span></figcaption>
          </figure>
        @endforeach
      </div>
    @endif
    @if ($sections->count() > 1)
      <p class="hint reveal">Scroll ↓ or use ← → to switch sections</p>
    @endif
  </div>
@endif
