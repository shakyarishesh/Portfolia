{{--
  Layout for the platform's own public pages (landing, demo form, /create application).
  Sections: title, description (meta), content. Stacks: head (extra CSS), scripts.
  Base styles: public/site/site.css. Page-specific CSS goes in its own file pushed onto `head`.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', config('portfolio.platform_name'))</title>
  <meta name="description" content="@yield('description', 'Create a personal portfolio website in minutes.')">
  {{-- Theme chosen with the header switch, applied before first paint; no choice saved = follow the system. --}}
  <script>try { const t = localStorage.getItem('site-theme'); if (t === 'light' || t === 'dark') document.documentElement.dataset.theme = t; } catch (e) {}</script>
  <link rel="stylesheet" href="{{ asset('site/site.css') }}?v={{ filemtime(public_path('site/site.css')) }}">
  @stack('head')
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="site-header">
    <nav class="wrap site-nav" aria-label="Main">
      <a class="site-brand" href="{{ route('root') }}">
        {{-- Logo mark: a page with a profile block and two lines of content. --}}
        <svg width="28" height="28" viewBox="0 0 28 28" aria-hidden="true"><rect width="28" height="28" rx="7" fill="var(--accent)"/><rect x="7" y="7" width="6" height="6" rx="3" fill="var(--accent-text)"/><rect x="15" y="8.5" width="6" height="3" rx="1.5" fill="var(--accent-text)" opacity=".7"/><rect x="7" y="16" width="14" height="2.5" rx="1.25" fill="var(--accent-text)"/><rect x="7" y="20.5" width="9" height="2.5" rx="1.25" fill="var(--accent-text)" opacity=".7"/></svg>
        {{ config('portfolio.platform_name') }}
      </a>
      <ul>
        <li><a href="{{ route('root') }}#templates">Templates</a></li>
        <li><a href="{{ route('root') }}#demo">Try the demo</a></li>
        <li><a class="btn btn-primary" href="{{ route('apply.create') }}">Create your portfolio</a></li>
      </ul>
      <button type="button" class="theme-toggle" data-theme-toggle aria-pressed="false"><span class="sr-only">Dark mode</span></button>
    </nav>
  </header>

  <main id="main">
    @if (session('success'))<div class="wrap"><p class="notice" role="status">{{ session('success') }}</p></div>@endif
    @yield('content')
  </main>

  <footer class="site-footer">
    <div class="wrap">© {{ date('Y') }} {{ config('portfolio.platform_name') }}</div>
  </footer>
  <script>
    // Dark mode switch: flips the current look (system or saved) and remembers it.
    (() => {
      const btn = document.querySelector('[data-theme-toggle]'), root = document.documentElement, mq = matchMedia('(prefers-color-scheme: dark)');
      const isDark = () => (root.dataset.theme || (mq.matches ? 'dark' : 'light')) === 'dark';
      const sync = () => btn.setAttribute('aria-pressed', String(isDark()));
      btn.addEventListener('click', () => {
        root.dataset.theme = isDark() ? 'light' : 'dark';
        try { localStorage.setItem('site-theme', root.dataset.theme); } catch (e) {}
        sync();
      });
      mq.addEventListener('change', sync);
      sync();
    })();
  </script>
  @stack('scripts')
</body>
</html>
