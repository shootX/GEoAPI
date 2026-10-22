    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $seo = $seo ?? [];
        $canonical = $seo['canonical'] ?? url()->current();
        $hreflang = $seo['hreflang'] ?? ['ka' => url('/ka'), 'en' => url('/en'), 'x-default' => url('/')];
        $ogImage = $seo['image'] ?? asset(app()->getLocale() === 'en' ? 'assets/brand/og-image-en.png' : 'assets/brand/og-image-ka.png');
        $ogType = $seo['type'] ?? 'website';
        $twitterCard = $seo['card'] ?? (isset($seo['image']) ? 'summary' : 'summary_large_image');
    @endphp
    <title>{{ $title ?? 'GeoAPI' }}</title>
    @if (!empty($description))
        <meta name="description" content="{{ $description }}">
    @endif
    @if (!empty($robots))
        <meta name="robots" content="{{ $robots }}">
    @endif
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('assets/brand/favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('assets/brand/favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="icon" href="{{ asset('assets/brand/favicon-16.png') }}" type="image/png" sizes="16x16">
    <link rel="apple-touch-icon" href="{{ asset('assets/brand/apple-touch-icon-180.png') }}">
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($hreflang as $lang => $href)
        <link rel="alternate" hreflang="{{ $lang }}" href="{{ $href }}">
    @endforeach
    <meta property="og:title" content="{{ $title ?? 'GeoAPI' }}">
    @if (!empty($description))
        <meta property="og:description" content="{{ $description }}">
        <meta name="twitter:description" content="{{ $description }}">
    @endif
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:image" content="{{ $ogImage }}">
    @unless (isset($seo['image']))
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
    @endunless
    <meta name="theme-color" content="#4338CA">
    <meta name="twitter:card" content="{{ $twitterCard }}">
    <meta name="twitter:title" content="{{ $title ?? 'GeoAPI' }}">
    <x-electrik::brand-styles />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
