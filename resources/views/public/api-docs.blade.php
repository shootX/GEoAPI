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
    <div style="max-width:760px">
        <h1 class="h1" style="font-size:36px">{{ $en ? 'API reference' : 'API ცნობარი' }}</h1>
        <p class="lead" style="margin-top:12px;font-size:16px">{{ $en ? 'The reference is generated from the OpenAPI document. Field names stay in English. The guide is Georgian and English. Download the same document or a Postman collection. Admin routes are not in this document.' : 'ცნობარი OpenAPI დოკუმენტიდან ივსება. ველების სახელები ინგლისურია. გზამკვლევი ქართული და ინგლისურია. იგივე დოკუმენტი ან Postman კოლექცია ჩამოიტვირთება. ადმინისტრაციული მისამართები ამ დოკუმენტში არ არის.' }}</p>
        <p class="sm" style="margin-top:12px;color:var(--ac)"><a href="{{ url('/api/v1/openapi.json') }}">openapi.json</a> · <a href="{{ url('/api/v1/postman.json') }}">Postman</a> · <a href="{{ url('/guide') }}">{{ $en ? 'Guide' : 'გზამკვლევი' }}</a></p>
    </div>
    <div id="redoc" style="margin-top:32px;border:1px solid var(--line);border-radius:14px;overflow:hidden"></div>
    {{-- Redoc's footer logo is hot-linked from its CDN; img-src does not allow it. --}}
    <style @nonce>#redoc a[href*="redocly.com"] img { display: none; }</style>
    <script src="{{ asset('vendor/redoc/redoc.standalone.js') }}?v=2.5.4"></script>
    <script @nonce>
        Redoc.init({{ \Illuminate\Support\Js::from(url('/api/v1/openapi.json')) }}, {
            theme: {
                colors: { primary: { main: '#4338CA' }, text: { primary: '#0F0E2A', secondary: '#4A4866' }, border: { dark: '#C9C7DA', light: '#E4E3EE' } },
                typography: { fontFamily: 'FiraGO, system-ui, sans-serif', headings: { fontFamily: 'FiraGO, system-ui, sans-serif', fontWeight: '700' }, code: { fontFamily: '"JetBrains Mono", ui-monospace, monospace' } },
                sidebar: { backgroundColor: '#F7F7FB', activeTextColor: '#4338CA' },
                rightPanel: { backgroundColor: '#0F0E2A' }
            }
        }, document.getElementById('redoc'));
    </script>
@endcomponent
