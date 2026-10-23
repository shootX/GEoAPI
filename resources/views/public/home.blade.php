@php
    $en = app()->getLocale() === 'en';
    $levels = [
        ['box', $en ? 'Brand' : 'ბრენდი', 'id, slug, name, synonyms[], logo_url_light/dark', '/brands'],
        ['fold', $en ? 'Model' : 'მოდელი', 'brand_id, slug, name, markets[], generations_count', '/brands/{slug}/models'],
        ['log', $en ? 'Generation' : 'თაობა', 'name, year_start, year_end, body_type, chassis_code', '/models/{id}/generations'],
        ['gear', $en ? 'Modification' : 'მოდიფიკაცია', 'engine_volume_cc, transmission, drive_type, body_type', '/generations/{id}/modifications'],
        ['doc', $en ? 'Specifications' : 'სპეციფიკაციები', 'engine{volume_cc, fuel, power_hp}, transmission, consumption', '/modifications/{id}/specs'],
        ['chk', $en ? 'Equipment' : 'აღჭურვილობა', 'standard / optional / not_available', '/modifications/{id}/equipment'],
    ];
    $v2 = \App\Billing\V2\Flag::on();
    $freeQ = ($v2 ? '1 000' : '100');
    $freeRpm = $v2 ? \App\Billing\V2\Catalog::rpm('free') : 60;
    $steps = $en ? [
        ['Sign up', 'Free plan: 0 ₾, '.$freeQ.' successful requests a month.'],
        ['Confirm your email', 'Required before you can create a secret key.'],
        ['Create a project and a key', 'geoapi_sk_ is shown only once. Save it.'],
        ['Send your first request', 'REST API or the widget, one shared counter.'],
    ] : [
        ['დარეგისტრირდით', 'Free გეგმა — 0 ₾, '.$freeQ.' წარმატებული მოთხოვნა თვეში.'],
        ['დაადასტურეთ ელფოსტა', 'საიდუმლო გასაღების შექმნისთვის აუცილებელია.'],
        ['შექმენით პროექტი და გასაღები', 'geoapi_sk_ ჩანს მხოლოდ ერთხელ — შეინახეთ.'],
        ['გაგზავნეთ პირველი მოთხოვნა', 'REST API ან ვიჯეტი — ერთი მრიცხველით.'],
    ];
    $faq = $en ? [
        ['Which requests count toward the limit?', 'Only successful (HTTP 200) responses, including an empty list. Errors do not use the limit.'],
        ['What happens when the limit runs out?', 'The API returns 429 with the code MONTHLY_QUOTA_EXCEEDED. Above '.($v2 ? 'your plan\'s per-minute limit (Free: '.$freeRpm.' requests a minute)' : $freeRpm.' requests a minute').' you get RATE_LIMIT_EXCEEDED and Retry-After.'],
        ['Can I use the widget and the API together?', $v2 ? 'Yes. Both use one counter shared by all projects in your account.' : 'Yes. Both use the same project counter.'],
        ['Do you decode VINs?', 'No. That is not part of the current scope.'],
    ] : [
        ['რომელი მოთხოვნები ითვლება ლიმიტში?', 'მხოლოდ წარმატებული (HTTP 200) პასუხები, მათ შორის ცარიელი სია. შეცდომები ლიმიტს არ ხარჯავს.'],
        ['რა მოხდება ლიმიტის ამოწურვისას?', 'API აბრუნებს 429-ს კოდით MONTHLY_QUOTA_EXCEEDED. '.($v2 ? 'გეგმის წუთის ლიმიტის გადაჭარბებისას (Free — წუთში '.$freeRpm.' მოთხოვნა)' : 'წუთში '.$freeRpm.' მოთხოვნის გადაჭარბებისას').' — RATE_LIMIT_EXCEEDED და Retry-After.'],
        ['შემიძლია ვიჯეტის და API-ს ერთად გამოყენება?', $v2 ? 'დიახ. ორივე ერთ მრიცხველს იყენებს, რომელსაც ანგარიშის ყველა პროექტი იზიარებს.' : 'დიახ. ორივე ერთ პროექტის მრიცხველს იყენებს.'],
        ['VIN დეკოდირება გაქვთ?', 'არა — ეს მიმდინარე მოცულობაში არ შედის.'],
    ];
    $dash = '—';
    $show = fn ($n) => ($n === null || $n === '' || $n === '0' || $n === 0) ? $dash : $n;
    $chain = [
        [$show($brands), $en ? 'brands' : 'ბრენდი'],
        [$show($models), $en ? 'models' : 'მოდელი'],
        [$show($generations), $en ? 'generations' : 'თაობა'],
        [$show($modifications), $en ? 'modifications' : 'მოდიფიკაცია'],
    ];
    $more = array_values(array_filter([
        [$specifications ?? null, $en ? 'specifications ·' : 'სპეციფიკაცია ·', $en ? 'engine, transmission, drive, dimensions, fuel' : 'ძრავი, გადაცემათა კოლოფი, წამყვანი, ზომები, საწვავი'],
        [$markets ?? null, $en ? 'model markets' : 'მოდელის ბაზარი', null],
        [isset($equipmentMillions) && $equipmentMillions ? ($en ? $equipmentMillions.'M+' : $equipmentMillions.' მლნ+') : null, $en ? 'trim/equipment records' : 'კომპლექტაციის ჩანაწერი', null],
        [$photos ?? null, $en ? 'photos · demo only' : 'ფოტო · მხოლოდ დემოში', $en ? 'Not in API v1' : 'API v1-ში არა'],
    ], fn ($row) => $row[0] !== null && $row[0] !== '' && $row[0] !== '0' && $row[0] !== 0));
@endphp
@component('components.layouts.public', [
    'title' => 'GeoAPI.ge — '.($en ? 'Vehicle data API' : 'ავტომობილების მონაცემების API'),
    'description' => $en
        ? 'Brands, models, generations, modifications and specifications in one REST API or a ready widget.'
        : 'ბრენდები, მოდელები, თაობები, მოდიფიკაციები და სპეციფიკაციები ერთი REST API-ით ან მზა ვიჯეტით.',
    'home' => true,
])
<section class="s-e8228a">
    <div class="s-e415e2 wrap split">
        <div>
            <span class="s-0dc840 bd bd-ac"><x-ga.icon name="db" :size="13" /> {{ $en ? 'Vehicle data API' : 'ავტომობილების მონაცემების API' }}</span>
            <h1 class="s-86de7a h1">{{ $en ? 'Brands, models, generations and specifications —' : 'ბრენდები, მოდელები, თაობები და სპეციფიკაციები —' }} <span class="s-1da5cd">{{ $en ? 'one API' : 'ერთი API-ით' }}</span></h1>
            <p class="s-64de39 lead">{{ $en ? 'Get structured data on brands, models, generations, modifications and technical specs. Use the REST API or a ready widget.' : 'მიიღეთ ბრენდების, მოდელების, თაობების, მოდიფიკაციებისა და ტექნიკური მახასიათებლების სტრუქტურირებული მონაცემები. გამოიყენეთ REST API ან მზა ვიჯეტი.' }}</p>
            <div class="s-583f75 row">
                <a class="btn b-pri b-lg" href="{{ route('register') }}">{{ $en ? 'Start free' : 'დაიწყე უფასოდ' }}</a>
                <a class="btn b-sec b-lg" href="{{ url('/guide') }}"><x-ga.icon name="doc" />{{ $en ? 'Documentation' : 'დოკუმენტაცია' }}</a>
            </div>
            <p class="s-d6f2af sm mut">{{ $en ? 'Free plan · 0 ₾ · '.$freeQ.' successful requests a month' : 'Free გეგმა · 0 ₾ · '.$freeQ.' წარმატებული მოთხოვნა თვეში' }}</p>
        </div>
        <div class="s-c6031c">
            <div class="s-bd28c7">
                <div class="s-fdf33f xs mut">{{ $en ? 'Your app · request' : 'თქვენი აპლიკაცია · მოთხოვნა' }}</div>
                <div class="s-0be020 row"><span class="s-e57620 bd mono">GET</span><span class="s-bfb357 mono">/api/v1/brands/toyota/models</span></div>
                <div class="s-8a77e5 mono xs mut">Authorization: Bearer geoapi_sk_••••</div>
            </div>
            <div class="s-a7ca67">
                <svg width="2" height="56" aria-hidden="true"><line x1="1" y1="0" x2="1" y2="56" stroke="#A5A0F0" stroke-width="2" stroke-dasharray="4 4"/></svg>
                <div class="s-16f023">
                    <span class="s-0fb990"><x-ga.icon name="db" :size="18" /></span>
                    <div><div class="s-160b06">GeoAPI</div><div class="s-af4721 mono">brand → model → generation → modification</div></div>
                </div>
            </div>
            <div class="s-e5831b">
                <div class="s-9bae86 row"><span class="xs mut">{{ $en ? 'Response · JSON' : 'პასუხი · JSON' }}</span><span class="s-f0abc1 row"><span class="bd bd-ok"><i></i>200 OK</span><span class="mono xs mut">X-Quota-Remaining</span></span></div>
<pre class="s-7291ea mono viz-pre" tabindex="0">{
  <span class="s-1b397b">"data"</span>: [{ <span class="s-1b397b">"id"</span>: <span class="s-dbe7f0">"10"</span>, <span class="s-1b397b">"slug"</span>: <span class="s-dbe7f0">"camry"</span>,
             <span class="s-1b397b">"name"</span>: <span class="s-dbe7f0">"Camry"</span>, <span class="s-1b397b">"generations_count"</span>: <span class="s-7ae4a4">1</span> }],
  <span class="s-1b397b">"meta"</span>: { <span class="s-1b397b">"page"</span>: <span class="s-7ae4a4">1</span>, <span class="s-1b397b">"per_page"</span>: <span class="s-7ae4a4">100</span>, <span class="s-1b397b">"total"</span>: <span class="s-7ae4a4">1</span> }
}</pre>
            </div>
            <div class="s-d0007a xs mut">{{ $en ? 'Example from the OpenAPI spec' : 'მაგალითი OpenAPI სპეციფიკაციიდან' }}</div>
        </div>
    </div>
        <div class="wrap">
            <div class="cat">
                <div class="cat-h">
                    <span class="eyebrow">{{ $en ? 'Catalog' : 'კატალოგი' }}</span>
                    <p class="xs mut">@if ($measuredAt){{ $measuredAt }} · @endif<a class="mono" href="{{ url('/api/v1/docs') }}#tag/Catalog/operation/getCoverage">GET /api/v1/coverage</a></p>
                </div>
                <ol class="cat-chain">
                    @foreach ($chain as [$n, $label])
                        <li><b class="tabular">{{ $n }}</b><span>{{ $label }}</span></li>
                    @endforeach
                </ol>
                @if ($more)
                    <ul class="cat-more">
                        @foreach ($more as [$n, $label, $detail])
                            <li><b class="tabular">{{ $n }}</b> <span class="k">{{ $label }}</span>@if ($detail)<span class="d">{{ $detail }}</span>@endif</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
</section>
<section class="sec" id="features">
    <div class="wrap">
        <div class="sec-t"><div class="eyebrow">{{ $en ? 'What you get' : 'რას იღებთ' }}</div><h2 class="s-d2c171 h2">{{ $en ? 'Six levels, from brand to equipment' : 'ექვსი დონე — ბრენდიდან აღჭურვილობამდე' }}</h2><p class="s-56f435 lead">{{ $en ? 'Every level has its own endpoint, filters and pagination.' : 'ყველა დონე თავისი endpoint-ით, ფილტრებით და pagination-ით.' }}</p></div>
        <div class="g3">
            @foreach ($levels as $k => [$icon, $title, $fields, $path])
                <div class="fc">
                    <div class="s-d5dbc8 row"><div class="s-116966 fi"><x-ga.icon :name="$icon" :size="20" /></div><span class="mono xs mut">0{{ $k + 1 }}</span></div>
                    <h3 class="s-d6f2af h4">{{ $title }}</h3>
                    <p class="s-7596e8 mono">{{ $fields }}</p>
                    <div class="s-289355 mono">GET {{ $path }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
<section class="wrap">
    <div class="s-dfb216 dark-sec split">
        <div>
            <div class="s-c52459 eyebrow">{{ $en ? 'One request' : 'ერთი მოთხოვნა' }}</div>
            <h2 class="s-d2c171 h2">{{ $en ? 'Structured JSON, ready to use' : 'სტრუქტურირებული JSON — მზა გამოსაყენებლად' }}</h2>
            <p class="s-3e50bd">{{ $en ? 'Filters:' : 'ფილტრები:' }} <span class="s-5e0faa mono">year_from, year_to, body, fuel, drive, transmission, q, modified_after</span>. {{ $en ? '100 records per page (max 1000).' : 'გვერდზე 100 ჩანაწერი (max 1000).' }}</p>
            <div class="s-c8db12 col sm">
                @foreach ($en
                    ? ['Limits in every response: X-RateLimit-* and X-Quota-*', 'One error format: {error:{code,message}}', 'Code samples: cURL, JavaScript, Node.js, Python, PHP']
                    : ['ლიმიტები ყოველ პასუხში: X-RateLimit-* და X-Quota-*', 'შეცდომები ერთ ფორმატში: {error:{code,message}}', 'კოდის მაგალითები: cURL, JavaScript, Node.js, Python, PHP'] as $line)
                    <span class="s-8d7302 row"><x-ga.icon name="chk" class="s-c52459" />{{ $line }}</span>
                @endforeach
            </div>
        </div>
        <div class="code">
            <div class="ch"><span class="on">cURL</span><a href="{{ url('/guide') }}" class="s-bd97b4 row">{{ $en ? 'All languages' : 'ყველა ენა' }} <x-ga.icon name="chev" :size="13" /></a></div>
<pre><span class="k4"># {{ $en ? 'the key goes only in the Authorization header' : 'გასაღები მხოლოდ Authorization header-ში' }}</span>
curl https://geoapi.ge<span class="k1">/api/v1/modifications/1000/specs</span> \
  -H <span class="k2">"Authorization: Bearer $GEOAPI_API_KEY"</span></pre>
            <div class="s-6b438b row"><span class="s-d73c66 bd">200 OK</span><span class="k4">X-RateLimit-Limit: {{ \App\Billing\V2\Flag::on() ? \App\Billing\V2\Catalog::rpm('free').' · X-Quota-Limit: '.\App\Billing\V2\Catalog::plans()['free']['quota'] : '60 · X-Quota-Limit: 2000' }}</span></div>
<pre class="s-0b1435">{
  <span class="k1">"data"</span>: {
    <span class="k1">"modification_id"</span>: <span class="k2">"1000"</span>,
    <span class="k1">"engine"</span>: { <span class="k1">"volume_cc"</span>: <span class="k3">2494</span>, <span class="k1">"fuel"</span>: <span class="k2">"petrol"</span>,
                <span class="k1">"power_hp"</span>: <span class="k3">200</span> },
    <span class="k1">"dimensions"</span>: <span class="k3">null</span>
  }
}</pre>
        </div>
    </div>
</section>
<section class="sec">
    <div class="wrap">
        <div class="sec-t"><div class="eyebrow">{{ $en ? 'Integration' : 'ინტეგრაცია' }}</div><h2 class="s-d2c171 h2">{{ $en ? 'Pick the way that suits you' : 'აირჩიეთ თქვენთვის მოსახერხებელი გზა' }}</h2></div>
        <div class="g3">
            <div class="fc"><div class="fi"><x-ga.icon name="glob" :size="20" /></div><h3 class="s-d6f2af h4">REST API</h3><p class="s-fe7b49 sm mut">{{ $en ? '14 GET endpoints, JSON, Bearer auth, pagination and filters.' : '14 GET endpoint, JSON, Bearer ავტორიზაცია, pagination და ფილტრები.' }}</p></div>
            <div class="fc"><div class="fi"><x-ga.icon name="mobile" :size="20" /></div><h3 class="s-d6f2af h4">{{ $en ? 'Embeddable widget' : 'ჩასაშენებელი ვიჯეტი' }}</h3><p class="s-fe7b49 sm mut">{{ $en ? 'A ready cascading picker on your site. Public key geoapi_pk_ and allowed origins.' : 'მზა კასკადური არჩევა თქვენს საიტზე. საჯარო გასაღები geoapi_pk_ და დაშვებული origin-ები.' }}</p></div>
            <div class="s-12296c fc"><div class="s-a562f4 row"><div class="s-116966 fi"><x-ga.icon name="box" :size="20" /></div><span class="prop">◇ {{ $en ? 'COMING' : 'მზადდება' }}</span></div><h3 class="s-d6f2af h4">SDK · MCP · CLI</h3><p class="s-fe7b49 sm mut">{{ $en ? 'JavaScript SDK, MCP Server and CLI. Guide pages are being prepared.' : 'JavaScript SDK, MCP Server და CLI — ინსტრუქციების გვერდები მზადდება.' }}</p></div>
        </div>
    </div>
</section>
<section id="how" class="s-3a4c3f">
    <div class="wrap">
        <div class="sec-t"><div class="eyebrow">{{ $en ? 'Getting started' : 'როგორ დავიწყოთ' }}</div><h2 class="s-d2c171 h2">{{ $en ? 'Four steps to the first response' : 'ოთხი ნაბიჯი პირველ პასუხამდე' }}</h2></div>
        <div class="g4">
            @foreach ($steps as $i => [$title, $text])
                <div class="s-9099a8 fc"><div class="s-5d7eb4 mono">0{{ $i + 1 }}</div><h3 class="s-8a77e5 h4">{{ $title }}</h3><p class="s-96ad60 sm mut">{{ $text }}</p></div>
            @endforeach
        </div>
    </div>
</section>
<section id="faq" class="s-3a4c3f">
    <div class="s-dfb216 wrap split">
        <div><div class="eyebrow">{{ $en ? 'Questions' : 'კითხვები' }}</div><h2 class="s-d2c171 h2">{{ $en ? 'Frequently asked questions' : 'ხშირად დასმული კითხვები' }}</h2><p class="s-56f435 mut">{{ $en ? 'Another question? Write to us:' : 'სხვა კითხვა? მოგვწერეთ:' }} <a href="mailto:hello@geoapi.ge" class="s-8d3abb">hello@geoapi.ge</a></p></div>
        <div>
            @foreach ($faq as $i => [$q, $a])
                <details @if ($i === 0) open @endif class="s-74c2a8"><summary class="s-3e6cea">{{ $q }}<span class="s-2bf96e"><x-ga.icon name="down" /></span></summary><p class="s-8a77e5 mut">{{ $a }}</p></details>
            @endforeach
        </div>
    </div>
</section>
<section class="s-3a4c3f wrap">
    <div class="s-703894 cta-band">
        <div class="s-a58e8b"><h2 class="h2">{{ $en ? 'Start free' : 'დაიწყეთ უფასოდ' }}</h2><p class="s-8a77e5 mut">{{ $en ? $freeQ.' successful requests a month. Upgrade any time; a downgrade starts when the paid term ends.' : $freeQ.' წარმატებული მოთხოვნა თვეში. გეგმის გაზრდა — ნებისმიერ დროს, შემცირება — გადახდილი ვადის ბოლოს.' }}</p></div>
        <a class="btn b-pri b-lg" href="{{ route('register') }}">{{ $en ? 'Create an account' : 'ანგარიშის შექმნა' }}</a>
        <a class="btn b-sec b-lg" href="{{ route('pricing') }}">{{ $en ? 'Pricing' : 'ტარიფები' }}</a>
    </div>
</section>
@endcomponent
