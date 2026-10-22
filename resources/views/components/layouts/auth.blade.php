<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
@include('components.layouts.partials.head', ['robots' => $robots ?? 'noindex, follow'])
</head>
@php
    $en = app()->getLocale() === 'en';
@endphp
<body class="site ga min-h-screen antialiased">
    <div class="auth">
        <div class="auth-l">
            <div class="row" style="justify-content:space-between">
                <a href="{{ url('/') }}" aria-label="GeoAPI.ge"><img src="{{ asset('assets/brand/geoapi-lockup-light.svg') }}" alt="geoapi.ge" style="height:28px" width="88" height="28"></a>
                <span class="seg" style="padding:3px">
                    <a href="{{ route('locale.switch', 'ka') }}" class="{{ $en ? '' : 'on' }}">KA</a>
                    <a href="{{ route('locale.switch', 'en') }}" class="{{ $en ? 'on' : '' }}">EN</a>
                </span>
            </div>
            <div class="auth-f" style="padding-block:40px">
                {{ $slot }}
            </div>
            <div class="xs mut row" style="justify-content:space-between">
                <span>© {{ date('Y') }} GeoAPI.ge</span>
                <span><a href="{{ url('/terms') }}">{{ $en ? 'Terms' : 'წესები' }}</a> · <a href="{{ url('/privacy') }}">{{ $en ? 'Privacy' : 'კონფიდენციალურობა' }}</a></span>
            </div>
        </div>
        <div class="auth-r">
            <div style="position:absolute;inset:0;background:radial-gradient(600px 400px at 100% 0,#2A2670,transparent)"></div>
            <div style="position:relative">
                <img src="{{ asset('assets/brand/geoapi-lockup-dark.svg') }}" alt="" style="height:34px">
                <h2 class="h2" style="margin-top:40px;color:#fff">{{ $en ? 'Brands, models, generations and specifications —' : 'ბრენდები, მოდელები, თაობები და სპეციფიკაციები —' }} <span style="color:#A5A0F0">{{ $en ? 'one API' : 'ერთი API-ით' }}</span></h2>
                <div class="col sm" style="gap:12px;margin-top:28px;color:#D9D7EE">
                    @foreach ($en
                        ? ['Free plan: 100 successful requests a month', 'REST API and an embeddable widget', '14 endpoints · OpenAPI and Postman']
                        : ['Free გეგმა — 100 წარმატებული მოთხოვნა თვეში', 'REST API და ჩასაშენებელი ვიჯეტი', '14 endpoint · OpenAPI და Postman'] as $line)
                        <span class="row" style="gap:10px;flex-wrap:nowrap"><x-ga.icon name="chk" style="color:#A5A0F0" />{{ $line }}</span>
                    @endforeach
                </div>
                <div class="code" style="margin-top:36px;background:#17163A;border:1px solid #26244F"><pre style="font-size:12px">GET <span class="k1">/api/v1/coverage</span>
{ <span class="k1">"brands_count"</span>: <span class="k3">…</span>, <span class="k1">"models_count"</span>: <span class="k3">…</span> }</pre></div>
            </div>
        </div>
    </div>
</body>
</html>
