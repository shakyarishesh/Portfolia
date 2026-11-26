@include('portfolios.partials.meta')
<meta name="theme-color" content="#07080d">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('assets/style.css') }}?v={{ filemtime(public_path('assets/style.css')) }}">
<script>try { const t = localStorage.getItem('theme'); if (t) document.documentElement.dataset.theme = t; const a = localStorage.getItem('accent'); if (a) document.documentElement.dataset.accent = a; } catch (e) {}</script>
