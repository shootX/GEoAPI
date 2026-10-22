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
    $steps = $en ? [
        ['Sign up', 'Free plan: $0, 100 successful requests a month.'],
        ['Confirm your email', 'Required before you can create a secret key.'],
        ['Create a project and a key', 'geoapi_sk_ is shown only once. Save it.'],
        ['Send your first request', 'REST API or the widget, one shared counter.'],
    ] : [
        ['დარეგისტრირდით', 'Free გეგმა — $0, 100 წარმატებული მოთხოვნა თვეში.'],
        ['დაადასტურეთ ელფოსტა', 'საიდუმლო გასაღების შექმნისთვის აუცილებელია.'],
        ['შექმენით პროექტი და გასაღები', 'geoapi_sk_ ჩანს მხოლოდ ერთხელ — შეინახეთ.'],
        ['გაგზავნეთ პირველი მოთხოვნა', 'REST API ან ვიჯეტი — ერთი მრიცხველით.'],
    ];
    $faq = $en ? [
        ['Which requests count toward the limit?', 'Only successful (HTTP 200) responses, including an empty list. Errors do not use the limit.'],
        ['What happens when the limit runs out?', 'The API returns 429 with the code MONTHLY_QUOTA_EXCEEDED. Above 60 requests a minute you get RATE_LIMIT_EXCEEDED and Retry-After.'],
        ['Can I use the widget and the API together?', 'Yes. Both use the same project counter.'],
        ['Do you decode VINs?', 'No. That is not part of the current scope.'],
    ] : [
        ['რომელი მოთხოვნები ითვლება ლიმიტში?', 'მხოლოდ წარმატებული (HTTP 200) პასუხები, მათ შორის ცარიელი სია. შეცდომები ლიმიტს არ ხარჯავს.'],
        ['რა მოხდება ლიმიტის ამოწურვისას?', 'API აბრუნებს 429-ს კოდით MONTHLY_QUOTA_EXCEEDED. წუთში 60 მოთხოვნის გადაჭარბებისას — RATE_LIMIT_EXCEEDED და Retry-After.'],
        ['შემიძლია ვიჯეტის და API-ს ერთად გამოყენება?', 'დიახ. ორივე ერთ პროექტის მრიცხველს იყენებს.'],
        ['VIN დეკოდირება გაქვთ?', 'არა — ეს მიმდინარე მოცულობაში არ შედის.'],
    ];
    $stats = [
        [$brands, $en ? 'brands' : 'ბრენდი'],
        [$models, $en ? 'models' : 'მოდელი'],
        [$generations, $en ? 'generations' : 'თაობა'],
        [$modifications, $en ? 'modifications' : 'მოდიფიკაცია'],
    ];
@endphp
@component('components.layouts.public', [
    'title' => 'GeoAPI.ge — '.($en ? 'Vehicle data API' : 'ავტომობილების მონაცემების API'),
    'description' => $en
        ? 'Brands, models, generations, modifications and specifications in one REST API or a ready widget.'
        : 'ბრენდები, მოდელები, თაობები, მოდიფიკაციები და სპეციფიკაციები ერთი REST API-ით ან მზა ვიჯეტით.',
    'home' => true,
])
<section style="background:linear-gradient(180deg,#F7F7FB,#fff)">
    <div class="wrap split" style="padding-block:72px 64px">
        <div>
            <span class="bd bd-ac" style="border-radius:6px"><x-ga.icon name="db" :size="13" /> {{ $en ? 'Vehicle data API' : 'ავტომობილების მონაცემების API' }}</span>
            <h1 class="h1" style="margin-top:18px">{{ $en ? 'Brands, models, generations and specifications —' : 'ბრენდები, მოდელები, თაობები და სპეციფიკაციები —' }} <span style="color:var(--ac);white-space:nowrap">{{ $en ? 'one API' : 'ერთი API-ით' }}</span></h1>
            <p class="lead" style="margin-top:18px;max-width:540px">{{ $en ? 'Get structured data on brands, models, generations, modifications and technical specs. Use the REST API or a ready widget.' : 'მიიღეთ ბრენდების, მოდელების, თაობების, მოდიფიკაციებისა და ტექნიკური მახასიათებლების სტრუქტურირებული მონაცემები. გამოიყენეთ REST API ან მზა ვიჯეტი.' }}</p>
            <div class="row" style="margin-top:28px">
                <a class="btn b-pri b-lg" href="{{ route('register') }}">{{ $en ? 'Start free' : 'დაიწყე უფასოდ' }}</a>
                <a class="btn b-sec b-lg" href="{{ url('/guide') }}"><x-ga.icon name="doc" />{{ $en ? 'Documentation' : 'დოკუმენტაცია' }}</a>
            </div>
            <p class="sm mut" style="margin-top:14px">{{ $en ? 'Free plan · $0 · 100 successful requests a month' : 'Free გეგმა · $0 · 100 წარმატებული მოთხოვნა თვეში' }}</p>
        </div>
        <div style="position:relative;background:#fff;border:1px solid var(--line);border-radius:24px;padding:26px;box-shadow:var(--sh2);background-image:radial-gradient(#E4E3EE 1.2px,transparent 1.2px);background-size:24px 24px">
            <div style="background:#fff;border:1px solid var(--line);border-radius:14px;padding:14px 16px;box-shadow:var(--sh2);max-width:360px">
                <div class="xs mut" style="margin-bottom:8px">{{ $en ? 'Your app · request' : 'თქვენი აპლიკაცია · მოთხოვნა' }}</div>
                <div class="row" style="gap:8px;flex-wrap:nowrap"><span class="bd mono" style="background:var(--ac);color:#fff;border-radius:5px;font-size:11px;font-weight:600;height:20px">GET</span><span class="mono" style="font-size:12.5px;min-width:0;overflow-wrap:anywhere">/api/v1/brands/toyota/models</span></div>
                <div class="mono xs mut" style="margin-top:8px">Authorization: Bearer geoapi_sk_••••</div>
            </div>
            <div style="display:flex;align-items:center;gap:14px;margin:18px 0 18px 40px">
                <svg width="2" height="56" aria-hidden="true"><line x1="1" y1="0" x2="1" y2="56" stroke="#A5A0F0" stroke-width="2" stroke-dasharray="4 4"/></svg>
                <div style="display:flex;align-items:center;gap:12px;background:#0F0E2A;color:#fff;border-radius:14px;padding:10px 16px 10px 10px;box-shadow:0 0 0 8px rgb(165 160 240/.18)">
                    <span style="width:36px;height:36px;border-radius:10px;background:var(--ac);display:grid;place-items:center"><x-ga.icon name="db" :size="18" /></span>
                    <div><div style="font-weight:600;font-size:13px">GeoAPI</div><div class="mono" style="font-size:11px;color:#A5A0F0">brand → model → generation → modification</div></div>
                </div>
            </div>
            <div style="background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:var(--sh2);margin-left:auto;max-width:430px;min-width:0;overflow:hidden">
                <div class="row" style="justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--line)"><span class="xs mut">{{ $en ? 'Response · JSON' : 'პასუხი · JSON' }}</span><span class="row" style="gap:6px"><span class="bd bd-ok"><i></i>200 OK</span><span class="mono xs mut">X-Quota-Remaining</span></span></div>
<pre class="mono viz-pre" style="font-size:11.5px;line-height:1.7;padding:12px 16px;margin:0;color:var(--ink);white-space:pre;overflow-x:auto;max-width:100%">{
  <span style="color:#4338CA">"data"</span>: [{ <span style="color:#4338CA">"id"</span>: <span style="color:#1F6B45">"10"</span>, <span style="color:#4338CA">"slug"</span>: <span style="color:#1F6B45">"camry"</span>,
             <span style="color:#4338CA">"name"</span>: <span style="color:#1F6B45">"Camry"</span>, <span style="color:#4338CA">"generations_count"</span>: <span style="color:#8A5300">1</span> }],
  <span style="color:#4338CA">"meta"</span>: { <span style="color:#4338CA">"page"</span>: <span style="color:#8A5300">1</span>, <span style="color:#4338CA">"per_page"</span>: <span style="color:#8A5300">100</span>, <span style="color:#4338CA">"total"</span>: <span style="color:#8A5300">1</span> }
}</pre>
            </div>
            <div class="xs mut" style="text-align:right;margin-top:10px">{{ $en ? 'Example from the OpenAPI spec' : 'მაგალითი OpenAPI სპეციფიკაციიდან' }}</div>
        </div>
    </div>
        <div class="wrap">
            <div class="g4 stats" style="border-top:1px solid var(--line);padding:28px 0 8px">
                @foreach ($stats as [$n, $label])
                    <div style="padding:4px 0"><div class="tabular" style="font-size:34px;font-weight:700;letter-spacing:-.02em">{{ ($n === null || $n === '' || $n === '0' || $n === 0) ? '—' : $n }}</div><div class="sm mut">{{ $label }}</div></div>
                @endforeach
            </div>
            <p class="xs mut" style="padding-bottom:12px">{{ $en ? 'Catalog counts' : 'კატალოგის მოცულობა' }} · {{ $measuredAt }} · <a class="mono" href="{{ url('/api/v1/docs') }}">/api/v1/coverage</a></p>
        </div>
</section>
<section class="sec" id="features">
    <div class="wrap">
        <div class="sec-t"><div class="eyebrow">{{ $en ? 'What you get' : 'რას იღებთ' }}</div><h2 class="h2" style="margin-top:10px">{{ $en ? 'Six levels, from brand to equipment' : 'ექვსი დონე — ბრენდიდან აღჭურვილობამდე' }}</h2><p class="lead" style="margin-top:12px">{{ $en ? 'Every level has its own endpoint, filters and pagination.' : 'ყველა დონე თავისი endpoint-ით, ფილტრებით და pagination-ით.' }}</p></div>
        <div class="g3">
            @foreach ($levels as $k => [$icon, $title, $fields, $path])
                <div class="fc">
                    <div class="row" style="justify-content:space-between;flex-wrap:nowrap"><div class="fi" style="margin:0"><x-ga.icon :name="$icon" :size="20" /></div><span class="mono xs mut">0{{ $k + 1 }}</span></div>
                    <h4 style="margin-top:14px">{{ $title }}</h4>
                    <p class="mono" style="font-size:12px;color:var(--ink2);margin-top:6px;min-height:38px">{{ $fields }}</p>
                    <div class="mono" style="font-size:11.5px;color:var(--ac);margin-top:10px;border-top:1px solid var(--line);padding-top:10px">GET {{ $path }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>
<section class="wrap">
    <div class="dark-sec split" style="align-items:start">
        <div>
            <div class="eyebrow" style="color:#A5A0F0">{{ $en ? 'One request' : 'ერთი მოთხოვნა' }}</div>
            <h2 class="h2" style="margin-top:10px">{{ $en ? 'Structured JSON, ready to use' : 'სტრუქტურირებული JSON — მზა გამოსაყენებლად' }}</h2>
            <p style="color:#B9B6D3;margin-top:14px">{{ $en ? 'Filters:' : 'ფილტრები:' }} <span class="mono" style="font-size:13px">year_from, year_to, body, fuel, drive, transmission, q, modified_after</span>. {{ $en ? '100 records per page (max 1000).' : 'გვერდზე 100 ჩანაწერი (max 1000).' }}</p>
            <div class="col sm" style="margin-top:22px;gap:10px;color:#D9D7EE">
                @foreach ($en
                    ? ['Limits in every response: X-RateLimit-* and X-Quota-*', 'One error format: {error:{code,message}}', 'Code samples: cURL, JavaScript, Node.js, Python, PHP']
                    : ['ლიმიტები ყოველ პასუხში: X-RateLimit-* და X-Quota-*', 'შეცდომები ერთ ფორმატში: {error:{code,message}}', 'კოდის მაგალითები: cURL, JavaScript, Node.js, Python, PHP'] as $line)
                    <span class="row" style="gap:10px;flex-wrap:nowrap"><x-ga.icon name="chk" style="color:#A5A0F0" />{{ $line }}</span>
                @endforeach
            </div>
        </div>
        <div class="code">
            <div class="ch"><span class="on">cURL</span><a href="{{ url('/guide') }}" style="margin-left:auto;color:#A5A0F0;font-size:12px" class="row">{{ $en ? 'All languages' : 'ყველა ენა' }} <x-ga.icon name="chev" :size="13" /></a></div>
<pre><span class="k4"># {{ $en ? 'the key goes only in the Authorization header' : 'გასაღები მხოლოდ Authorization header-ში' }}</span>
curl https://geoapi.ge<span class="k1">/api/v1/modifications/1000/specs</span> \
  -H <span class="k2">"Authorization: Bearer $GEOAPI_API_KEY"</span></pre>
            <div style="border-top:1px solid #26244F;padding:8px 18px;font-size:11.5px" class="row"><span class="bd" style="background:#123325;color:#7CCFA3;height:20px">200 OK</span><span class="k4">X-RateLimit-Limit: 60 · X-Quota-Limit: 2000</span></div>
<pre style="padding-top:4px">{
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
        <div class="sec-t"><div class="eyebrow">{{ $en ? 'Integration' : 'ინტეგრაცია' }}</div><h2 class="h2" style="margin-top:10px">{{ $en ? 'Pick the way that suits you' : 'აირჩიეთ თქვენთვის მოსახერხებელი გზა' }}</h2></div>
        <div class="g3">
            <div class="fc"><div class="fi"><x-ga.icon name="glob" :size="20" /></div><h4 style="margin-top:14px">REST API</h4><p class="sm mut" style="margin-top:6px">{{ $en ? '14 GET endpoints, JSON, Bearer auth, pagination and filters.' : '14 GET endpoint, JSON, Bearer ავტორიზაცია, pagination და ფილტრები.' }}</p></div>
            <div class="fc"><div class="fi"><x-ga.icon name="mobile" :size="20" /></div><h4 style="margin-top:14px">{{ $en ? 'Embeddable widget' : 'ჩასაშენებელი ვიჯეტი' }}</h4><p class="sm mut" style="margin-top:6px">{{ $en ? 'A ready cascading picker on your site. Public key geoapi_pk_ and allowed origins.' : 'მზა კასკადური არჩევა თქვენს საიტზე. საჯარო გასაღები geoapi_pk_ და დაშვებული origin-ები.' }}</p></div>
            <div class="fc" style="opacity:.75;border-style:dashed"><div class="row" style="justify-content:space-between"><div class="fi" style="margin:0"><x-ga.icon name="box" :size="20" /></div><span class="prop">◇ {{ $en ? 'COMING' : 'მზადდება' }}</span></div><h4 style="margin-top:14px">SDK · MCP · CLI</h4><p class="sm mut" style="margin-top:6px">{{ $en ? 'JavaScript SDK, MCP Server and CLI. Guide pages are being prepared.' : 'JavaScript SDK, MCP Server და CLI — ინსტრუქციების გვერდები მზადდება.' }}</p></div>
        </div>
    </div>
</section>
<section id="how" style="padding-bottom:96px">
    <div class="wrap">
        <div class="sec-t"><div class="eyebrow">{{ $en ? 'Getting started' : 'როგორ დავიწყოთ' }}</div><h2 class="h2" style="margin-top:10px">{{ $en ? 'Four steps to the first response' : 'ოთხი ნაბიჯი პირველ პასუხამდე' }}</h2></div>
        <div class="g4">
            @foreach ($steps as $i => [$title, $text])
                <div class="fc" style="background:var(--bg);border:0"><div class="mono" style="color:var(--ac);font-weight:600">0{{ $i + 1 }}</div><h4 style="margin-top:8px">{{ $title }}</h4><p class="sm mut" style="margin-top:4px">{{ $text }}</p></div>
            @endforeach
        </div>
    </div>
</section>
<section id="faq" style="padding-bottom:96px">
    <div class="wrap split" style="align-items:start">
        <div><div class="eyebrow">{{ $en ? 'Questions' : 'კითხვები' }}</div><h2 class="h2" style="margin-top:10px">{{ $en ? 'Frequently asked questions' : 'ხშირად დასმული კითხვები' }}</h2><p class="mut" style="margin-top:12px">{{ $en ? 'Another question? Write to us:' : 'სხვა კითხვა? მოგვწერეთ:' }} <a href="mailto:hello@geoapi.ge" style="color:var(--ac);font-weight:500">hello@geoapi.ge</a></p></div>
        <div>
            @foreach ($faq as $i => [$q, $a])
                <details @if ($i === 0) open @endif style="border-bottom:1px solid var(--line);padding:18px 0"><summary style="font-weight:600;display:flex;justify-content:space-between;list-style:none;cursor:pointer;gap:12px">{{ $q }}<span style="color:var(--ac)"><x-ga.icon name="down" /></span></summary><p class="mut" style="margin-top:8px">{{ $a }}</p></details>
            @endforeach
        </div>
    </div>
</section>
<section class="wrap" style="padding-bottom:96px">
    <div style="background:var(--soft);border-radius:24px;padding:48px;display:flex;align-items:center;gap:24px;flex-wrap:wrap" class="cta-band">
        <div style="flex:1;min-width:260px"><h2 class="h2">{{ $en ? 'Start free' : 'დაიწყეთ უფასოდ' }}</h2><p class="mut" style="margin-top:8px">{{ $en ? '100 successful requests a month. Change the plan any time.' : '100 წარმატებული მოთხოვნა თვეში. გეგმის შეცვლა ნებისმიერ დროს.' }}</p></div>
        <a class="btn b-pri b-lg" href="{{ route('register') }}">{{ $en ? 'Create an account' : 'ანგარიშის შექმნა' }}</a>
        <a class="btn b-sec b-lg" href="{{ route('pricing') }}">{{ $en ? 'Pricing' : 'ტარიფები' }}</a>
    </div>
</section>
@endcomponent
