{{--
  Content Journal shell. Pages @extends this and fill @section('main'); optional @push('head').
  Vars: $meta (passed to the shared meta partial), $navBlog/$navNotes (default: section switched on).
  Theme: data-default = admin setting (system|light|dark, handled in CSS via color-scheme);
  data-theme = visitor's saved choice, applied before first paint to avoid a flash.
--}}
@php
    $navBlog ??= \App\Http\Controllers\BlogController::enabled('article');
    $navNotes ??= \App\Http\Controllers\BlogController::enabled('note');
    $asset = fn (string $file) => asset("templates/content_journal/{$file}").'?v='.filemtime(public_path("templates/content_journal/{$file}"));
@endphp
<!doctype html>
<html lang="en" data-default="{{ $settings['theme'] ?? 'system' }}">
<head>
  @include('portfolios.partials.meta', $meta ?? [])
  <script>(function(d){try{var t=localStorage.getItem('cj-theme');if(t==='light'||t==='dark')d.setAttribute('data-theme',t)}catch(e){}})(document.documentElement)</script>
  <link rel="stylesheet" href="{{ $asset('style.css') }}">
  @stack('head')
</head>
<body class="cj" data-template="content_journal">
  @include('portfolios.partials.preview-banner')
  <a class="cj-skip" href="#main">Skip to content</a>
  <header class="cj-top">
    <nav class="cj-nav" aria-label="Main">
      <a class="cj-mark" href="{{ route('home') }}" @if (request()->routeIs('home')) aria-current="page" @endif>{{ $profile->full_name }}</a>
      <ul>
        @if ($navBlog)<li><a href="{{ route('blog.index') }}" @if (request()->routeIs('blog.*')) aria-current="page" @endif>Writing</a></li>@endif
        @if ($navNotes)<li><a href="{{ route('notes.index') }}" @if (request()->routeIs('notes.*')) aria-current="page" @endif>Notes</a></li>@endif
        <li><a href="{{ route('home') }}#work">Work</a></li>
      </ul>
      {{-- Needs JS, so it stays hidden until script.js wires it up. --}}
      <button type="button" class="cj-theme" aria-pressed="false" hidden>
        <span aria-hidden="true">◐</span> Dark mode
      </button>
    </nav>
  </header>

  <main id="main" class="cj-main" tabindex="-1">
    @yield('main')
  </main>

  <footer class="cj-foot">
    <p>© {{ now()->year }} {{ $profile->full_name }}@if ($profile->headline) · {{ $profile->headline }}@endif</p>
  </footer>
  <script src="{{ $asset('script.js') }}" defer></script>
</body>
</html>
