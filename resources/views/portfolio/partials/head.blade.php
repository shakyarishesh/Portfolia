@php
    $pageTitle = $title ?? "{$profile->full_name} — {$profile->headline}";
    $pageDescription = $description ?? ($profile->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $profile->intro), 155));
    $ogImage = $image ?? $profile->avatarSrc();
    if ($ogImage && ! str_starts_with($ogImage, 'http')) {
        $ogImage = url($ogImage);
    }
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">
<link rel="canonical" href="{{ $canonical ?? url()->current() }}">
<meta property="og:type" content="{{ $ogType ?? 'profile' }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:url" content="{{ $canonical ?? url()->current() }}">
<meta property="og:site_name" content="{{ $profile->full_name }}">
@if ($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
@endif
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">
<meta name="theme-color" content="#07080d">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/style.css') }}?v={{ filemtime(public_path('assets/style.css')) }}">
<script>try { const t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; const a = localStorage.getItem('accent'); if (a) document.documentElement.dataset.accent = a; } catch (e) {}</script>
