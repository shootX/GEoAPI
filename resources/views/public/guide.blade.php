@php
    $en = app()->getLocale() === 'en';
    $sections = $en ? [
        ['Brands', 'Returns the brand list. page defaults to 1. per_page defaults to 50 and cannot exceed 100.', '/api/v1/brands', "{\n  \"data\": [\n    { \"id\": \"1\", \"slug\": \"toyota\", \"name\": \"Toyota\" }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 50, \"total\": 1 }\n}"],
        ['Models', 'Models for one brand. The slug comes from the brands response.', '/api/v1/brands/{slug}/models', "{\n  \"data\": [\n    { \"id\": \"10\", \"slug\": \"camry\", \"name\": \"Camry\", \"generations_count\": 1 }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 50, \"total\": 1 }\n}"],
        ['Generations', 'Generations for one model id.', '/api/v1/models/{id}/generations', "{\n  \"data\": [\n    { \"id\": \"100\", \"name\": \"XV70\", \"year_start\": 2017, \"year_end\": 2024 }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 50, \"total\": 1 }\n}"],
        ['Modifications', 'Modifications for one generation id.', '/api/v1/generations/{id}/modifications', "{\n  \"data\": [\n    { \"id\": \"1000\", \"name\": \"2.5\", \"year_start\": 2018 }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 50, \"total\": 1 }\n}"],
        ['Modification', 'One modification.', '/api/v1/modifications/{id}', "{\n  \"data\": {\n    \"id\": \"1000\",\n    \"name\": \"2.5\"\n  }\n}"],
        ['Coverage', 'Catalog counts.', '/api/v1/coverage', "{\n  \"data\": {\n    \"brands_count\": 1,\n    \"models_count\": 1,\n    \"generations_count\": 1\n  }\n}"],
    ] : [
        ['ბრენდები', 'ბრენდების სია. page ნაგულისხმევად 1-ია. per_page ნაგულისხმევად 50-ია და 100-ს არ აჭარბებს.', '/api/v1/brands', "{\n  \"data\": [\n    { \"id\": \"1\", \"slug\": \"toyota\", \"name\": \"Toyota\" }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 50, \"total\": 1 }\n}"],
        ['მოდელები', 'ერთი ბრენდის მოდელები. slug ბრენდების პასუხიდან მოდის.', '/api/v1/brands/{slug}/models', "{\n  \"data\": [\n    { \"id\": \"10\", \"slug\": \"camry\", \"name\": \"Camry\", \"generations_count\": 1 }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 50, \"total\": 1 }\n}"],
        ['თაობები', 'ერთი მოდელის თაობები. id მოდელის პასუხიდან მოდის.', '/api/v1/models/{id}/generations', "{\n  \"data\": [\n    { \"id\": \"100\", \"name\": \"XV70\", \"year_start\": 2017, \"year_end\": 2024 }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 50, \"total\": 1 }\n}"],
        ['მოდიფიკაციები', 'ერთი თაობის მოდიფიკაციები. id თაობის პასუხიდან მოდის.', '/api/v1/generations/{id}/modifications', "{\n  \"data\": [\n    { \"id\": \"1000\", \"name\": \"2.5\", \"year_start\": 2018 }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 50, \"total\": 1 }\n}"],
        ['მოდიფიკაცია', 'ერთი მოდიფიკაცია.', '/api/v1/modifications/{id}', "{\n  \"data\": {\n    \"id\": \"1000\",\n    \"name\": \"2.5\"\n  }\n}"],
        ['დაფარვა', 'კატალოგის რაოდენობები.', '/api/v1/coverage', "{\n  \"data\": {\n    \"brands_count\": 1,\n    \"models_count\": 1,\n    \"generations_count\": 1\n  }\n}"],
    ];
@endphp
@component('components.layouts.public', [
    'title' => __('Documentation').' — CarAPI',
    'description' => $en
        ? 'CarAPI authorization, endpoints, widget, and error codes.'
        : 'CarAPI-ის ავტორიზაცია, მისამართები, ვიჯეტი და შეცდომის კოდები.',
])
    <div class="max-w-3xl">
        <h1 class="text-3xl font-extrabold leading-tight">{{ __('Documentation') }}</h1>
        <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ $en ? 'The secret key stays on your server. Send it as Authorization: Bearer carapi_sk_…' : 'საიდუმლო გასაღები carapi_sk_ მხოლოდ სერვერზე რჩება. სათაური: Authorization: Bearer.' }}</p>
        <p class="mt-2 text-sm text-muted-foreground">{{ __('The example is illustrative.') }}</p>
    </div>
    @foreach ($sections as [$title, $text, $path, $response])
        @include('public.partials.endpoint', ['title' => $title, 'text' => $text, 'path' => $path, 'response' => $response])
    @endforeach
    <section class="grid min-w-0 items-start gap-6 border-b border-border py-8 lg:grid-cols-2">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold">{{ __('Widget') }}</h2>
            <p class="mt-3 text-sm leading-6">{{ $en ? 'A public carapi_pk_ key and an allowed origin. The event is carapi:change. generation_id and modification_id are null in this version.' : 'საჯარო გასაღები carapi_pk_ და დაშვებული origin. მოვლენა carapi:change. generation_id და modification_id ამ ვერსიაში null-ია.' }}</p>
        </div>
        <div class="codebox min-w-0">
            <div class="flex justify-end px-3 py-2">
                <button type="button" class="text-xs font-semibold" data-copy-target="guide-embed" data-copied="{{ __('Copied') }}">{{ __('Copy') }}</button>
            </div>
            <pre id="guide-embed">{{ '<script src="'.rtrim((string) config('app.url'), '/').'/widget/v1/carapi-filter.js"></script>' }}
{{ '<carapi-filter public-key="carapi_pk_example"></carapi-filter>' }}</pre>
        </div>
    </section>
    <section class="grid min-w-0 items-start gap-6 py-8 lg:grid-cols-2">
        <div class="min-w-0 text-sm leading-6">
            <h2 class="text-lg font-semibold">{{ $en ? 'Errors' : 'შეცდომები' }}</h2>
            <p class="mt-3">401 unauthorized — {{ $en ? 'the key is missing or no longer works.' : 'გასაღები არ არის ან ვეღარ გამოიყენება.' }}</p>
            <p class="mt-2">403 forbidden — {{ $en ? 'the project, email, or origin does not match.' : 'პროექტი, ელფოსტა ან origin არ ემთხვევა.' }}</p>
            <p class="mt-2">404 not_found — {{ $en ? 'the record was not found.' : 'ჩანაწერი ვერ მოიძებნა.' }}</p>
            <p class="mt-2">422 — {{ $en ? 'page, per_page, or id is invalid.' : 'page, per_page ან id არასწორია.' }}</p>
            <p class="mt-2">429 — RATE_LIMIT_EXCEEDED {{ $en ? 'or' : 'ან' }} MONTHLY_QUOTA_EXCEEDED.</p>
            <p class="mt-2">503 — {{ $en ? 'the service is temporarily unavailable.' : 'სერვისი დროებით მიუწვდომელია.' }}</p>
        </div>
        <div class="codebox min-w-0">
            <p class="px-4 pt-3 text-xs font-semibold text-zinc-400">{{ __('Response') }}</p>
            <pre>{
  "error": {
    "code": "unauthorized",
    "message": "…"
  }
}</pre>
        </div>
    </section>
@endcomponent
