{{--
  Shared SEO/meta tags for every template. Optional vars: $title, $description, $image, $canonical, $ogType.
  Portfolio-level defaults come from the admin's SEO fields, then the profile.
--}}
@php
    $portfolio ??= \App\Support\CurrentPortfolio::get();
    $pageTitle = $title ?? ($portfolio?->seo_title ?: trim("{$profile->full_name} — {$profile->headline}", ' —'));
    $pageDescription = $description ?? ($portfolio?->seo_description ?: ($profile->meta_description ?: \Illuminate\Support\Str::limit(strip_tags((string) $profile->intro), 155)));
    $ogImage = $image ?? ($portfolio?->og_image_url ?: $profile->avatarSrc());
    if ($ogImage && ! str_starts_with($ogImage, 'http')) {
        $ogImage = url($ogImage);
    }
    $canonicalUrl = $canonical ?? url()->current();
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">
@if (! empty($previewing))
<meta name="robots" content="noindex, nofollow">
@else
<link rel="canonical" href="{{ $canonicalUrl }}">
@endif
<meta property="og:type" content="{{ $ogType ?? 'profile' }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:url" content="{{ $canonicalUrl }}">
<meta property="og:site_name" content="{{ $profile->full_name }}">
@if ($ogImage)
<meta property="og:image" content="{{ $ogImage }}">
@endif
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">
