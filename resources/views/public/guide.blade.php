@php
    $en = app()->getLocale() === 'en';
    $sections = $en ? [
        ['Brands', 'Brand list. page defaults to 1. limit defaults to 100 and cannot exceed 1000. per_page is the same parameter when limit is omitted. sort is name, id, or modified. direction is asc or desc. q searches the name.', '/api/v1/brands', "{\n  \"data\": [\n    { \"id\": \"1\", \"slug\": \"toyota\", \"name\": \"Toyota\", \"modified\": \"2026-01-01T00:00:00Z\" }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 100, \"total\": 1 },\n  \"collection\": { \"count\": 1, \"total\": 1, \"pages\": 1, \"next\": null, \"prev\": null, \"first\": \"/api/v1/brands?page=1&limit=100\", \"last\": \"/api/v1/brands?page=1&limit=100\" }\n}"],
        ['Models', 'Models for one brand. Filter with body. The slug comes from the brands response.', '/api/v1/brands/{slug}/models', "{\n  \"data\": [\n    { \"id\": \"10\", \"slug\": \"camry\", \"name\": \"Camry\", \"generations_count\": 1 }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 100, \"total\": 1 }\n}"],
        ['Generations', 'Generations for one model id. Filter with year_from, year_to, and body.', '/api/v1/models/{id}/generations', "{\n  \"data\": [\n    { \"id\": \"100\", \"name\": \"XV70\", \"year_start\": 2017, \"year_end\": 2024 }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 100, \"total\": 1 }\n}"],
        ['Modifications', 'Modifications for one generation id, or every modification when the id is omitted. Filters: year_from, year_to, body, fuel, drive, transmission, brand_id, model_id, and q.', '/api/v1/modifications', "{\n  \"data\": [\n    { \"id\": \"1000\", \"name\": \"2.5\", \"year_start\": 2018, \"fuel\": \"petrol\" }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 100, \"total\": 1 }\n}"],
        ['Modification', 'One modification. modified is the UTC updated_at value.', '/api/v1/modifications/{id}', "{\n  \"data\": {\n    \"id\": \"1000\",\n    \"name\": \"2.5\",\n    \"modified\": \"2026-01-01T00:00:00Z\"\n  }\n}"],
        ['Engines', 'Volume, fuel, and power stored on a modification. Filter with fuel, brand_id, model_id, volume_from, and volume_to.', '/api/v1/engines', "{\n  \"data\": [\n    { \"id\": \"1000\", \"volume_cc\": 2494, \"fuel\": \"petrol\", \"power_hp\": 200 }\n  ]\n}"],
        ['Bodies', 'Distinct body types that are stored. q searches the name.', '/api/v1/bodies', "{\n  \"data\": [\n    { \"id\": \"sedan\", \"name\": \"sedan\", \"count\": 1 }\n  ]\n}"],
        ['Years', 'Years for one model, taken from generation and modification year_start/year_end. An empty year_end stops at the current UTC year.', '/api/v1/models/{id}/years', "{\n  \"data\": [\n    { \"id\": \"2018\", \"year\": 2018 }\n  ]\n}"],
        ['Specs', 'Stored engine, transmission, and L/100km consumption for one modification. Missing values are null. Dimensions are not stored.', '/api/v1/modifications/{id}/specs', "{\n  \"data\": {\n    \"modification_id\": \"1000\",\n    \"engine\": { \"volume_cc\": 2494, \"fuel\": \"petrol\", \"power_hp\": 200 },\n    \"dimensions\": null\n  }\n}"],
        ['Equipment', 'Equipment for one modification: standard, optional, not_available, and unknown.', '/api/v1/modifications/{id}/equipment', "{\n  \"data\": {\n    \"modification_id\": \"1000\",\n    \"standard\": [\n      { \"id\": \"1\", \"name\": \"ABS\" }\n    ]\n  }\n}"],
        ['Fuel', 'City, highway, and combined consumption stored on modification specs, in litres per 100 km.', '/api/v1/fuel', "{\n  \"data\": [\n    { \"id\": \"1000\", \"fuel\": \"petrol\", \"city_l\": 9.1, \"combined_l\": 7.4 }\n  ]\n}"],
        ['Colors', 'Verified colors stored on model photos. Unverified rows are omitted. The image itself is not included.', '/api/v1/colors', "{\n  \"data\": [\n    { \"id\": \"10\", \"name\": \"white\", \"verified\": true }\n  ]\n}"],
        ['Coverage', 'Catalog counts.', '/api/v1/coverage', "{\n  \"brands_count\": 1,\n  \"models_count\": 1,\n  \"generations_count\": 1\n}"],
    ] : [
        ['ბრენდები', 'ბრენდების სია. page ნაგულისხმევად 1-ია. limit ნაგულისხმევად 100-ია და 1000-ს არ აჭარბებს. per_page იგივე პარამეტრია, როცა limit არ არის. sort არის name, id ან modified. direction არის asc ან desc. q სახელში ეძებს.', '/api/v1/brands', "{\n  \"data\": [\n    { \"id\": \"1\", \"slug\": \"toyota\", \"name\": \"Toyota\", \"modified\": \"2026-01-01T00:00:00Z\" }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 100, \"total\": 1 },\n  \"collection\": { \"count\": 1, \"total\": 1, \"pages\": 1, \"next\": null, \"prev\": null, \"first\": \"/api/v1/brands?page=1&limit=100\", \"last\": \"/api/v1/brands?page=1&limit=100\" }\n}"],
        ['მოდელები', 'ერთი ბრენდის მოდელები. ფილტრი body. slug ბრენდების პასუხიდან მოდის.', '/api/v1/brands/{slug}/models', "{\n  \"data\": [\n    { \"id\": \"10\", \"slug\": \"camry\", \"name\": \"Camry\", \"generations_count\": 1 }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 100, \"total\": 1 }\n}"],
        ['თაობები', 'ერთი მოდელის თაობები. ფილტრები year_from, year_to და body.', '/api/v1/models/{id}/generations', "{\n  \"data\": [\n    { \"id\": \"100\", \"name\": \"XV70\", \"year_start\": 2017, \"year_end\": 2024 }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 100, \"total\": 1 }\n}"],
        ['მოდიფიკაციები', 'ერთი თაობის მოდიფიკაციები, ან ყველა მოდიფიკაცია id-ის გარეშე. ფილტრები: year_from, year_to, body, fuel, drive, transmission, brand_id, model_id და q.', '/api/v1/modifications', "{\n  \"data\": [\n    { \"id\": \"1000\", \"name\": \"2.5\", \"year_start\": 2018, \"fuel\": \"petrol\" }\n  ],\n  \"meta\": { \"page\": 1, \"per_page\": 100, \"total\": 1 }\n}"],
        ['მოდიფიკაცია', 'ერთი მოდიფიკაცია. modified არის UTC updated_at.', '/api/v1/modifications/{id}', "{\n  \"data\": {\n    \"id\": \"1000\",\n    \"name\": \"2.5\",\n    \"modified\": \"2026-01-01T00:00:00Z\"\n  }\n}"],
        ['ძრავები', 'მოცულობა, საწვავი და სიმძლავრე, რაც მოდიფიკაციაზე ინახება. ფილტრები fuel, brand_id, model_id, volume_from და volume_to.', '/api/v1/engines', "{\n  \"data\": [\n    { \"id\": \"1000\", \"volume_cc\": 2494, \"fuel\": \"petrol\", \"power_hp\": 200 }\n  ]\n}"],
        ['ძარები', 'შენახული ძარის ტიპები. q სახელში ეძებს.', '/api/v1/bodies', "{\n  \"data\": [\n    { \"id\": \"sedan\", \"name\": \"sedan\", \"count\": 1 }\n  ]\n}"],
        ['წლები', 'ერთი მოდელის წლები თაობისა და მოდიფიკაციის year_start/year_end-იდან. ცარიელი year_end მიმდინარე UTC წელზე ჩერდება.', '/api/v1/models/{id}/years', "{\n  \"data\": [\n    { \"id\": \"2018\", \"year\": 2018 }\n  ]\n}"],
        ['სპეციფიკაცია', 'შენახული ძრავა, ტრანსმისია და ხარჯი ლიტრში 100 კმ-ზე. ცარიელი მნიშვნელობა null-ია. ზომები არ ინახება.', '/api/v1/modifications/{id}/specs', "{\n  \"data\": {\n    \"modification_id\": \"1000\",\n    \"engine\": { \"volume_cc\": 2494, \"fuel\": \"petrol\", \"power_hp\": 200 },\n    \"dimensions\": null\n  }\n}"],
        ['კომპლექტაცია', 'ერთი მოდიფიკაციის კომპლექტაცია: standard, optional, not_available და unknown.', '/api/v1/modifications/{id}/equipment', "{\n  \"data\": {\n    \"modification_id\": \"1000\",\n    \"standard\": [\n      { \"id\": \"1\", \"name\": \"ABS\" }\n    ]\n  }\n}"],
        ['საწვავი', 'ქალაქის, ტრასის და კომბინირებული ხარჯი modification specs-იდან, ლიტრი 100 კმ-ზე.', '/api/v1/fuel', "{\n  \"data\": [\n    { \"id\": \"1000\", \"fuel\": \"petrol\", \"city_l\": 9.1, \"combined_l\": 7.4 }\n  ]\n}"],
        ['ფერები', 'მხოლოდ დადასტურებული ფერი მოდელის ფოტოდან. დაუდასტურებელი სტრიქონი არ ბრუნდება. სურათი პასუხში არ არის.', '/api/v1/colors', "{\n  \"data\": [\n    { \"id\": \"10\", \"name\": \"white\", \"verified\": true }\n  ]\n}"],
        ['დაფარვა', 'კატალოგის რაოდენობები.', '/api/v1/coverage', "{\n  \"brands_count\": 1,\n  \"models_count\": 1,\n  \"generations_count\": 1\n}"],
    ];
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
        @foreach ($sections as $index => [$title])
            <a href="#guide-{{ $index }}">{{ $title }}</a>
        @endforeach
        <a href="#guide-widget">{{ __('Widget') }}</a>
        <a href="#guide-limits">{{ $en ? 'Limits' : 'ლიმიტები' }}</a>
        <a href="#guide-errors">{{ $en ? 'Errors' : 'შეცდომები' }}</a>
    </aside>
    <div>
    <div class="max-w-3xl">
        <h1 class="text-3xl font-extrabold leading-tight">{{ __('Documentation') }}</h1>
        <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ $en ? 'The secret key stays on your server. Send it as Authorization: Bearer geoapi_sk_…' : 'საიდუმლო გასაღები geoapi_sk_ მხოლოდ სერვერზე რჩება. სათაური: Authorization: Bearer.' }}</p>
        <p class="mt-2 text-sm text-muted-foreground">{{ __('The example is illustrative.') }}</p>
        <p class="mt-3 text-sm"><a href="{{ url('/api/v1/docs') }}">{{ $en ? 'OpenAPI reference' : 'OpenAPI ცნობარი' }}</a> · <a href="{{ url('/api/v1/postman.json') }}">Postman</a> · <a href="{{ url('/api/v1/openapi.json') }}">openapi.json</a></p>
    </div>
    @foreach ($sections as $index => [$title, $text, $path, $response])
        @include('public.partials.endpoint', ['title' => $title, 'text' => $text, 'path' => $path, 'response' => $response, 'anchor' => 'guide-'.$index])
    @endforeach
    <section id="guide-widget" class="grid min-w-0 items-start gap-6 border-b border-border py-8 lg:grid-cols-2">
        <div class="min-w-0">
            <h2 class="text-lg font-semibold">{{ __('Widget') }}</h2>
            <p class="mt-3 text-sm leading-6">{{ $en ? 'The browser calls POST /widget/v1/{public-key}/brands and the same path for models, years, and generations. On this site the key demo works only for this origin. A project key looks like geoapi_pk_ and 32 hex characters. The geoapi:change event includes generation_id after a generation is chosen. modification_id stays null; use the server API for modifications.' : 'ბრაუზერი იძახებს POST /widget/v1/{საჯარო-გასაღები}/brands და იმავე გზას მოდელების, წლებისა და თაობებისთვის. ამ საიტზე demo გასაღები მხოლოდ ამ origin-ზე მუშაობს. პროექტის გასაღები არის geoapi_pk_ და 32 თექვსმეტობითი სიმბოლო. geoapi:change მოვლენაში generation_id ივსება თაობის არჩევის შემდეგ. modification_id რჩება null; მოდიფიკაციისთვის გამოიყენეთ სერვერული API.' }}</p>
        </div>
        <div class="codebox min-w-0">
            <div class="flex justify-end px-3 py-2">
                <button type="button" class="text-xs font-semibold" data-copy-target="guide-embed" data-copied="{{ __('Copied') }}">{{ __('Copy') }}</button>
            </div>
            <pre id="guide-embed">{{ '<script src="'.rtrim((string) config('app.url'), '/').'/widget/v1/geoapi-filter.js"></script>' }}
{{ '<geoapi-filter public-key="demo"></geoapi-filter>' }}</pre>
        </div>
    </section>
    <section id="guide-limits" class="grid min-w-0 items-start gap-6 border-b border-border py-8 lg:grid-cols-2">
        <div class="min-w-0 text-sm leading-6">
            <h2 class="text-lg font-semibold">{{ $en ? 'Limits' : 'ლიმიტები' }}</h2>
            <p class="mt-3">{{ $en ? 'Free, per project: 60 requests each UTC minute, 2 000 successful requests each UTC day, and 10 000 successful requests each UTC month. Pro is not for sale yet.' : 'Free, პროექტზე: UTC წუთში 60 მოთხოვნა, UTC დღეში 2 000 წარმატებული მოთხოვნა და UTC თვეში 10 000 წარმატებული მოთხოვნა. Pro ჯერ არ იყიდება.' }}</p>
            <p class="mt-2">{{ $en ? 'A successful response sends X-RateLimit-Limit, X-RateLimit-Remaining, and X-RateLimit-Reset for the minute window, plus X-Quota-Limit, X-Quota-Remaining, and X-Quota-Reset for the month. 429 is JSON with Retry-After. Codes: RATE_LIMIT_EXCEEDED, DAILY_QUOTA_EXCEEDED, MONTHLY_QUOTA_EXCEEDED.' : 'წარმატებული პასუხი აბრუნებს X-RateLimit-Limit, X-RateLimit-Remaining და X-RateLimit-Reset წუთის ფანჯრისთვის, პლუს X-Quota-Limit, X-Quota-Remaining და X-Quota-Reset თვისთვის. 429 არის JSON და Retry-After. კოდები: RATE_LIMIT_EXCEEDED, DAILY_QUOTA_EXCEEDED, MONTHLY_QUOTA_EXCEEDED.' }}</p>
            <p class="mt-2">modified_after {{ $en ? 'returns rows changed after a UTC timestamp, for example 2026-01-01T00:00:00Z. Each resource includes modified.' : 'აბრუნებს ჩანაწერებს UTC დროის შემდეგ, მაგალითად 2026-01-01T00:00:00Z. თითო რესურსს აქვს modified.' }}</p>
        </div>
        <div class="codebox min-w-0">
            <p class="px-4 pt-3 text-xs font-semibold text-zinc-400">429</p>
            <pre>{
  "error": {
    "code": "DAILY_QUOTA_EXCEEDED",
    "message": "…"
  }
}</pre>
        </div>
    </section>
    <section id="guide-errors" class="grid min-w-0 items-start gap-6 py-8 lg:grid-cols-2">
        <div class="min-w-0 text-sm leading-6">
            <h2 class="text-lg font-semibold">{{ $en ? 'Errors' : 'შეცდომები' }}</h2>
            <p class="mt-3">401 unauthorized — {{ $en ? 'the key is missing or no longer works.' : 'გასაღები არ არის ან ვეღარ გამოიყენება.' }}</p>
            <p class="mt-2">403 forbidden — {{ $en ? 'the project, email, or origin does not match.' : 'პროექტი, ელფოსტა ან origin არ ემთხვევა.' }}</p>
            <p class="mt-2">404 not_found — {{ $en ? 'the record was not found.' : 'ჩანაწერი ვერ მოიძებნა.' }}</p>
            <p class="mt-2">422 — {{ $en ? 'page, limit, per_page, sort, direction, a filter, or id is invalid.' : 'page, limit, per_page, sort, direction, ფილტრი ან id არასწორია.' }}</p>
            <p class="mt-2">429 — RATE_LIMIT_EXCEEDED, DAILY_QUOTA_EXCEEDED {{ $en ? 'or' : 'ან' }} MONTHLY_QUOTA_EXCEEDED. X-RateLimit-Limit, X-RateLimit-Remaining, X-RateLimit-Reset.</p>
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
    </div>
    </div>
@endcomponent
