{{--
  Professional shell. Pages @extends this and set:
    $meta  — vars for the shared meta partial
    $page  — home|project|posts|post
    $navItems — [[href, label], …] shown in the sticky bar (defaults to a link home)
    $contactHref — target of the Contact CTA (null hides it)
  Setting `accent` (navy|teal|slate|burgundy) becomes a class on <html>; style.css does the rest.
  Icons: one hand-drawn stroke sprite below, used via <svg><use href="#pro-i-name"/></svg>.
--}}
@php
    $asset = fn (string $file) => asset("templates/professional/{$file}").'?v='.@filemtime(public_path("templates/professional/{$file}"));
    $navItems ??= [[route('home'), 'Overview']];
    $contactHref ??= route('home').'#contact';
    $footerLinks = $links ?? collect();
    $icon ??= fn (string $name) => '<svg class="pro-i" aria-hidden="true" focusable="false"><use href="#pro-i-'.e($name).'"/></svg>';
@endphp
<!doctype html>
<html lang="en" class="pro accent-{{ $settings['accent'] ?? 'navy' }}" data-template="professional">
<head>
  @include('portfolios.partials.meta', $meta ?? [])
  <meta name="color-scheme" content="light">
  <script>document.documentElement.classList.add('js')</script>
  <link rel="stylesheet" href="{{ $asset('style.css') }}">
  <script src="{{ $asset('script.js') }}" defer></script>
  @yield('head')
</head>
<body class="pro-body page-{{ $page ?? 'home' }}">
  @include('portfolios.partials.preview-banner')
  <a class="pro-skip" href="#content">Skip to main content</a>

  <svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
    <defs>
      <symbol id="pro-i-mail" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3.5 7 8.5 6 8.5-6"/></symbol>
      <symbol id="pro-i-phone" viewBox="0 0 24 24"><rect x="7" y="2.5" width="10" height="19" rx="2"/><path d="M11 18h2"/></symbol>
      <symbol id="pro-i-pin" viewBox="0 0 24 24"><path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/></symbol>
      <symbol id="pro-i-download" viewBox="0 0 24 24"><path d="M12 4v11m-5-5 5 5 5-5M5 20h14"/></symbol>
      <symbol id="pro-i-arrow" viewBox="0 0 24 24"><path d="M5 12h14m-6-6 6 6-6 6"/></symbol>
      <symbol id="pro-i-external" viewBox="0 0 24 24"><path d="M14 4h6v6m0-6-9 9m7 1v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/></symbol>
      <symbol id="pro-i-code" viewBox="0 0 24 24"><path d="m8 7-5 5 5 5m8-10 5 5-5 5M13.5 5l-3 14"/></symbol>
      <symbol id="pro-i-briefcase" viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 13h18"/></symbol>
      <symbol id="pro-i-cap" viewBox="0 0 24 24"><path d="m2 9 10-5 10 5-10 5z"/><path d="M6 11v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5M22 9v5"/></symbol>
      <symbol id="pro-i-award" viewBox="0 0 24 24"><circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/></symbol>
      <symbol id="pro-i-calendar" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4m8-4v4"/></symbol>
      <symbol id="pro-i-check" viewBox="0 0 24 24"><path d="m5 12.5 4.5 4.5L19 7.5"/></symbol>
      <symbol id="pro-i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16"/></symbol>
      <symbol id="pro-i-close" viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></symbol>
      <symbol id="pro-i-link" viewBox="0 0 24 24"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/></symbol>
      <symbol id="pro-i-quote" viewBox="0 0 24 24"><path d="M10 7H6.5A2.5 2.5 0 0 0 4 9.5V17h6v-6H4m16-4h-3.5A2.5 2.5 0 0 0 14 9.5V17h6v-6h-6"/></symbol>
      <symbol id="pro-i-doc" viewBox="0 0 24 24"><path d="M6 3h8l5 5v13H6z"/><path d="M14 3v5h5M9.5 13h6m-6 4h6"/></symbol>
      <symbol id="pro-i-star" viewBox="0 0 24 24"><path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2-5.5-2.9-5.5 2.9 1-6.2L3 9.6l6.2-.9z"/></symbol>
      <symbol id="pro-i-layers" viewBox="0 0 24 24"><path d="m12 3 9 5-9 5-9-5z"/><path d="m3 13 9 5 9-5"/></symbol>
      <symbol id="pro-i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="6.5"/><path d="m20 20-4.4-4.4"/></symbol>
      <symbol id="pro-i-up" viewBox="0 0 24 24"><path d="M12 20V5m-6 6 6-6 6 6"/></symbol>
    </defs>
  </svg>

  <header class="pro-bar">
    <div class="pro-wrap pro-bar-inner">
      <a class="pro-brand" href="{{ route('home') }}">
        <span class="pro-monogram" aria-hidden="true">{{ collect(preg_split('/\s+/', trim($profile->full_name)))->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}</span>
        <span class="pro-brand-name">{{ $profile->full_name }}</span>
      </a>
      <button class="pro-burger" type="button" aria-expanded="false" aria-controls="pro-menu">
        {!! $icon('menu') !!}<span class="pro-burger-label">Menu</span>
      </button>
      <nav id="pro-menu" class="pro-menu" aria-label="Primary">
        <ul>
          @foreach ($navItems as [$href, $label])
            <li><a href="{{ $href }}">{{ $label }}</a></li>
          @endforeach
          @if ($contactHref)
            <li class="pro-menu-cta"><a class="pro-btn pro-btn-primary pro-btn-sm" href="{{ $contactHref }}">Contact</a></li>
          @endif
        </ul>
      </nav>
    </div>
  </header>

  <main id="content" class="pro-main" tabindex="-1">
    @yield('body')
  </main>

  <footer class="pro-foot">
    <div class="pro-wrap pro-foot-inner">
      <p>© {{ now()->year }} {{ $profile->full_name }}@if ($profile->headline) · {{ $profile->headline }}@endif</p>
      @if ($footerLinks->isNotEmpty() || $profile->email)
        <ul class="pro-foot-links" aria-label="Profiles">
          @foreach ($footerLinks as $link)
            <li><a href="{{ $link->url }}" target="_blank" rel="me noopener">{!! $icon(['github' => 'code', 'gitlab' => 'code', 'linkedin' => 'briefcase'][$link->platform] ?? 'link') !!}<span>{{ $link->label ?: \Illuminate\Support\Str::headline($link->platform) }}</span><span class="pro-sr"> (opens in a new tab)</span></a></li>
          @endforeach
          @if ($profile->email)<li><a href="mailto:{{ $profile->email }}">{!! $icon('mail') !!}<span>Email</span></a></li>@endif
        </ul>
      @endif
      <a class="pro-top" href="#content">{!! $icon('up') !!}Back to top</a>
    </div>
  </footer>
</body>
</html>
