@php
    $locale = app()->getLocale() === 'en' ? 'en' : 'ka';
    $en = $locale === 'en';
    $sections = \App\Catalog\PublicSpec::guideSections($locale);
    $pages = \App\Catalog\PublicSpec::narrative($locale);
    $before = ['guide-quickstart'];
    $between = ['guide-auth'];
    $after = ['guide-packages', 'guide-dictionary', 'guide-limits', 'guide-changelog', 'guide-recipes', 'guide-errors'];
    $pageById = [];
    foreach ($pages as $page) {
        $pageById[$page['id']] = $page;
    }
@endphp
@component('components.layouts.public', [
    'title' => __('Documentation').' — GeoAPI',
    'description' => $en
        ? 'GeoAPI authorization, endpoints, widget, and error codes.'
        : 'GeoAPI-ის ავტორიზაცია, მისამართები, ვიჯეტი და შეცდომის კოდები.',
    'active' => 'docs',
    'bleed' => true,
])
<div class="wrap docs-l" style="padding-block:40px 96px;max-width:1360px">
    <aside class="toc" aria-label="{{ __('On this page') }}">
        <div class="gr">{{ $en ? 'ON THIS PAGE' : 'გვერდზე' }}</div>
        @include('public.partials.guide-toc-links')
        <div class="gr">{{ $en ? 'REFERENCE' : 'სპეციფიკაცია' }}</div>
        <a href="{{ url('/api/v1/docs') }}">API reference</a>
        <a href="{{ url('/api/v1/openapi.json') }}">openapi.json</a>
        <a href="{{ url('/api/v1/postman.json') }}">postman.json</a>
    </aside>
    <details class="toc-m">
        <summary>{{ __('On this page') }}</summary>
        <nav class="toc" aria-label="{{ __('On this page') }}">@include('public.partials.guide-toc-links')</nav>
    </details>
    <main style="min-width:0">
        <div class="sm mut">{{ $en ? 'Documentation / REST API v1' : 'დოკუმენტაცია / REST API v1' }}</div>
        <h1 style="font-size:36px;font-weight:700;margin-top:6px;line-height:1.2">{{ __('Documentation') }}</h1>
        <p class="lead" style="margin-top:8px;max-width:720px">{{ $en ? 'Every endpoint with a request in cURL, JavaScript, Node.js, Python and PHP, and an example response.' : 'ყველა endpoint — მოთხოვნა cURL, JavaScript, Node.js, Python და PHP-ზე და პასუხის მაგალითი.' }}</p>
        <div class="al al-in" style="margin-top:18px;max-width:720px"><x-ga.icon name="lock" :size="18" /><div><b>{{ $en ? 'Authorization' : 'ავტორიზაცია' }}</b>{{ $en ? 'The secret key stays on your server. Send it as Authorization: Bearer geoapi_sk_…' : 'საიდუმლო გასაღები geoapi_sk_ მხოლოდ სერვერზე რჩება. სათაური: Authorization: Bearer.' }} {{ __('The example is illustrative.') }}</div></div>
        <div class="row sm" style="margin-top:14px;gap:16px;color:var(--ac)"><a class="row" style="gap:6px" href="{{ url('/api/v1/docs') }}"><x-ga.icon name="ext" :size="14" />{{ $en ? 'OpenAPI reference' : 'OpenAPI ცნობარი' }}</a><a class="row" style="gap:6px" href="{{ url('/api/v1/openapi.json') }}"><x-ga.icon name="ext" :size="14" />openapi.json</a><a class="row" style="gap:6px" href="{{ url('/api/v1/postman.json') }}"><x-ga.icon name="ext" :size="14" />Postman</a></div>
    @foreach ($before as $id)
        @include('public.partials.prose', ['page' => $pageById[$id]])
    @endforeach
    @foreach ($sections as $index => $section)
        @include('public.partials.endpoint', ['title' => $section['title'], 'text' => $section['text'], 'path' => $section['path'], 'example' => $section['example'], 'response' => $section['response'], 'anchor' => 'guide-'.$index])
    @endforeach
    @foreach ($between as $id)
        @include('public.partials.prose', ['page' => $pageById[$id]])
    @endforeach
    <section id="guide-widget" class="doc-sec">
        <div class="doc-text">
            <h2 class="doc-h">{{ __('Widget') }}</h2>
            <p style="margin-top:14px;color:var(--ink2)">{{ \App\Catalog\PublicSpec::widgetText($locale) }}</p>
        </div>
        <div class="code">
            <div class="ch"><span class="on">HTML</span><button type="button" class="row" style="margin-left:auto;color:#A5A0F0" data-copy-target="guide-embed" data-copied="{{ __('Copied') }}">{{ __('Copy') }}</button></div>
            <pre id="guide-embed" style="white-space:pre-wrap;word-break:break-all">{{ '<script src="'.rtrim((string) config('app.url'), '/').'/widget/v1/geoapi-filter.js"></script>' }}
{{ '<geoapi-filter public-key="demo"></geoapi-filter>' }}</pre>
        </div>
    </section>
    @foreach ($after as $id)
        @include('public.partials.prose', ['page' => $pageById[$id]])
    @endforeach
    </main>
</div>
@endcomponent
