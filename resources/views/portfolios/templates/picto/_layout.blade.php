{{--
  Picto shell. Pages @extends this with $meta (shared meta partial vars), $page (home|project|posts|post)
  and $nav: list of ['href', 'emoji', 'label', 'key' => section id for scroll-spy|null].
  Settings: palette (candy|tropical|sunrise) → data-palette; decorations → data-deco. style.css does the rest.
--}}
@php
    $asset = fn (string $file) => asset("templates/picto/{$file}").'?v='.@filemtime(public_path("templates/picto/{$file}"));
    $palette = $settings['palette'] ?? 'candy';
    $initials = collect(preg_split('/\s+/', trim((string) $profile->full_name)))->filter()->map(fn ($n) => mb_substr($n, 0, 1))->take(2)->implode('') ?: '★';
@endphp
<!doctype html>
<html lang="en" data-template="picto">
<head>
  @include('portfolios.partials.meta', $meta ?? [])
  <meta name="color-scheme" content="light">
  <link rel="stylesheet" href="{{ $asset('style.css') }}">
  <script src="{{ $asset('script.js') }}" defer></script>
  @yield('head')
</head>
<body class="pc palette-{{ $palette }} page-{{ $page ?? 'home' }}" data-palette="{{ $palette }}" data-deco="{{ ($settings['decorations'] ?? true) ? 'on' : 'off' }}">
  @include('portfolios.partials.preview-banner')
  <a class="pc-skip" href="#content">Skip to content</a>

  <header class="pc-bar">
    <a class="pc-brand" href="{{ route('home') }}"><span class="pc-brand-mark" aria-hidden="true">{{ $initials }}</span><span class="pc-brand-name">{{ $profile->full_name }}</span></a>
    @if (! empty($nav))
      <nav class="pc-nav" aria-label="Sections">
        <ul>
          @foreach ($nav as $item)
            <li><a href="{{ $item['href'] }}" @if ($item['key'] ?? null) data-nav="{{ $item['key'] }}" @endif class="c{{ $loop->index % 4 + 1 }}"><span class="pc-nav-ico" aria-hidden="true">{{ $item['emoji'] }}</span><span class="pc-nav-label">{{ $item['label'] }}</span></a></li>
          @endforeach
        </ul>
      </nav>
    @endif
  </header>

  <main id="content" class="pc-main" tabindex="-1">
    @yield('body')
  </main>

  <footer class="pc-foot">
    <p class="pc-foot-name"><span aria-hidden="true">✌️</span> © {{ now()->year }} {{ $profile->full_name }}</p>
    @yield('footer-links')
    <p class="pc-foot-note">Made with plain CSS, system emoji and a little colour. <a href="#content">Back to top <span aria-hidden="true">⬆️</span></a></p>
  </footer>
</body>
</html>
