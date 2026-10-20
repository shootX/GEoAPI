@php
    $en = app()->getLocale() === 'en';
@endphp
@component('components.layouts.public', [
    'title' => ($en ? 'API reference' : 'API ცნობარი').' — CarAPI',
    'description' => $en
        ? 'Interactive reference for the CarAPI public catalog.'
        : 'CarAPI-ის საჯარო კატალოგის ინტერაქტიული ცნობარი.',
])
    <div class="page-intro">
        <h1 class="text-3xl font-extrabold leading-tight">{{ $en ? 'API reference' : 'API ცნობარი' }}</h1>
        <p class="mt-3 text-sm leading-6">{{ $en ? 'The reference is generated from the OpenAPI document. Download the same document or a Postman collection.' : 'ცნობარი OpenAPI დოკუმენტიდან ივსება. იგივე დოკუმენტი ან Postman კოლექცია ჩამოიტვირთება.' }}</p>
        <p class="mt-3 text-sm"><a href="{{ url('/api/v1/openapi.json') }}">openapi.json</a> · <a href="{{ url('/api/v1/postman.json') }}">Postman</a> · <a href="{{ url('/guide') }}">{{ $en ? 'Guide' : 'გზამკვლევი' }}</a></p>
    </div>
    <div id="redoc"></div>
    <script src="https://cdn.redoc.ly/redoc/latest/bundles/redoc.standalone.js"></script>
    <script>
        Redoc.init({{ \Illuminate\Support\Js::from(url('/api/v1/openapi.json')) }}, {}, document.getElementById('redoc'));
    </script>
@endcomponent
