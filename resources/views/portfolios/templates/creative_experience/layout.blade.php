{{--
  Creative Experience layout. Children set: $nav ([[href, label], ...]), optional $meta (for the shared meta partial),
  optional $heroMode (effective hero after fallbacks), and sections "head" / "content".
  Everything is server-rendered; script.js only adds motion + the project selector. Motion is opt-in via html.fx.
--}}
@php
    $asset = fn (string $f) => asset("templates/creative_experience/{$f}").'?v='.filemtime(public_path("templates/creative_experience/{$f}"));
    $initials = collect(preg_split('/\s+/', trim((string) $profile->full_name)))->map(fn ($n) => \Illuminate\Support\Str::substr($n, 0, 1))->take(2)->implode('');
@endphp
<!doctype html>
<html lang="en" class="ce" data-template="creative_experience" data-palette="{{ $settings['palette'] ?? 'sunset' }}" data-hero="{{ $heroMode ?? ($settings['hero'] ?? 'particles') }}" data-motion="{{ ($settings['motion'] ?? true) ? 'on' : 'off' }}">
<head>
  @include('portfolios.partials.meta', $meta ?? [])
  <link rel="stylesheet" href="{{ $asset('style.css') }}">
  <script src="{{ $asset('script.js') }}" defer></script>
  @yield('head')
</head>
<body>
  @include('portfolios.partials.preview-banner')
  <a class="skip" href="#main">Skip to content</a>
  <header class="top">
    <a class="brand" href="{{ route('home') }}"><span class="mark" aria-hidden="true">{{ $initials }}</span><span class="brand-name">{{ $profile->full_name }}</span></a>
    <nav class="site-nav" aria-label="Primary">
      <ol>
        @foreach ($nav as [$href, $label])
          <li><a href="{{ $href }}"><span class="n" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span>{{ $label }}</a></li>
        @endforeach
      </ol>
    </nav>
  </header>
  <main id="main" tabindex="-1">
    @yield('content')
  </main>
  <footer class="foot">
    <p>© {{ now()->year }} {{ $profile->full_name }}</p>
    <p class="muted">Motion is optional here — everything works without it.</p>
    <a href="#main">Back to top <span aria-hidden="true">↑</span></a>
  </footer>
</body>
</html>
