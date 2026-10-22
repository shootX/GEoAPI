@php
    $en = app()->getLocale() === 'en';
    $lari = fn (int $cents): string => \App\Billing\Plans::lari($cents);
    $business = \App\Billing\V2\Flag::businessRpm();
    $plans = [
        'free' => [
            'name' => 'Free',
            'month' => 0,
            'year' => 0,
            'quota' => $en ? '1 000 successful requests / month' : '1 000 წარმატებული მოთხოვნა / თვე',
            'features' => $en
                ? ['1 active project', '15 requests / minute', 'REST API and widget']
                : ['1 აქტიური პროექტი', '15 მოთხოვნა / წუთი', 'REST API და ვიჯეტი'],
            'cta' => $en ? 'Start free' : 'დაიწყე უფასოდ',
            'href' => route('register'),
            'buy' => true,
        ],
        'starter' => [
            'name' => 'Starter',
            'month' => \App\Billing\V2\Catalog::tetri('starter', 'month'),
            'year' => \App\Billing\V2\Catalog::tetri('starter', 'year'),
            'quota' => $en ? '25 000 successful requests / month' : '25 000 წარმატებული მოთხოვნა / თვე',
            'features' => $en
                ? ['3 active projects', '60 requests / minute', 'PDF invoice']
                : ['3 აქტიური პროექტი', '60 მოთხოვნა / წუთი', 'ინვოისი PDF'],
            'cta' => $en ? 'Pay' : 'გადახდა',
            'href' => url('/account/billing'),
            'buy' => true,
        ],
        'growth' => [
            'name' => 'Growth',
            'month' => \App\Billing\V2\Catalog::tetri('growth', 'month'),
            'year' => \App\Billing\V2\Catalog::tetri('growth', 'year'),
            'quota' => $en ? '150 000 successful requests / month' : '150 000 წარმატებული მოთხოვნა / თვე',
            'features' => $en
                ? ['10 active projects', '120 requests / minute', 'Prepaid request packs']
                : ['10 აქტიური პროექტი', '120 მოთხოვნა / წუთი', 'წინასწარ შეძენილი პაკეტები'],
            'cta' => $en ? 'Pay' : 'გადახდა',
            'href' => url('/account/billing'),
            'buy' => true,
            'hi' => true,
        ],
    ];
    if ($business) {
        $plans['business'] = [
            'name' => 'Business',
            'month' => \App\Billing\V2\Catalog::tetri('business', 'month'),
            'year' => \App\Billing\V2\Catalog::tetri('business', 'year'),
            'quota' => $en ? '500 000 successful requests / month' : '500 000 წარმატებული მოთხოვნა / თვე',
            'features' => $en
                ? ['25 active projects', '240 requests / minute', 'PDF invoice']
                : ['25 აქტიური პროექტი', '240 მოთხოვნა / წუთი', 'ინვოისი PDF'],
            'cta' => $en ? 'Pay' : 'გადახდა',
            'href' => url('/account/billing'),
            'buy' => true,
        ];
    }
    $perMonth = $en ? '/ month' : '/ თვე';
    $perYear = $en ? '/ year' : '/ წელი';
@endphp
<div data-billing-v2="1">
<section class="sec" style="padding-top:56px;padding-bottom:56px;background:linear-gradient(180deg,#F7F7FB,#fff)" data-billing="month">
    <div class="wrap">
        <div class="sec-t">
            <div class="eyebrow">{{ $en ? 'Pricing' : 'ტარიფები' }}</div>
            <h1 class="h1" style="margin-top:10px">{{ $en ? 'One subscription for the workspace' : 'ერთი გამოწერა სამუშაო სივრცეზე' }}</h1>
            <p class="lead" style="margin-top:12px">{{ $en ? 'Projects share one monthly quota. Only successful (HTTP 200) responses count. A year is paid once and does not renew by itself.' : 'პროექტები ერთ თვიურ კვოტას იყოფენ. ითვლება მხოლოდ წარმატებული (HTTP 200) პასუხი. წელიწადი ერთხელ იხდება და თავისით არ გრძელდება.' }}</p>
            <div class="seg" style="margin-top:24px" role="group" aria-label="{{ $en ? 'Billing period' : 'გადახდის პერიოდი' }}">
                <button type="button" class="seg-b on" data-period="month" aria-pressed="true">{{ $en ? 'Monthly' : 'თვიური' }}</button>
                <button type="button" class="seg-b" data-period="year" aria-pressed="false">{{ $en ? 'Yearly' : 'წლიური' }} <span class="bd bd-ok" style="height:18px;font-size:11px;margin-left:4px">−15%</span></button>
            </div>
        </div>
        <div class="g3" style="align-items:start">
            @foreach ($plans as $plan)
                @php $hi = ! empty($plan['hi']); @endphp
                <div class="fc" style="padding:28px;position:relative;{{ $hi ? 'border:2px solid var(--ac);box-shadow:var(--sh2);' : '' }}">
                    @if ($hi)<span class="bd" style="position:absolute;top:-12px;left:24px;background:var(--ac);color:#fff">{{ $en ? 'Recommended' : 'რეკომენდებული' }}</span>@endif
                    <h3>{{ $plan['name'] }}</h3>
                    <div data-show="month">
                        <div style="margin-top:14px;display:flex;align-items:baseline;gap:6px"><span class="tabular" style="font-size:42px;font-weight:700;letter-spacing:-.02em">{{ $lari($plan['month']) }}</span><span class="mut">{{ $perMonth }}</span></div>
                    </div>
                    <div data-show="year" hidden>
                        <div style="margin-top:14px;display:flex;align-items:baseline;gap:6px"><span class="tabular" style="font-size:42px;font-weight:700;letter-spacing:-.02em">{{ $lari($plan['year']) }}</span><span class="mut">{{ $plan['year'] === 0 ? $perMonth : $perYear }}</span></div>
                        @if ($plan['year'] !== 0)
                            <div class="sm mut">{{ $lari(intdiv($plan['year'], 12)) }} {{ $perMonth }}<br><s>{{ $lari($plan['month'] * 12) }}</s> −15%</div>
                        @endif
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
            {{ $en ? 'Prices are in GEL and include VAT. The quota resets every month, including on a yearly payment.' : 'ფასები ლარშია და დღგ-ს მოიცავს. კვოტა ყოველ თვე ნულდება, წლიურ გადახდაზეც.' }}
            {{ $en ? 'Payment: Bank of Georgia. There is no automatic charge.' : 'გადახდა: Bank of Georgia. ავტომატური ჩამოჭრა არ არის.' }}
        </p>
        <p class="xs mut" style="text-align:center;margin-top:6px">{{ $en ? 'One subscription per workspace: all projects share the monthly quota. Prepaid packs: Starter +10 000 for '.$lari(900).', Growth +50 000 for '.$lari(2000).'; they expire after 90 days and are spent after the monthly quota. Extra active project: '.$lari(500).' / month. No overage bill.' : 'ერთი გამოწერა სამუშაო სივრცეზე: ყველა პროექტი თვიურ კვოტას იზიარებს. წინასწარი პაკეტები: Starter +10 000 — '.$lari(900).', Growth +50 000 — '.$lari(2000).'; 90 დღეში იწურება და თვიური კვოტის შემდეგ იხარჯება. დამატებითი აქტიური პროექტი: '.$lari(500).' / თვე. ზედმეტ მოხმარებაზე ანგარიში არ იწერება.' }}</p>
        <p class="xs mut" style="text-align:center;margin-top:6px">{{ $en ? 'Enterprise: custom limits and terms —' : 'Enterprise: ინდივიდუალური ლიმიტები და პირობები —' }} <a href="mailto:hello@geoapi.ge">hello@geoapi.ge</a></p>
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
