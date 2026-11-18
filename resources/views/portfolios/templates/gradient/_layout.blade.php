{{--
  Gradient shell. Pages @extends this with $meta (meta partial vars), $page (home|project|posts|post) and,
  on home, $nav (section links for the header); elsewhere optionally $crumb = [label, url]. Settings reach CSS as data-attributes on <html>.
--}}
@php
    $asset = fn (string $file) => asset("templates/gradient/{$file}").'?v='.@filemtime(public_path("templates/gradient/{$file}"));
    $page ??= 'home';
    $nav ??= collect();
    $home = route('home');
    $onHome = $page === 'home';
@endphp
<!doctype html>
<html lang="en" class="gd" data-template="gradient" data-gradient="{{ $settings['gradient'] ?? 'blue_violet' }}" data-shapes="{{ ($settings['shapes'] ?? true) ? 'on' : 'off' }}">
<head>
  @include('portfolios.partials.meta', $meta ?? [])
  <meta name="color-scheme" content="light">
  <link rel="stylesheet" href="{{ $asset('style.css') }}">
  <script src="{{ $asset('script.js') }}" defer></script>
  @yield('head')
</head>
<body class="gd-body page-{{ $page }}">
  @include('portfolios.partials.preview-banner')
  @include('portfolios.partials.intro')
  <a class="gd-skip" href="#content">Skip to content</a>

  <header class="gd-header">
    <div class="gd-header-in">
      <a class="gd-brand" href="{{ $onHome ? '#home' : $home }}">
        <span class="gd-mark" aria-hidden="true">{{ mb_strtoupper(mb_substr($profile->full_name, 0, 1)) }}</span>
        <span class="gd-brand-name">{{ $profile->full_name }}</span>
      </a>
      <nav class="gd-nav" aria-label="Primary">
        <ul>
          @if ($onHome)
            @foreach ($nav as $item)
              @continue($item['key'] === 'contact')
              <li><a href="#{{ $item['anchor'] }}" data-spy="{{ $item['anchor'] }}">{{ $item['label'] }}</a></li>
            @endforeach
          @else
            <li><a href="{{ $home }}">Home</a></li>
            <li><a href="{{ $home }}#projects">Projects</a></li>
            @isset($crumb)
              <li><a href="{{ $crumb[1] }}" @if ($page === 'posts') aria-current="page" @endif>{{ $crumb[0] }}</a></li>
            @endisset
          @endif
        </ul>
      </nav>
      @if (! $onHome || $nav->contains('key', 'contact'))
        <a class="gd-btn gd-btn-sm gd-cta" href="{{ $onHome ? '' : $home }}#contact" @if ($onHome) data-spy="contact" @endif>Contact</a>
      @endif
    </div>
  </header>

  @if ($onHome)
    @yield('body')
  @else
    <main id="content" class="gd-page" tabindex="-1">
      @yield('body')
    </main>
  @endif

  <footer class="gd-footer">
    <div class="gd-footer-in">
      <p>© {{ now()->year }} {{ $profile->full_name }}</p>
      @if (! empty($footerLinks) && $footerLinks->isNotEmpty())
        <ul class="gd-footer-links" aria-label="Social profiles">
          @foreach ($footerLinks as $link)
            <li><a href="{{ $link->url }}" rel="me noopener" target="_blank">{{ $link->label ?: \Illuminate\Support\Str::headline($link->platform) }}<span class="sr-only"> (opens in a new tab)</span></a></li>
          @endforeach
        </ul>
      @endif
      @unless ($onHome)<p><a href="{{ $home }}">← Back to {{ $profile->full_name }}</a></p>@endunless
    </div>
  </footer>
</body>
</html>
