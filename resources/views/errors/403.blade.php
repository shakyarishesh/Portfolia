<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Forbidden</title>
<link rel="stylesheet" href="{{ asset('admin-assets/admin.css') }}"></head>
<body><main class="auth"><div class="card stack"><h1>Not allowed</h1><p class="muted">{{ $exception->getMessage() ?: 'Your account does not have access to this page.' }}</p><a class="btn" href="{{ url('/') }}">Back to the site</a></div></main></body></html>
