@php
    $en = app()->getLocale() === 'en';
    $embed = '<script src="'.rtrim((string) config('app.url'), '/').'/widget/v1/geoapi-filter.js"></script>'."\n".'<geoapi-filter public-key="demo"></geoapi-filter>';
@endphp
@component('components.layouts.public', [
    'title' => __('Vehicle picker').' — GeoAPI',
    'description' => __('Choose a brand, model, year, generation, and modification.'),
    'active' => 'demo',
    'bleed' => true,
])
<section class="sec" style="padding-top:56px">
    <div class="wrap">
        <div style="max-width:720px">
            <div class="eyebrow">{{ $en ? 'Demo' : 'დემო' }}</div>
            <h1 class="h1" style="margin-top:10px">{{ __('Vehicle picker') }}</h1>
            <p class="lead" style="margin-top:12px">{{ $en ? 'Cascading choice: brand → model → year → generation → modification. This is how it looks on your site.' : 'კასკადური არჩევა: ბრენდი → მოდელი → წელი → თაობა → მოდიფიკაცია. ასე გამოჩნდება თქვენს საიტზე.' }}</p>
        </div>
        <div class="split demo-split" style="margin-top:36px;align-items:start">
            <div class="fc demo-card" style="padding:28px;box-shadow:var(--sh2)">
                @include('public.partials.vehicle-picker')
                <details id="vp-response" class="api-fold" style="margin-top:18px">
                    <summary class="sm" style="cursor:pointer;color:var(--ac);font-weight:500">{{ __('View the API response') }}</summary>
                    <div class="code" style="margin-top:10px"><pre id="vp-json" style="max-height:360px;overflow:auto"></pre></div>
                </details>
                <p class="xs mut" style="margin-top:14px">{{ $en ? 'Live data from the GeoAPI catalog. When a photo is missing, a placeholder is shown.' : 'ცოცხალი მონაცემები GeoAPI-ის კატალოგიდან. ფოტოს არარსებობისას ჩანს placeholder.' }}</p>
            </div>
            <div class="col" style="gap:16px">
                <div class="row" style="justify-content:space-between"><h3>{{ __('Embed code') }}</h3></div>
                <div class="code">
                    <div class="ch"><span class="on">HTML</span><button type="button" style="margin-left:auto;color:#A5A0F0" class="row" data-copy-target="vp-embed" data-copied="{{ __('Copied') }}">{{ __('Copy') }}</button></div>
                    <pre id="vp-embed" style="white-space:pre-wrap;word-break:break-all">{{ $embed }}</pre>
                </div>
                <div class="al al-in"><x-ga.icon name="info" :size="18" /><div><b>{{ $en ? 'Allowed origins' : 'დაშვებული origin-ები' }}</b>{{ $en ? 'The widget works only on the https domains listed in your cabinet. Use your own public key geoapi_pk_….' : 'ვიჯეტი მუშაობს მხოლოდ კაბინეტში მითითებულ https დომენებზე. გამოიყენეთ საკუთარი საჯარო გასაღები geoapi_pk_….' }}</div></div>
                <a class="btn b-pri b-lg" style="justify-content:center" href="{{ route('register') }}">{{ $en ? 'Get your own key' : 'მიიღე საკუთარი გასაღები' }}</a>
            </div>
        </div>
    </div>
</section>
@endcomponent
