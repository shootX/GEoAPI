@php
    $en = app()->getLocale() === 'en';
    $planName = ['basic' => 'Basic', 'premium' => 'Premium'][$order->plan_code] ?? ucfirst((string) $order->plan_code);
@endphp
<x-layouts.cabinet :title="($en ? 'Test payment' : 'სატესტო გადახდა').' — GeoAPI'">
<div class="app">
    <x-ga.panel-side active="billing" />
    <div class="main">
        <header class="top">
            <button class="btn menu" id="menu" type="button" aria-expanded="false" aria-controls="side" aria-label="{{ __('Menu') }}" data-menu-toggle><x-ga.icon name="menu" :size="20" /></button>
            <a class="mlogo" href="{{ url('/') }}"><img src="{{ asset('assets/brand/geoapi-lockup-light.svg') }}" alt="geoapi.ge" height="22" width="69"></a>
            <h1>{{ $en ? 'Test payment' : 'სატესტო გადახდა' }} <span class="bd bd-wa">TEST</span></h1>
        </header>
        <div class="s-e2b01b content">
            <h1 class="mh">{{ $en ? 'Test payment' : 'სატესტო გადახდა' }} <span class="bd bd-wa">TEST</span></h1>
            <div class="al al-wa" data-code="test payment page"><x-ga.icon name="warn" :size="18" /><div>{{ $en ? 'This is a TEST payment page. The bank is not connected: no card details are collected and no money is charged.' : 'ეს არის სატესტო გადახდის გვერდი. ბანკი არ არის მიერთებული: ბარათის მონაცემები არ გროვდება და თანხა არ ჩამოიჭრება.' }}</div></div>
            <section class="panel">
                <div class="ph"><h2>{{ $en ? 'Order' : 'შეკვეთა' }}</h2><span class="bd bd-wa">provider: mock</span></div>
                <div class="set-row"><span class="mut">{{ $en ? 'Plan' : 'გეგმა' }}</span><strong>{{ $planName }} · {{ $order->interval === 'year' ? ($en ? 'year' : 'წელი') : ($en ? 'month' : 'თვე') }}</strong></div>
                <div class="set-row"><span class="mut">{{ $en ? 'Amount' : 'თანხა' }}</span><strong class="mono">{{ number_format((float) $order->gel_amount, 2) }} ₾</strong></div>
                <div class="set-row"><span class="mut">{{ $en ? 'Order' : 'შეკვეთა' }}</span><span class="mono xs">{{ $order->public_id }}</span></div>
                <div class="s-f1a90b">
                    <form method="post" action="{{ route('billing.mock.complete', $order->public_id) }}">@csrf<input type="hidden" name="result" value="success"><button class="btn pine" type="submit">{{ $en ? 'Pay (test) — success' : 'გადახდა (ტესტი) — წარმატება' }}</button></form>
                    <form method="post" action="{{ route('billing.mock.complete', $order->public_id) }}">@csrf<input type="hidden" name="result" value="fail"><button class="btn" type="submit">{{ $en ? 'Fail (test)' : 'წარუმატებელი (ტესტი)' }}</button></form>
                    <a class="btn" href="{{ route('account.billing') }}">{{ $en ? 'Back' : 'უკან' }}</a>
                </div>
            </section>
        </div>
    </div>
</div>
</x-layouts.cabinet>
