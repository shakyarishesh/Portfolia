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
  <title>@yield('title', config('app.name'))</title>
  <meta name="description" content="@yield('description', 'Create a personal portfolio website in minutes.')">
  <link rel="stylesheet" href="{{ asset('site/site.css') }}?v={{ filemtime(public_path('site/site.css')) }}">
  @stack('head')
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="site-header">
    <nav class="wrap site-nav" aria-label="Main">
      <a class="site-brand" href="{{ route('root') }}">{{ config('app.name') }}</a>
      <ul>
        <li><a href="{{ route('root') }}#templates">Templates</a></li>
        <li><a href="{{ route('root') }}#demo">Try the demo</a></li>
        <li><a class="btn btn-primary" href="{{ route('apply.create') }}">Create your portfolio</a></li>
      </ul>
    </nav>
  </header>

  <main id="main">
    @if (session('success'))<div class="wrap"><p class="notice" role="status">{{ session('success') }}</p></div>@endif
    @yield('content')
  </main>

  <footer class="site-footer">
    <div class="wrap">© {{ date('Y') }} {{ config('app.name') }}</div>
  </footer>
  @stack('scripts')
</body>
</html>
