<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
  <title>Sign in · Portfolio admin</title>
  <link rel="stylesheet" href="{{ asset('admin-assets/admin.css') }}">@include('admin.partials.theme-init')
  <script src="{{ asset('admin-assets/admin.js') }}" defer></script>
</head>
<body>
<main class="auth">
  <form class="card stack" method="post" action="{{ url('/admin/login') }}">
    @csrf
    <h1>Portfolio admin</h1>
    <p class="muted small">Sign in to edit your portfolio.</p>
    @if ($errors->any())<div class="alert bad" role="alert">{{ $errors->first() }}</div>@endif
    <div class="field"><label for="email">Email</label><input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"></div>
    <div class="field"><label for="password">Password</label><input type="password" id="password" name="password" required autocomplete="current-password"></div>
    <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
    <button class="btn primary" type="submit" data-loading="Signing in…">Sign in</button>
  </form>
</main>
</body>
</html>
