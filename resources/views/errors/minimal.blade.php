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
    <main style="max-width:560px;margin:40px auto;padding:0 16px;text-align:center">
        <a href="{{ url('/') }}" aria-label="GeoAPI.ge"><img src="{{ asset('assets/brand/geoapi-lockup-light.svg') }}" alt="geoapi.ge" style="height:30px;margin:0 auto" width="94" height="30"></a>
        <div class="mono" style="margin-top:40px;font-size:13px;color:var(--ac);letter-spacing:.08em">@yield('code')</div>
        <h1 class="h1" style="margin-top:10px;font-size:40px">@yield('message')</h1>
        <p style="margin-top:28px"><a class="btn b-pri b-lg" href="{{ url('/') }}">{{ __('Back to the home page') }}</a></p>
    </main>
</body>
</html>
