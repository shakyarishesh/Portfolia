<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>File too large</title>
<link rel="stylesheet" href="{{ asset('admin-assets/admin.css') }}">@include('admin.partials.theme-init')</head>
<body><main class="auth"><div class="card stack"><h1>File too large</h1><p class="muted">The upload was bigger than the server accepts ({{ ini_get('post_max_size') }}). Please choose a smaller file and try again.</p><a class="btn" href="{{ url()->previous(url('/')) }}">Go back</a></div></main></body></html>
