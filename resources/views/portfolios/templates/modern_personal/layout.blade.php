{{--
  Modern Personal shell. Pages @extends this with ['meta' => [...meta partial vars], 'page' => 'home|project|posts|post', 'nav' => [[href, label], ...]].
  Settings become classes on <html>: accent-{ink|olive|terracotta}, serif-headings on/off. Light theme only, no JavaScript.
--}}
<!doctype html>
<html lang="en" class="mp accent-{{ $settings['accent'] ?? 'ink' }}{{ ($settings['serif_headings'] ?? true) ? ' mp-serif' : '' }}" data-template="modern_personal">
<head>
  @include('portfolios.partials.meta', $meta ?? [])
  <meta name="color-scheme" content="light">
  <link rel="stylesheet" href="{{ asset('templates/modern_personal/style.css') }}?v={{ @filemtime(public_path('templates/modern_personal/style.css')) }}">
  @yield('head')
</head>
<body class="page-{{ $page ?? 'home' }}">
  @include('portfolios.partials.preview-banner')
  @include('portfolios.partials.cursor')
  @include('portfolios.partials.intro')
  <a class="mp-skip" href="#content">Skip to content</a>

  <header class="mp-top mp-wrap">
    <a class="mp-brand" href="{{ route('home') }}">{{ $profile->full_name }}</a>
    @if (! empty($nav))
      <nav class="mp-nav" aria-label="Primary">
        <ul>
          @foreach ($nav as [$href, $label])
            <li><a href="{{ $href }}">{{ $label }}</a></li>
          @endforeach
        </ul>
      </nav>
    @endif
  </header>

  <main id="content" class="mp-wrap" tabindex="-1">
    @yield('body')
  </main>

  <footer class="mp-wrap">
    <div class="mp-foot">
      <p>© {{ now()->year }} {{ $profile->full_name }}</p>
      <p><a href="#content">Back to top</a></p>
    </div>
  </footer>
</body>
</html>
