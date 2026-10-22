{{-- Laravel's own 401/402/419/429/500/503 pages extend errors::minimal. This one keeps the
     GeoAPI look without the public layout, which reads the database and may be what failed. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title') — GeoAPI</title>
    <link rel="icon" href="{{ asset('assets/brand/favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="site ga min-h-screen antialiased">
    <main class="s-1ae07a">
        <a href="{{ url('/') }}" aria-label="GeoAPI.ge"><img src="{{ asset('assets/brand/geoapi-lockup-light.svg') }}" alt="geoapi.ge" class="s-7f574b" width="94" height="30"></a>
        <div class="s-6bae1e mono">@yield('code')</div>
        <h1 class="s-97768e h1">@yield('message')</h1>
        <p class="s-583f75"><a class="btn b-pri b-lg" href="{{ url('/') }}">{{ __('Back to the home page') }}</a></p>
    </main>
</body>
</html>
