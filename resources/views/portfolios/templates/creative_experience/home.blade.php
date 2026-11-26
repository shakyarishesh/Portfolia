@extends('portfolios.templates.creative_experience.layout')

@php
    use Illuminate\Support\Str;
    $md = fn (?string $text) => Str::inlineMarkdown((string) $text, ['html_input' => 'escape', 'allow_unsafe_links' => false]);
    $names = preg_split('/\s+/', trim((string) $profile->full_name));
    $hasAbout = filled($profile->about) || $expertise->isNotEmpty() || $testimonials->isNotEmpty();
    $hasWriting = $hasBlog || $hasNotes;

    // Effective hero: orbits need skills to orbit; motion off or "none" means no canvas at all.
    $orbitSkills = $skillCategories->flatMap->skills->sortByDesc(fn ($s) => (int) $s->level)->take(8)->pluck('name')->values();
    $heroMode = $settings['hero'] ?? 'particles';
    if ($heroMode === 'orbits' && $orbitSkills->isEmpty()) {
        $heroMode = 'particles';
    }
    $canvas = ($settings['motion'] ?? true) && $heroMode !== 'none';

    $nav = $sections->map(fn ($s) => ['#'.($s->key === 'home' && ! $hasAbout ? 'top' : $s->key), $s->nav_label])->all();
    if ($hasWriting) {
        $nav[] = ['#writing', $hasBlog ? 'Writing' : 'Notes'];
    }
    $meta = ['canonical' => route('home')];
    $homeSection = $sections->firstWhere('key', 'home');
@endphp

@section('head')
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
  ]), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@section('content')
  <section class="hero" id="top" aria-labelledby="hero-name">
    {{-- Static art: the fallback for reduced motion / low power / small screens / no JS. --}}
    <div class="hero-art" aria-hidden="true">
      <span class="blob b1"></span><span class="blob b2"></span><span class="blob b3"></span>
      @if ($heroMode === 'orbits')
        <ul class="orbit-static" style="--n: {{ $orbitSkills->count() }}">
          @foreach ($orbitSkills as $skill)<li style="--i: {{ $loop->index }}">{{ $skill }}</li>@endforeach
        </ul>
      @endif
    </div>
    @if ($canvas)
      <canvas class="hero-canvas" aria-hidden="true" data-mode="{{ $heroMode }}" data-skills="{{ $orbitSkills->toJson() }}"></canvas>
    @endif

    <div class="hero-copy">
      <p class="kicker">{{ $homeSection?->eyebrow ?: "Hello, I'm" }}</p>
      <h1 class="name" id="hero-name">@foreach ($names as $n)<span class="w" style="--d: {{ $loop->index }}">{{ $n }}</span>@if (! $loop->last) @endif @endforeach</h1>
      @if ($profile->headline)<p class="role">{{ $profile->headline }}</p>@endif
      @if ($profile->typed_phrases)
        <ul class="phrases" aria-label="What I build">
          @foreach ($profile->typed_phrases as $phrase)<li style="--r: {{ ($loop->index % 3 - 1) * 2 }}deg">{{ $phrase }}</li>@endforeach
        </ul>
      @endif
      @if ($profile->intro)<p class="lead">{!! $md($profile->intro) !!}</p>@endif
      <div class="cta">
        @if ($sections->contains('key', 'projects'))<a class="btn primary" href="#projects">See the work</a>@endif
        @if ($sections->contains('key', 'contact'))<a class="btn" href="#contact">Say hello</a>@endif
        @if ($profile->resume)<a class="btn" href="{{ route('resume') }}">Download CV</a>@endif
      </div>
      @if ($currentJob || $profile->open_to_work || $profile->location)
        <ul class="hero-facts">
          @if ($currentJob)<li><span class="muted">Now</span> {{ $currentJob->role }} @ {{ $currentJob->company }}</li>@endif
          @if ($profile->location)<li><span class="muted">Based in</span> {{ $profile->location }}</li>@endif
          @if ($profile->open_to_work)<li class="sticker">Open to new work</li>@endif
        </ul>
      @endif
    </div>
  </section>

  @foreach ($sections as $section)
    @if ($section->key === 'contact' && $hasWriting)
      @include('portfolios.templates.creative_experience.sections.writing')
    @endif
    @if ($section->key !== 'home' || $hasAbout)
      @include(\App\Models\Portfolio::isFreeTextSection($section->key) ? 'portfolios.templates.creative_experience.sections._text' : 'portfolios.templates.creative_experience.sections.'.$section->key, ['section' => $section])
    @endif
  @endforeach
  @if ($hasWriting && ! $sections->contains('key', 'contact'))
    @include('portfolios.templates.creative_experience.sections.writing')
  @endif
@endsection
