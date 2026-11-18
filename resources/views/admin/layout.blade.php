@php
    $nav = fn (string $route, array $params = []) => request()->routeIs($route) && (! $params || request()->route('slug') === ($params[0] ?? null)) ? 'active' : '';
    $pendingProposals = \App\Models\ProposedChange::pending()->count();
    $unread = \App\Models\ContactMessage::whereNull('read_at')->whereNull('archived_at')->count();
    $currentPortfolio = \App\Support\CurrentPortfolio::get();
    $isAdmin = (bool) auth()->user()->is_admin; // false = a portfolio owner: same screens, own portfolio only
    $switchable = \App\Models\Portfolio::query()->unless($isAdmin, fn ($q) => $q->where('user_id', auth()->id()))->orderBy('name')->get(['id', 'name', 'username', 'status']);
    $pendingApplications = $switchable->where('status', 'pending')->count();
@endphp
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>@yield('title', 'Admin') · Portfolio admin</title>
  <link rel="stylesheet" href="{{ asset('admin-assets/admin.css') }}?v={{ filemtime(public_path('admin-assets/admin.css')) }}">@include('admin.partials.theme-init')
  <script src="{{ asset('admin-assets/admin.js') }}?v={{ filemtime(public_path('admin-assets/admin.js')) }}" defer></script>
</head>
<body>
<div class="shell">
  <aside class="sidebar" aria-label="Admin navigation">
    <a class="brand" href="{{ route('admin.dashboard') }}"><span>{{ $isAdmin ? 'rs' : '✎' }}</span> {{ $isAdmin ? 'Portfolio admin' : 'My portfolio' }}</a>
    @if ($currentPortfolio)
      <form class="nav-group switcher" method="post" action="{{ route('admin.portfolios.switch', $currentPortfolio) }}" data-switcher>
        @csrf
        <p><label for="portfolio-switcher">Editing portfolio</label></p>
        <select id="portfolio-switcher" aria-describedby="portfolio-switcher-url">
          @foreach ($switchable as $p)
            <option value="{{ $p->id }}" data-action="{{ route('admin.portfolios.switch', $p) }}" @selected($p->id === $currentPortfolio->id)>{{ $p->name }} ({{ $p->username }})</option>
          @endforeach
        </select>
        @if ($isAdmin)<noscript><a class="small" href="{{ route('admin.portfolios.index') }}">Switch from the portfolio list</a></noscript>@endif
        <a class="small switcher-url" id="portfolio-switcher-url" href="{{ $currentPortfolio->url() }}" target="_blank" rel="noopener">{{ $currentPortfolio->url() }} ↗</a>
        @unless ($currentPortfolio->isPublic())<span class="badge {{ $currentPortfolio->status }}">{{ \App\Models\Portfolio::STATUSES[$currentPortfolio->status] ?? $currentPortfolio->status }} · not public</span>@endunless
      </form>
    @endif
    <div class="nav-group">
      <a class="nav {{ $nav('admin.dashboard') }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
      @if ($isAdmin)
        <a class="nav {{ $nav('admin.portfolios.*') }}" href="{{ route('admin.portfolios.index') }}">Portfolios <span class="count">{{ $switchable->count() }}</span></a>
        <a class="nav {{ $nav('admin.applications.*') }}" href="{{ route('admin.applications.index') }}">Applications @if ($pendingApplications)<span class="count" title="Waiting for review">{{ $pendingApplications }}</span>@endif</a>
      @elseif ($currentPortfolio)
        <a class="nav {{ $nav('admin.portfolios.*') }}" href="{{ route('admin.portfolios.edit', $currentPortfolio) }}">Template &amp; settings</a>
      @endif
      <a class="nav {{ $nav('admin.analytics') }}" href="{{ route('admin.analytics') }}">Analytics</a>
      @if ($currentPortfolio)<a class="nav" href="{{ route('home') }}" target="_blank" rel="noopener">View site ↗</a>@endif
    </div>
    <div class="nav-group">
      <p>Profile</p>
      <a class="nav {{ $nav('admin.profile.*') }}" href="{{ route('admin.profile.edit') }}">Personal info &amp; CV</a>
      <a class="nav {{ $nav('admin.sections.*') }}" href="{{ route('admin.sections.index') }}">Sections &amp; order</a>
    </div>
    <div class="nav-group">
      <p>Content</p>
      @foreach (\App\Admin\Resources::ALL as $r)
        <a class="nav {{ request()->route('slug') === $r::SLUG ? 'active' : '' }}" href="{{ route('admin.resources.index', $r::SLUG) }}">{{ $r::LABEL }}</a>
      @endforeach
    </div>
    <div class="nav-group">
      <p>Updates</p>
      @if ($isAdmin)
        <a class="nav {{ $nav('admin.imports.*') }}" href="{{ route('admin.imports.index') }}">Import conversations</a>
        <a class="nav {{ $nav('admin.proposals.*') }}" href="{{ route('admin.proposals.index') }}">Review changes @if ($pendingProposals)<span class="count">{{ $pendingProposals }}</span>@endif</a>
      @endif
      <a class="nav {{ $nav('admin.audit.*') }}" href="{{ route('admin.audit.index') }}">Audit history</a>
      <a class="nav {{ $nav('admin.messages.*') }}" href="{{ route('admin.messages.index') }}">Messages @if ($unread)<span class="count">{{ $unread }}</span>@endif</a>
    </div>
    <div class="nav-group">
      <button type="button" class="btn sm theme-toggle" data-theme-toggle aria-pressed="false">Dark mode <span data-icon aria-hidden="true"></span></button>
    </div>
    <form method="post" action="{{ route('logout') }}" class="nav-group">
      @csrf
      <p>{{ auth()->user()->name }}</p>
      <button class="btn sm" type="submit">Log out</button>
    </form>
  </aside>

  <main class="main" id="main">
    <div class="topbar">
      <div>
        <button class="btn sm menu-toggle" type="button" data-toggle-sidebar aria-label="Open menu">☰ Menu</button>
        @hasSection('crumbs')<div class="crumbs">@yield('crumbs')</div>@endif
        <h1>@yield('title')</h1>
      </div>
      <div class="actions">@yield('actions')</div>
    </div>

    @if (session('success'))<div class="alert ok" role="status">{{ session('success') }}</div>@endif
    @if ($errors->any())
      <div class="alert bad" role="alert"><b>Please fix the following:</b><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    @yield('content')
  </main>
</div>
</body>
</html>
