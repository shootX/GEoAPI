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
<div class="s-ce92a4 wrap docs-l">
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
    <main class="s-3922d3">
        <div class="sm mut">{{ $en ? 'Documentation / REST API v1' : 'დოკუმენტაცია / REST API v1' }}</div>
        <h1 class="s-fe74f3">{{ __('Documentation') }}</h1>
        <p class="s-9ca068 lead">{{ $en ? 'Every endpoint with a request in cURL, JavaScript, Node.js, Python and PHP, and an example response.' : 'ყველა endpoint — მოთხოვნა cURL, JavaScript, Node.js, Python და PHP-ზე და პასუხის მაგალითი.' }}</p>
        <div class="s-329c66 al al-in"><x-ga.icon name="lock" :size="18" /><div><b>{{ $en ? 'Authorization' : 'ავტორიზაცია' }}</b>{{ $en ? 'The secret key stays on your server. Send it as Authorization: Bearer geoapi_sk_…' : 'საიდუმლო გასაღები geoapi_sk_ მხოლოდ სერვერზე რჩება. სათაური: Authorization: Bearer.' }} {{ __('The example is illustrative.') }}</div></div>
        <div class="s-8ca97c row sm"><a class="s-f0abc1 row" href="{{ url('/api/v1/docs') }}"><x-ga.icon name="ext" :size="14" />{{ $en ? 'OpenAPI reference' : 'OpenAPI ცნობარი' }}</a><a class="s-f0abc1 row" href="{{ url('/api/v1/openapi.json') }}"><x-ga.icon name="ext" :size="14" />openapi.json</a><a class="s-f0abc1 row" href="{{ url('/api/v1/postman.json') }}"><x-ga.icon name="ext" :size="14" />Postman</a></div>
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
            <p class="s-a79feb">{{ \App\Catalog\PublicSpec::widgetText($locale) }}</p>
        </div>
        <div class="code">
            <div class="ch"><span class="on">HTML</span><button type="button" class="s-0bc09c row" data-copy-target="guide-embed" data-copied="{{ __('Copied') }}">{{ __('Copy') }}</button></div>
            <pre id="guide-embed" class="s-3f3a6c">{{ '<script src="'.rtrim((string) config('app.url'), '/').'/widget/v1/geoapi-filter.js"></script>' }}
{{ '<geoapi-filter public-key="demo"></geoapi-filter>' }}</pre>
        </div>
    </section>
    @foreach ($after as $id)
        @include('public.partials.prose', ['page' => $pageById[$id]])
    @endforeach
    </main>
</div>
@endcomponent
