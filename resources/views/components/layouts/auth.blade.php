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
            <div class="s-a562f4 row">
                <a href="{{ url('/') }}" aria-label="GeoAPI.ge"><img src="{{ asset('assets/brand/geoapi-lockup-light.svg') }}" alt="geoapi.ge" class="s-3a8bc5" width="88" height="28"></a>
                <span class="s-cda68d seg">
                    <a href="{{ route('locale.switch', 'ka') }}" class="{{ $en ? '' : 'on' }}">KA</a>
                    <a href="{{ route('locale.switch', 'en') }}" class="{{ $en ? 'on' : '' }}">EN</a>
                </span>
            </div>
            <div class="s-347bbd auth-f">
                {{ $slot }}
            </div>
            <div class="s-a562f4 xs mut row">
                <span>© {{ date('Y') }} GeoAPI.ge</span>
                <span><a href="{{ url('/terms') }}">{{ $en ? 'Terms' : 'წესები' }}</a> · <a href="{{ url('/privacy') }}">{{ $en ? 'Privacy' : 'კონფიდენციალურობა' }}</a></span>
            </div>
        </div>
        <div class="auth-r">
            <div class="s-30da31"></div>
            <div class="s-d461c9">
                <img src="{{ asset('assets/brand/geoapi-lockup-dark.svg') }}" alt="" class="s-1ebb58">
                <h2 class="s-0fcc4b h2">{{ $en ? 'Brands, models, generations and specifications —' : 'ბრენდები, მოდელები, თაობები და სპეციფიკაციები —' }} <span class="s-c52459">{{ $en ? 'one API' : 'ერთი API-ით' }}</span></h2>
                <div class="s-3f8196 col sm">
                    @foreach ($en
                        ? ['Free plan: '.(\App\Billing\V2\Flag::on() ? '1 000' : '100').' successful requests a month', 'REST API and an embeddable widget', '14 endpoints · OpenAPI and Postman']
                        : ['Free გეგმა — '.(\App\Billing\V2\Flag::on() ? '1 000' : '100').' წარმატებული მოთხოვნა თვეში', 'REST API და ჩასაშენებელი ვიჯეტი', '14 endpoint · OpenAPI და Postman'] as $line)
                        <span class="s-8d7302 row"><x-ga.icon name="chk" class="s-c52459" />{{ $line }}</span>
                    @endforeach
                </div>
                <div class="s-5af183 code"><pre class="s-6cb285">GET <span class="k1">/api/v1/coverage</span>
{ <span class="k1">"brands_count"</span>: <span class="k3">…</span>, <span class="k1">"models_count"</span>: <span class="k3">…</span> }</pre></div>
            </div>
        </div>
    </div>
</body>
</html>
