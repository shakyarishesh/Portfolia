{{--
  Minimal Editorial shell. Pages @extends this with ['meta' => [...meta partial vars], 'page' => 'home|project|posts|post'].
  Settings: theme (dark|light) and accent (teal|amber|violet|rose) become body classes; style.css does the rest.
--}}
@php
    $asset = fn (string $file) => asset("templates/minimal_editorial/{$file}").'?v='.@filemtime(public_path("templates/minimal_editorial/{$file}"));
@endphp
<!doctype html>
<html lang="en">
<head>
  @include('portfolios.partials.meta', $meta ?? [])
  <meta name="color-scheme" content="{{ $settings['theme'] ?? 'dark' }}">
  <link rel="stylesheet" href="{{ $asset('style.css') }}">
  <script src="{{ $asset('script.js') }}" defer></script>
  @yield('head')
</head>
<body class="me theme-{{ $settings['theme'] ?? 'dark' }} accent-{{ $settings['accent'] ?? 'teal' }} page-{{ $page ?? 'home' }}">
  @include('portfolios.partials.preview-banner')
  @include('portfolios.partials.intro')
  <a class="me-skip" href="#content">Skip to content</a>
  @if (($page ?? 'home') === 'home')
    @yield('body')
  @else
    <header class="me-bar">
      <a class="me-bar-name" href="{{ route('home') }}">{{ $profile->full_name }}</a>
      @if ($profile->headline)<span class="me-bar-role">{{ $profile->headline }}</span>@endif
    </header>
    <main id="content" class="me-page" tabindex="-1">
      @yield('body')
    </main>
    <footer class="me-foot me-page-foot"><a href="{{ route('home') }}">← {{ $profile->full_name }}</a></footer>
  @endif
</body>
</html>
