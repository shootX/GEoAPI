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
            'href' => url('/account/billing?plan=starter'),
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
            'href' => url('/account/billing?plan=growth'),
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
            'href' => url('/account/billing?plan=business'),
            'buy' => true,
        ];
    }
    $perMonth = $en ? '/ month' : '/ თვე';
    $perYear = $en ? '/ year' : '/ წელი';
@endphp
<div data-billing-v2="1">
<section class="s-c6eb23 sec" data-billing="month">
    <div class="wrap">
        <div class="sec-t">
            <div class="eyebrow">{{ $en ? 'Pricing' : 'ტარიფები' }}</div>
            <h1 class="s-d2c171 h1">{{ $en ? 'One subscription for the workspace' : 'ერთი გამოწერა სამუშაო სივრცეზე' }}</h1>
            <p class="s-56f435 lead">{{ $en ? 'Projects share one monthly quota. Only successful (HTTP 200) responses count. A year is paid once and does not renew by itself.' : 'პროექტები ერთ თვიურ კვოტას იყოფენ. ითვლება მხოლოდ წარმატებული (HTTP 200) პასუხი. წელიწადი ერთხელ იხდება და თავისით არ გრძელდება.' }}</p>
            <div class="s-49fcd8 seg" role="group" aria-label="{{ $en ? 'Billing period' : 'გადახდის პერიოდი' }}">
                <button type="button" class="seg-b on" data-period="month" aria-pressed="true">{{ $en ? 'Monthly' : 'თვიური' }}</button>
                <button type="button" class="seg-b" data-period="year" aria-pressed="false">{{ $en ? 'Yearly' : 'წლიური' }} <span class="s-6cb639 bd bd-ok">−15%</span></button>
            </div>
        </div>
        <div class="s-dfb216 g3">
            @foreach ($plans as $plan)
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
                            <div class="sm mut">{{ $lari(intdiv($plan['year'], 12)) }} {{ $perMonth }}<br><s>{{ $lari($plan['month'] * 12) }}</s> −15%</div>
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
            {{ $en ? 'Prices are in GEL and include VAT. The quota resets every month, including on a yearly payment.' : 'ფასები ლარშია და დღგ-ს მოიცავს. კვოტა ყოველ თვე ნულდება, წლიურ გადახდაზეც.' }}
            {{ $en ? 'Payment: Bank of Georgia. There is no automatic charge.' : 'გადახდა: Bank of Georgia. ავტომატური ჩამოჭრა არ არის.' }}
        </p>
        <p class="s-ed74eb xs mut">{{ $en ? 'One subscription per workspace: all projects share the monthly quota. Prepaid packs: Starter +10 000 for '.$lari(\App\Billing\V2\Catalog::packs()['starter']['tetri']).', Growth +50 000 for '.$lari(\App\Billing\V2\Catalog::packs()['growth']['tetri']).'; they expire after 90 days and are spent after the monthly quota. Extra active project: '.$lari(\App\Billing\V2\Catalog::EXTRA_PROJECT_TETRI).' / month. No overage bill.' : 'ერთი გამოწერა სამუშაო სივრცეზე: ყველა პროექტი თვიურ კვოტას იზიარებს. წინასწარი პაკეტები: Starter +10 000 — '.$lari(\App\Billing\V2\Catalog::packs()['starter']['tetri']).', Growth +50 000 — '.$lari(\App\Billing\V2\Catalog::packs()['growth']['tetri']).'; 90 დღეში იწურება და თვიური კვოტის შემდეგ იხარჯება. დამატებითი აქტიური პროექტი: '.$lari(\App\Billing\V2\Catalog::EXTRA_PROJECT_TETRI).' / თვე. ზედმეტ მოხმარებაზე ანგარიში არ იწერება.' }}</p>
        <p class="s-ed74eb xs mut">{{ $en ? 'Enterprise: custom limits and terms —' : 'Enterprise: ინდივიდუალური ლიმიტები და პირობები —' }} <a href="mailto:hello@geoapi.ge">hello@geoapi.ge</a></p>
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
