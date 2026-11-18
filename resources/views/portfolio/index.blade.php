@php
    use Illuminate\Support\Str;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $fx = ['home' => 'rise', 'experience' => 'skew', 'projects' => 'zoom', 'skills' => 'circle', 'education' => 'skew', 'contact' => 'rise'];
    $names = preg_split('/\s+/', trim($profile->full_name));
@endphp
<!doctype html>
<html lang="en">
<head>
  @include('portfolio.partials.head', ['canonical' => route('home')])
  @if (($settings['intro'] ?? true) && empty($demo))
  <script>try { if (!localStorage.getItem(@json('seenIntro:'.$portfolio->username)) && !matchMedia('(prefers-reduced-motion: reduce)').matches) document.documentElement.classList.add('intro'); } catch (e) {}</script>
  @endif
  <script type="application/ld+json">{!! json_encode(array_filter([
      '@context' => 'https://schema.org',
      '@type' => 'Person',
      'name' => $profile->full_name,
      'jobTitle' => $profile->headline,
      'url' => route('home'),
      'image' => $profile->avatarSrc(),
      'email' => $profile->email ? 'mailto:'.$profile->email : null,
      'address' => $profile->location,
      'worksFor' => $currentJob ? ['@type' => 'Organization', 'name' => $currentJob->company, 'url' => $currentJob->company_url] : null,
      'alumniOf' => $education->map(fn ($e) => ['@type' => 'EducationalOrganization', 'name' => $e->institution])->all() ?: null,
      'sameAs' => $links->pluck('url')->all() ?: null,
      'knowsAbout' => $skillCategories->flatMap->skills->pluck('name')->all() ?: null,
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
</head>
<body>
  @include('portfolios.partials.preview-banner')
  <a class="skip" href="#{{ $sections->first()->key }}">Skip to content</a>
  <div class="intro-screen" aria-hidden="true" data-words="{{ json_encode(\App\Support\Templates::introWords($settings ?? [])) }}" data-seen-key="seenIntro:{{ $portfolio->username }}"><span></span></div>
  <div class="bg" aria-hidden="true"><span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span><div class="grid"></div></div>
  <div class="cursor-glow" aria-hidden="true"></div>
  <div class="cursor-ring" aria-hidden="true"></div>
  <div class="cursor-dot" aria-hidden="true"></div>

  <header class="nav">
    <a class="logo" href="#{{ $sections->first()->key }}" data-tab="{{ $sections->first()->key }}" aria-label="{{ $profile->full_name }} — home">{{ Str::lower(collect($names)->map(fn ($n) => Str::substr($n, 0, 1))->implode('')) }}<span>.</span></a>
    <nav class="tabs" aria-label="Sections">
      <span class="pill" aria-hidden="true"></span>
      @foreach ($sections as $section)
        <a href="#{{ $section->key }}" data-tab="{{ $section->key }}">{{ $section->nav_label }}</a>
      @endforeach
    </nav>
    <div class="nav-tools">
      @if ($hasBlog)<a class="nav-link" href="{{ route('blog.index') }}">Blog</a>@endif
      @if (empty($demo))<a class="nav-link" href="{{ route('playground') }}">API</a>@endif{{-- no playground in the session-only demo --}}
      <button class="icon-btn" type="button" data-terminal-open aria-label="Open interactive terminal" title="Terminal ( ` )"><span aria-hidden="true">&gt;_</span></button>
      <details class="accent-picker">
        <summary class="icon-btn" aria-label="Choose accent colour" title="Accent colour"><span class="swatch" aria-hidden="true"></span></summary>
        <div class="accent-menu" role="group" aria-label="Accent colour">
          @foreach (['violet' => 'Violet', 'blue' => 'Electric blue', 'emerald' => 'Emerald', 'amber' => 'Amber'] as $key => $label)
            <button type="button" data-accent="{{ $key }}" aria-label="{{ $label }}" title="{{ $label }}"></button>
          @endforeach
        </div>
      </details>
    <button class="theme-toggle" type="button" aria-label="Toggle light and dark mode">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <mask id="moon"><rect width="24" height="24" fill="#fff"/><circle class="moon-cut" cx="24" cy="4" r="8" fill="#000"/></mask>
        <circle class="sun-core" cx="12" cy="12" r="5" mask="url(#moon)"/>
        <g class="sun-rays"><path d="M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></g>
      </svg>
    </button>
    </div>
  </header>

  <div class="dots" aria-hidden="true"></div>
  <div class="progress" aria-hidden="true"><span></span></div>

  <main>
    @foreach ($sections as $section)
      <section class="panel" id="{{ $section->key }}" data-fx="{{ $fx[$section->key] ?? 'rise' }}" aria-label="{{ $section->nav_label }}">
        @include('portfolio.sections.'.$section->key, ['section' => $section, 'md' => $md, 'names' => $names])
      </section>
    @endforeach
  </main>

  <dialog class="terminal" aria-label="Interactive terminal" data-api="{{ route('api.portfolio') }}">
    <div class="term-bar"><span class="term-dots" aria-hidden="true"><i></i><i></i><i></i></span><span>guest@{{ request()->getHost() }}: ~</span><button type="button" class="term-close" aria-label="Close terminal">✕</button></div>
    <div class="term-out" role="log" aria-live="polite"></div>
    <form class="term-line" autocomplete="off"><label for="term-in">$</label><input id="term-in" name="cmd" spellcheck="false" autocapitalize="off" aria-label="Command (type help)" maxlength="100"></form>
  </dialog>

  @if ($assistant)
    <div class="assistant" data-url="{{ route('assistant') }}">
      <button class="assistant-toggle" type="button" aria-expanded="false" aria-controls="assistant-panel">Ask about {{ $names[0] }}</button>
      <section class="assistant-panel" id="assistant-panel" hidden aria-label="Portfolio assistant">
        <header><b>Ask about {{ $names[0] }}</b><small>AI answers from this portfolio's content only. It can be wrong — check the details or get in touch.</small></header>
        <div class="assistant-log" role="log" aria-live="polite"></div>
        <form class="assistant-form"><label class="sr-only" for="assistant-q">Your question</label><input id="assistant-q" name="question" maxlength="500" required placeholder="e.g. Which projects used Laravel?"><button class="btn primary" type="submit">Ask</button></form>
      </section>
    </div>
  @endif

  <script src="{{ asset('assets/main.js') }}?v={{ filemtime(public_path('assets/main.js')) }}"></script>
  <script type="module" src="{{ asset('assets/bg.js') }}?v={{ filemtime(public_path('assets/bg.js')) }}"></script>
</body>
</html>
