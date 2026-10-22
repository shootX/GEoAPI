@php
    $en = app()->getLocale() === 'en';
@endphp
@component('components.layouts.public', [
    'title' => ($en ? 'API reference' : 'API ცნობარი').' — GeoAPI',
    'description' => $en
        ? 'Interactive reference for the GeoAPI public catalog.'
        : 'GeoAPI-ის საჯარო კატალოგის ინტერაქტიული ცნობარი.',
    'active' => 'docs',
])
    <div class="s-71e179">
        <h1 class="s-f2d0be h1">{{ $en ? 'API reference' : 'API ცნობარი' }}</h1>
        <p class="s-9be78c lead">{{ $en ? 'The reference is generated from the OpenAPI document. Field names stay in English. The guide is Georgian and English. Download the same document or a Postman collection. Admin routes are not in this document.' : 'ცნობარი OpenAPI დოკუმენტიდან ივსება. ველების სახელები ინგლისურია. გზამკვლევი ქართული და ინგლისურია. იგივე დოკუმენტი ან Postman კოლექცია ჩამოიტვირთება. ადმინისტრაციული მისამართები ამ დოკუმენტში არ არის.' }}</p>
        <p class="s-89a2dc sm"><a href="{{ url('/api/v1/openapi.json') }}">openapi.json</a> · <a href="{{ url('/api/v1/postman.json') }}">Postman</a> · <a href="{{ url('/guide') }}">{{ $en ? 'Guide' : 'გზამკვლევი' }}</a></p>
    </div>
    <div id="redoc" class="s-fdddf4"></div>
    <style @nonce>#redoc .redoc-json .collapser { min-width: 24px; min-height: 24px; } #redoc .redoc-json li { line-height: 24px; }</style>
    <script src="{{ asset('vendor/redoc/redoc.standalone.js') }}?v=2.5.4-p1"></script>
    <script @nonce>
        Redoc.init({{ \Illuminate\Support\Js::from(url('/api/v1/openapi.json')) }}, {
            theme: {
                colors: { primary: { main: '#4338CA' }, text: { primary: '#0F0E2A', secondary: '#4A4866' }, border: { dark: '#C9C7DA', light: '#E4E3EE' } },
                typography: { fontFamily: 'FiraGO, system-ui, sans-serif', headings: { fontFamily: 'FiraGO, system-ui, sans-serif', fontWeight: '700' }, code: { fontFamily: '"JetBrains Mono", ui-monospace, monospace' } },
                sidebar: { backgroundColor: '#F7F7FB', activeTextColor: '#4338CA' },
                rightPanel: { backgroundColor: '#0F0E2A' }
            },
            nonce: {{ \Illuminate\Support\Js::from((string) \Illuminate\Support\Facades\Vite::cspNonce()) }}
        }, document.getElementById('redoc'));
        // Redoc's code samples scroll sideways but cannot take focus; keyboard users need to reach them.
        new MutationObserver(function () {
            document.querySelectorAll('#redoc pre:not([tabindex])').forEach(function (pre) { pre.tabIndex = 0; });
        }).observe(document.getElementById('redoc'), { childList: true, subtree: true });
    </script>
@endcomponent
