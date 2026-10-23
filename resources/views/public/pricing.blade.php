@if (config('billing.v2'))
    @include('public.partials.pricing-v2')
@else
@php
    $en = app()->getLocale() === 'en';
    $lari = fn (int $cents): string => \App\Billing\Plans::lari($cents);
    $plans = [
        'free' => ['name' => 'Free', 'month' => 0, 'year' => 0, 'quota' => $en ? '100 successful requests / month' : '100 წარმატებული მოთხოვნა / თვე',
            'features' => $en ? ['REST API and widget', '60 requests / minute', 'All endpoints'] : ['REST API და ვიჯეტი', '60 მოთხოვნა / წუთი', 'ყველა endpoint'],
            'cta' => $en ? 'Start free' : 'დაიწყე უფასოდ', 'href' => route('register')],
        'basic' => ['name' => 'Basic', 'month' => \App\Billing\Plans::tetri('basic', 'month'), 'year' => \App\Billing\Plans::tetri('basic', 'year'), 'quota' => $en ? '2 000 successful requests / month' : '2 000 წარმატებული მოთხოვნა / თვე',
            'features' => $en ? ['Everything in Free', '60 requests / minute', 'PDF invoice'] : ['ყველაფერი Free-დან', '60 მოთხოვნა / წუთი', 'ინვოისი PDF'],
            'cta' => $en ? 'Pay' : 'გადახდა', 'href' => url('/account/billing'), 'hi' => true],
        'premium' => ['name' => 'Premium', 'month' => \App\Billing\Plans::tetri('premium', 'month'), 'year' => \App\Billing\Plans::tetri('premium', 'year'), 'quota' => $en ? 'Unlimited requests' : 'შეუზღუდავი მოთხოვნები',
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
        [$en ? 'Price / month' : 'ფასი / თვე', $lari(0), $lari($plans['basic']['month']), $lari($plans['premium']['month'])],
        [$en ? 'Price / year (−12%)' : 'ფასი / წელი (−12%)', $lari(0), $lari($plans['basic']['year']), $lari($plans['premium']['year'])],
    ];
@endphp
<div>
<section class="s-c6eb23 sec" data-billing="month">
    <div class="wrap">
        <div class="sec-t">
            <div class="eyebrow">{{ $en ? 'Pricing' : 'ტარიფები' }}</div>
            <h1 class="s-d2c171 h1">{{ $en ? 'Pay for what you use' : 'გადაიხადეთ იმისთვის, რასაც იყენებთ' }}</h1>
            <p class="s-56f435 lead">{{ $en ? 'Only successful (HTTP 200) responses count toward the limit.' : 'ლიმიტში ითვლება მხოლოდ წარმატებული (HTTP 200) პასუხები.' }}</p>
            <div class="s-49fcd8 seg" role="group" aria-label="{{ $en ? 'Billing period' : 'გადახდის პერიოდი' }}">
                <button type="button" class="seg-b on" data-period="month" aria-pressed="true">{{ $en ? 'Monthly' : 'თვიური' }}</button>
                <button type="button" class="seg-b" data-period="year" aria-pressed="false">{{ $en ? 'Yearly' : 'წლიური' }} <span class="s-6cb639 bd bd-ok">−12%</span></button>
            </div>
        </div>
        <div class="s-dfb216 g3">
            @foreach ($plans as $key => $plan)
                @php $hi = ! empty($plan['hi']); @endphp
                <div class="fc plan-card {{ $hi ? 'plan-card-hi' : '' }}">
                    @if ($hi)<span class="s-aa8940 bd">{{ $en ? 'Recommended' : 'რეკომენდებული' }}</span>@endif
                    <h2 class="h3">{{ $plan['name'] }}</h2>
                    <div data-show="month">
                        <div class="s-47a3c2"><span class="s-3d01a4 tabular">{{ $lari($plan['month']) }}</span><span class="mut">{{ $perMonth }}</span></div>
                    </div>
                    <div data-show="year" hidden>
                        <div class="s-47a3c2"><span class="s-3d01a4 tabular">{{ $lari($plan['year']) }}</span><span class="mut">{{ $plan['year'] === 0 ? $perMonth : $perYear }}</span></div>
                        @if ($plan['year'] !== 0)
                            <div class="sm mut">{{ $lari(intdiv($plan['year'], 12)) }} {{ $perMonth }}<br><s>{{ $lari($plan['month'] * 12) }}</s> −12%</div>
                        @endif
                    </div>
                    <div class="s-5008a2">{{ $plan['quota'] }}</div>
                    <a class="s-4a2700 btn {{ $hi ? 'b-pri' : 'b-sec' }}" href="{{ $plan['href'] }}">{{ $plan['cta'] }}</a>
                    <div class="s-27510f col sm">
                        @foreach ($plan['features'] as $f)
                            <span class="s-8d7302 row"><x-ga.icon name="chk" class="s-1b397b" />{{ $f }}</span>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
        <p class="s-c7b101 sm mut">
            {{ $en ? 'Prices are in GEL and include VAT.' : 'ფასები ლარშია და დღგ-ს შეიცავს.' }}
            {{ $en ? 'Payment: Bank of Georgia.' : 'გადახდა: Bank of Georgia.' }}
        </p>
        <p class="s-ed74eb xs mut">{{ $en ? 'An existing project keeps 10 000 requests a month until a plan is chosen.' : 'არსებული პროექტი 10 000 მოთხოვნას ინარჩუნებს, სანამ გეგმას აირჩევს.' }}</p>
    </div>
</section>
<section class="s-8855dd wrap">
    <h2 class="s-49f14f h2">{{ $en ? 'Comparison' : 'შედარება' }}</h2>
    {{-- Scrolls sideways on narrow screens, so keyboard users must be able to focus it. --}}
    <div class="tw" tabindex="0" role="region" aria-label="{{ $en ? 'Plan comparison' : 'გეგმების შედარება' }}">
        <table class="s-433de3">
            <thead><tr><th class="s-dc39b8"></th><th class="num">Free</th><th class="s-2bf96e num">Basic</th><th class="num">Premium</th></tr></thead>
            <tbody>
                @foreach ($compare as [$a, $b, $c, $d])
                    <tr><td>{{ $a }}</td><td class="num">{{ $b }}</td><td class="s-03bdc8 num">{{ $c }}</td><td class="num">{{ $d }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
<section class="s-3a4c3f wrap">
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
            <div class="fc"><h3 class="h4">{{ $q }}</h3><p class="s-fe7b49 sm mut">{{ $a }}</p></div>
        @endforeach
    </div>
</section>
<script @nonce>
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
