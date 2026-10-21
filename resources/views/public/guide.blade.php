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
])
    <div class="guide-layout">
    <aside class="guide-toc" aria-label="{{ __('On this page') }}">
        <strong>{{ __('On this page') }}</strong>
        @foreach ($before as $id)
            <a href="#{{ $id }}">{{ $pageById[$id]['title'] }}</a>
        @endforeach
        @foreach ($sections as $index => $section)
            <a href="#guide-{{ $index }}">{{ $section['title'] }}</a>
        @endforeach
        @foreach ($between as $id)
            <a href="#{{ $id }}">{{ $pageById[$id]['title'] }}</a>
        @endforeach
        <a href="#guide-widget">{{ __('Widget') }}</a>
        @foreach ($after as $id)
            <a href="#{{ $id }}">{{ $pageById[$id]['title'] }}</a>
        @endforeach
    </aside>
    <div>
    <div class="max-w-3xl">
        <h1 class="text-3xl font-extrabold leading-tight">{{ __('Documentation') }}</h1>
        <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ $en ? 'The secret key stays on your server. Send it as Authorization: Bearer geoapi_sk_…' : 'საიდუმლო გასაღები geoapi_sk_ მხოლოდ სერვერზე რჩება. სათაური: Authorization: Bearer.' }}</p>
        <p class="mt-2 text-sm text-muted-foreground">{{ __('The example is illustrative.') }}</p>
        <p class="mt-3 text-sm"><a href="{{ url('/api/v1/docs') }}">{{ $en ? 'OpenAPI reference' : 'OpenAPI ცნობარი' }}</a> · <a href="{{ url('/api/v1/postman.json') }}">Postman</a> · <a href="{{ url('/api/v1/openapi.json') }}">openapi.json</a></p>
    </div>
    @foreach ($before as $id)
        @include('public.partials.prose', ['page' => $pageById[$id]])
    @endforeach
    @foreach ($sections as $index => $section)
        @include('public.partials.endpoint', ['title' => $section['title'], 'text' => $section['text'], 'path' => $section['path'], 'example' => $section['example'], 'response' => $section['response'], 'anchor' => 'guide-'.$index])
    @endforeach
    @foreach ($between as $id)
        @include('public.partials.prose', ['page' => $pageById[$id]])
    @endforeach
    <section id="guide-widget" class="grid min-w-0 items-start gap-6 border-b border-border py-8 lg:grid-cols-2">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold">{{ __('Widget') }}</h2>
            <p class="mt-3 text-sm leading-6">{{ \App\Catalog\PublicSpec::widgetText($locale) }}</p>
        </div>
        <div class="codebox min-w-0">
            <div class="flex justify-end px-3 py-2">
                <button type="button" class="text-xs font-semibold" data-copy-target="guide-embed" data-copied="{{ __('Copied') }}">{{ __('Copy') }}</button>
            </div>
            <pre id="guide-embed">{{ '<script src="'.rtrim((string) config('app.url'), '/').'/widget/v1/geoapi-filter.js"></script>' }}
{{ '<geoapi-filter public-key="demo"></geoapi-filter>' }}</pre>
        </div>
    </section>
    @foreach ($after as $id)
        @include('public.partials.prose', ['page' => $pageById[$id]])
    @endforeach
    </div>
    </div>
@endcomponent
