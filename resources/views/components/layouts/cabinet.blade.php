<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'GeoAPI' }}</title>
    <link rel="icon" href="{{ asset('assets/brand/favicon-32-lime.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('assets/cabinet.css') }}?v=20261020w">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="cab">
    {{ $slot }}
    @livewireScripts
</body>
</html>
