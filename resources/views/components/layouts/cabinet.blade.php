<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'GeoAPI' }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('assets/brand/favicon.svg') }}" type="image/svg+xml">
    <meta name="robots" content="noindex">
    <link rel="icon" href="{{ asset('assets/brand/favicon-32.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('assets/brand/apple-touch-icon-180.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/cabinet.css') }}?v=20261022gb">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="cab ga">
    {{ $slot }}
    @livewireScripts
</body>
</html>
