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
<section class="s-ed929b sec">
    <div class="wrap">
        <div class="s-b12974">
            <div class="eyebrow">{{ $en ? 'Demo' : 'დემო' }}</div>
            <h1 class="s-d2c171 h1">{{ __('Vehicle picker') }}</h1>
            <p class="s-56f435 lead">{{ $en ? 'Cascading choice: brand → model → year → generation → modification. This is how it looks on your site.' : 'კასკადური არჩევა: ბრენდი → მოდელი → წელი → თაობა → მოდიფიკაცია. ასე გამოჩნდება თქვენს საიტზე.' }}</p>
        </div>
        @php
            $c = \Carapi\Catalog\PublicCounts::read();
            $fmtN = fn ($k) => isset($c[$k]) && ($c['status'] ?? '') === 'ok' ? number_format((int) $c[$k], 0, '.', ' ') : null;
            $eqM = isset($c['equipment']) && (int) $c['equipment'] >= 1000000 ? intdiv((int) $c['equipment'], 1000000) : null;
            $totals = array_filter([
                [$fmtN('brands'), $en ? 'brands' : 'ბრენდი'],
                [$fmtN('models'), $en ? 'models' : 'მოდელი'],
                [$fmtN('generations'), $en ? 'generations' : 'თაობა'],
                [$fmtN('modifications'), $en ? 'modifications' : 'მოდიფიკაცია'],
                [$fmtN('specifications'), $en ? 'specifications' : 'სპეციფიკაცია'],
                [$fmtN('markets'), $en ? 'model markets' : 'მოდელის ბაზარი'],
                [$eqM ? ($en ? $eqM.'M+' : $eqM.' მლნ+') : null, $en ? 'trim/equipment' : 'კომპლექტაცია'],
                [$fmtN('photos'), $en ? 'photos' : 'ფოტო'],
            ], fn ($r) => $r[0] !== null && $r[0] !== '0');
        @endphp
        @if ($totals)
            <div class="s-b852a3 row demo-totals" data-demo-totals>
                @foreach ($totals as [$n, $label])
                    <div><span class="s-62ba2f tabular">{{ $n }}</span> <span class="sm mut">{{ $label }}</span></div>
                @endforeach
            </div>
        @endif
        <div class="s-67de55 split demo-split">
            <div class="s-fe4549 fc demo-card">
                @include('public.partials.vehicle-picker')
                <details id="vp-response" class="s-86de7a api-fold">
                    <summary class="s-3b802c sm">{{ __('View the API response') }}</summary>
                    <div class="s-d2c171 code"><pre id="vp-json" class="s-901059"></pre></div>
                </details>
                <p class="s-d6f2af xs mut">{{ $en ? 'Live data from the GeoAPI catalog: specifications, trim/equipment, and an approved photo when one exists.' : 'ცოცხალი მონაცემები GeoAPI-ის კატალოგიდან: სპეციფიკაცია, კომპლექტაცია და დადასტურებული ფოტო, თუ არსებობს.' }}</p>
            </div>
            <div class="s-f6ce4d col">
                <div class="s-a562f4 row"><h3>{{ __('Embed code') }}</h3></div>
                <div class="code">
                    <div class="ch"><span class="on">HTML</span><button type="button" class="s-0bc09c row" data-copy-target="vp-embed" data-copied="{{ __('Copied') }}">{{ __('Copy') }}</button></div>
                    <pre id="vp-embed" class="s-3f3a6c">{{ $embed }}</pre>
                </div>
                <div class="al al-in"><x-ga.icon name="info" :size="18" /><div><b>{{ $en ? 'Allowed origins' : 'დაშვებული origin-ები' }}</b>{{ $en ? 'The widget works only on the https domains listed in your cabinet. Use your own public key geoapi_pk_….' : 'ვიჯეტი მუშაობს მხოლოდ კაბინეტში მითითებულ https დომენებზე. გამოიყენეთ საკუთარი საჯარო გასაღები geoapi_pk_….' }}</div></div>
                <a class="s-03c4bb btn b-pri b-lg" href="{{ route('register') }}">{{ $en ? 'Get your own key' : 'მიიღე საკუთარი გასაღები' }}</a>
            </div>
        </div>
    </div>
</section>
@endcomponent
