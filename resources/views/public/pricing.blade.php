@if (config('billing.v2'))
    @include('public.partials.pricing-v2')
@else
@php
    $en = app()->getLocale() === 'en';
    $rate = null;
    try {
        $rate = \App\Billing\NbgRate::latest();
    } catch (\Throwable) {
        $rate = null;
    }
    $gel = function (int $cents) use ($rate): ?string {
        if ($rate === null) {
            return null;
        }

        return \App\Billing\Plans::formatTetri(\App\Billing\Plans::gelTetri($cents, $rate['rate'], $rate['quantity']));
    };
    $usd = fn (int $cents): string => '$'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, '.', ' ');
    $plans = [
        'free' => ['name' => 'Free', 'month' => 0, 'year' => 0, 'quota' => $en ? '100 successful requests / month' : '100 წარმატებული მოთხოვნა / თვე',
            'features' => $en ? ['REST API and widget', '60 requests / minute', 'All endpoints'] : ['REST API და ვიჯეტი', '60 მოთხოვნა / წუთი', 'ყველა endpoint'],
            'cta' => $en ? 'Start free' : 'დაიწყე უფასოდ', 'href' => route('register')],
        'basic' => ['name' => 'Basic', 'month' => \App\Billing\Plans::usdCents('basic', 'month'), 'year' => \App\Billing\Plans::usdCents('basic', 'year'), 'quota' => $en ? '2 000 successful requests / month' : '2 000 წარმატებული მოთხოვნა / თვე',
            'features' => $en ? ['Everything in Free', '60 requests / minute', 'PDF invoice'] : ['ყველაფერი Free-დან', '60 მოთხოვნა / წუთი', 'ინვოისი PDF'],
            'cta' => $en ? 'Pay' : 'გადახდა', 'href' => url('/account/billing'), 'hi' => true],
        'premium' => ['name' => 'Premium', 'month' => \App\Billing\Plans::usdCents('premium', 'month'), 'year' => \App\Billing\Plans::usdCents('premium', 'year'), 'quota' => $en ? 'Unlimited requests' : 'შეუზღუდავი მოთხოვნები',
            'features' => $en ? ['Everything in Basic', '60 requests / minute', 'PDF invoice'] : ['ყველაფერი Basic-დან', '60 მოთხოვნა / წუთი', 'ინვოისი PDF'],
            'cta' => $en ? 'Pay' : 'გადახდა', 'href' => url('/account/billing')],
    ];
    $perMonth = $en ? '/ month' : '/ თვე';
    $perYear = $en ? '/ year' : '/ წელი';
    $unlimited = $en ? 'Unlimited' : 'შეუზღუდავი';
    $compare = [
        [$en ? 'Successful requests a month' : 'თვიური წარმატებული მოთხოვნები', '100', '2 000', $unlimited],
        [$en ? 'Requests a minute' : 'წუთში მოთხოვნები', '60', '60', '60'],
        [$en ? 'REST API — 14 endpoints' : 'REST API — 14 endpoint', '✓', '✓', '✓'],
        [$en ? 'Widget (geoapi_pk_)' : 'ვიჯეტი (geoapi_pk_)', '✓', '✓', '✓'],
        [$en ? 'Projects and keys' : 'პროექტები და გასაღებები', '✓', '✓', '✓'],
        [$en ? 'PDF invoice' : 'ინვოისი PDF', '—', '✓', '✓'],
        [$en ? 'Price / month' : 'ფასი / თვე', '$0', $usd($plans['basic']['month']), $usd($plans['premium']['month'])],
        [$en ? 'Price / year (−12%)' : 'ფასი / წელი (−12%)', '$0', $usd($plans['basic']['year']), $usd($plans['premium']['year'])],
    ];
@endphp
<div>
<section class="sec" style="padding-top:56px;padding-bottom:56px;background:linear-gradient(180deg,#F7F7FB,#fff)" data-billing="month">
    <div class="wrap">
        <div class="sec-t">
            <div class="eyebrow">{{ $en ? 'Pricing' : 'ტარიფები' }}</div>
            <h1 class="h1" style="margin-top:10px">{{ $en ? 'Pay for what you use' : 'გადაიხადეთ იმისთვის, რასაც იყენებთ' }}</h1>
            <p class="lead" style="margin-top:12px">{{ $en ? 'Only successful (HTTP 200) responses count toward the limit.' : 'ლიმიტში ითვლება მხოლოდ წარმატებული (HTTP 200) პასუხები.' }}</p>
            <div class="seg" style="margin-top:24px" role="group" aria-label="{{ $en ? 'Billing period' : 'გადახდის პერიოდი' }}">
                <button type="button" class="seg-b on" data-period="month" aria-pressed="true">{{ $en ? 'Monthly' : 'თვიური' }}</button>
                <button type="button" class="seg-b" data-period="year" aria-pressed="false">{{ $en ? 'Yearly' : 'წლიური' }} <span class="bd bd-ok" style="height:18px;font-size:11px;margin-left:4px">−12%</span></button>
            </div>
        </div>
        <div class="g3" style="align-items:start">
            @foreach ($plans as $key => $plan)
                @php $hi = ! empty($plan['hi']); @endphp
                <div class="fc" style="padding:28px;position:relative;{{ $hi ? 'border:2px solid var(--ac);box-shadow:var(--sh2);' : '' }}">
                    @if ($hi)<span class="bd" style="position:absolute;top:-12px;left:24px;background:var(--ac);color:#fff">{{ $en ? 'Recommended' : 'რეკომენდებული' }}</span>@endif
                    <h3>{{ $plan['name'] }}</h3>
                    <div data-show="month">
                        <div style="margin-top:14px;display:flex;align-items:baseline;gap:6px"><span class="tabular" style="font-size:42px;font-weight:700;letter-spacing:-.02em">{{ $usd($plan['month']) }}</span><span class="mut">{{ $perMonth }}</span></div>
                        <div class="sm mut" style="min-height:44px">@if ($plan['month'] === 0) ≈ 0 ₾ @elseif ($gel($plan['month'])) ≈ {{ $gel($plan['month']) }} ₾ {{ $perMonth }} @else ≈ — ₾ {{ $perMonth }} @endif</div>
                    </div>
                    <div data-show="year" hidden>
                        <div style="margin-top:14px;display:flex;align-items:baseline;gap:6px"><span class="tabular" style="font-size:42px;font-weight:700;letter-spacing:-.02em">{{ $usd($plan['year']) }}</span><span class="mut">{{ $plan['year'] === 0 ? $perMonth : $perYear }}</span></div>
                        <div class="sm mut" style="min-height:44px">
                            @if ($plan['year'] === 0) ≈ 0 ₾
                            @else
                                {{ $usd(intdiv($plan['year'], 12)) }} {{ $perMonth }}@if ($gel($plan['year'])) · ≈ {{ $gel($plan['year']) }} ₾ {{ $perYear }}@endif<br><s>{{ $usd($plan['month'] * 12) }}</s> −12%
                            @endif
                        </div>
                    </div>
                    <div style="font-weight:600;margin:14px 0">{{ $plan['quota'] }}</div>
                    <a class="btn {{ $hi ? 'b-pri' : 'b-sec' }}" style="width:100%;justify-content:center" href="{{ $plan['href'] }}">{{ $plan['cta'] }}</a>
                    <div class="col sm" style="gap:10px;margin-top:20px">
                        @foreach ($plan['features'] as $f)
                            <span class="row" style="gap:10px;flex-wrap:nowrap"><x-ga.icon name="chk" style="color:#4338CA" />{{ $f }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        <p class="sm mut" style="text-align:center;margin-top:20px">
            {{ $en ? 'Prices are in USD and include VAT.' : 'ფასები მითითებულია USD-ში და მოიცავს დღგ-ს.' }}
            @if ($rate)
                {{ $en ? 'GEL is indicative, at the National Bank of Georgia rate' : 'GEL — საორიენტაციო, ეროვნული ბანკის კურსით' }} 1 USD = {{ $rate['rate'] }} ₾ ({{ $rate['rated_on'] }}).
            @else
                {{ $en ? 'GEL appears once the National Bank rate is stored.' : 'ლარი გამოჩნდება, როცა ეროვნული ბანკის კურსი შეინახება.' }}
            @endif
            {{ $en ? 'Payment: Bank of Georgia.' : 'გადახდა: Bank of Georgia.' }}
        </p>
        <p class="xs mut" style="text-align:center;margin-top:6px">{{ $en ? 'An existing project keeps 10 000 requests a month until a plan is chosen.' : 'არსებული პროექტი 10 000 მოთხოვნას ინარჩუნებს, სანამ გეგმას აირჩევს.' }}</p>
    </div>
</section>
<section class="wrap" style="padding-bottom:72px">
    <h2 class="h2" style="margin-bottom:20px">{{ $en ? 'Comparison' : 'შედარება' }}</h2>
    <div class="tw">
        <table style="font-size:14px">
            <thead><tr><th style="width:40%"></th><th class="num">Free</th><th class="num" style="color:var(--ac)">Basic</th><th class="num">Premium</th></tr></thead>
            <tbody>
                @foreach ($compare as [$a, $b, $c, $d])
                    <tr><td>{{ $a }}</td><td class="num">{{ $b }}</td><td class="num" style="background:#FAFAFE;font-weight:500">{{ $c }}</td><td class="num">{{ $d }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
<section class="wrap" style="padding-bottom:96px">
    <div class="g3">
        @foreach ($en ? [
            ['When the limit runs out', 'After the monthly limit the API returns 429 MONTHLY_QUOTA_EXCEEDED until a new month starts or you change the plan.'],
            ['Changing the plan', 'You choose and pay for a plan in the cabinet, on the billing page. The invoice is a PDF.'],
            ['One counter', 'The widget and the server API use the same project limit.'],
        ] : [
            ['ლიმიტის ამოწურვა', 'თვიური ლიმიტის შემდეგ API აბრუნებს 429 MONTHLY_QUOTA_EXCEEDED-ს, სანამ ახალი თვე არ დაიწყება ან გეგმას არ შეცვლით.'],
            ['გეგმის შეცვლა', 'გეგმის არჩევა და გადახდა ხდება კაბინეტში, ბილინგის გვერდზე. ინვოისი PDF ფორმატშია.'],
            ['ერთი მრიცხველი', 'ვიჯეტი და სერვერის API ერთსა და იმავე პროექტის ლიმიტს იყენებს.'],
        ] as [$q, $a])
            <div class="fc"><h4>{{ $q }}</h4><p class="sm mut" style="margin-top:6px">{{ $a }}</p></div>
        @endforeach
    </div>
</section>
<script>
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-period]');
        if (!button) return;
        var period = button.getAttribute('data-period');
        document.querySelectorAll('[data-period]').forEach(function (b) {
            var on = b === button;
            b.classList.toggle('on', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
        document.querySelectorAll('[data-show]').forEach(function (node) {
            node.hidden = node.getAttribute('data-show') !== period;
        });
    });
</script>
</div>
@endif
