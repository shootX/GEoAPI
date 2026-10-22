@php
    $en = app()->getLocale() === 'en';
    $current = null;
    foreach ($projects as $p) {
        if ((int) $p['id'] === (int) $projectId) {
            $current = $p['name'];
        }
    }
    $statusLabel = $en
        ? ['paid' => 'paid', 'pending' => 'pending', 'failed' => 'failed', 'expired' => 'expired', 'refunded' => 'refunded', 'partial' => 'partly refunded']
        : ['paid' => 'გადახდილი', 'pending' => 'მოლოდინში', 'failed' => 'წარუმატებელი', 'expired' => 'ვადაგასული', 'refunded' => 'დაბრუნებული', 'partial' => 'ნაწილობრივ დაბრუნებული'];
    $currentBadge = '<span class="bd bd-ok" data-code="current">'.e($en ? 'Current' : 'მიმდინარე').'</span>';
    $currentBtn = '<button type="button" class="btn" disabled data-code="current-plan">'.e($en ? 'Current plan' : 'მიმდინარე გეგმა').'</button>';
    $statusTone = ['paid' => 'ok', 'pending' => 'wa', 'failed' => 'er', 'expired' => 'ne', 'refunded' => 'ne', 'partial' => 'wa'];
@endphp
<div>
<div class="app">
    <x-ga.panel-side active="billing" :project="$current" />
    <div class="main">
        <header class="top">
            <button class="btn menu" id="menu" type="button" aria-expanded="false" aria-controls="side" aria-label="{{ __('Menu') }}" onclick="var s=document.getElementById('side');var o=s.classList.toggle('open');this.setAttribute('aria-expanded',o?'true':'false')"><x-ga.icon name="menu" :size="20" /></button><a class="mlogo" href="{{ url('/') }}"><img src="{{ asset('assets/brand/geoapi-lockup-light.svg') }}" alt="geoapi.ge" height="22" width="69"></a>
            <h1>{{ $en ? 'Billing' : 'ბილინგი' }}</h1>
            <div class="who"><span class="seg hide-m" style="padding:3px"><a href="{{ route('locale.switch', 'ka') }}" class="{{ $en ? '' : 'on' }}">KA</a><a href="{{ route('locale.switch', 'en') }}" class="{{ $en ? 'on' : '' }}">EN</a></span></div>
        </header>
        <div class="content">
            <h1 class="mh">{{ $en ? 'Billing' : 'ბილინგი' }}</h1>
            @if (session('billing'))
                <div class="al al-ok"><x-ga.icon name="ok" :size="18" /><div>{{ session('billing') }}</div></div>
            @endif
            @if ($notice === 'return')
                <div class="al al-in"><x-ga.icon name="info" :size="18" /><div>{{ $en ? 'No bank confirmation yet. The return page alone does not turn the plan on.' : 'ბანკის დადასტურება ჯერ არ არის. გადახდის გვერდი უფლებას არ ჩართავს.' }}</div></div>
            @endif
            @if ($notice === 'test')
                <div class="al al-ok" data-code="test payment"><x-ga.icon name="ok" :size="18" /><div>{{ $en ? 'TEST payment recorded. The plan is active; the invoice is marked TEST. No money was charged.' : 'სატესტო გადახდა დაფიქსირდა. გეგმა ჩართულია; ინვოისი მონიშნულია TEST. თანხა არ ჩამოგეჭრათ.' }}</div></div>
            @endif
            @if ($notice === 'fail')
                <div class="al al-er"><x-ga.icon name="err" :size="18" /><div>{{ $en ? 'The payment was not completed. The quota did not change.' : 'გადახდა არ დასრულდა. კვოტა არ შეცვლილა.' }}</div></div>
            @endif
            @unless ($configured)
                <div class="al al-wa" data-code="payments not configured"><x-ga.icon name="warn" :size="18" /><div>{{ $en ? 'The payment provider is not connected yet. Card payment is temporarily unavailable.' : 'გადახდის პროვაიდერი ჯერ არ არის მიერთებული. ბარათით გადახდა დროებით მიუწვდომელია.' }}</div></div>
            @endunless
            @if ($rate === null)
                <div class="al al-wa" data-code="the rate is unavailable"><x-ga.icon name="warn" :size="18" /><div>{{ $en ? 'The National Bank rate is unavailable. A paid order is not created without it.' : 'ეროვნული ბანკის კურსი მიუწვდომელია. გადახდილი შეკვეთა კურსის გარეშე არ იქმნება.' }}</div></div>
            @endif
            @error('checkout')
                <div class="al al-er" data-code="{{ $message }}"><x-ga.icon name="err" :size="18" /><div>{{ $message === 'payments not configured' ? ($en ? 'The payment provider is not connected.' : 'გადახდის პროვაიდერი არ არის მიერთებული.') : ($message === 'no project' ? ($en ? 'Create a project first: the subscription is attached to your workspace through a project.' : 'ჯერ შექმენით პროექტი: გამოწერა სამუშაო სივრცეს პროექტის მეშვეობით ებმება.') : ($message === 'the rate is unavailable' ? ($en ? 'The rate is unavailable.' : 'კურსი მიუწვდომელია.') : $message)) }}</div></div>
            @enderror

            @if (! empty($v2))
                @include('livewire.account.billing-v2')
            @else
            <p class="muted">{{ $en ? 'Prices are in USD and include VAT. A year costs 12% less than 12 months. GEL is shown at the National Bank rate.' : 'ფასი დოლარშია და დღგ-ს შეიცავს. წელიწადი 12 თვის ფასზე 12%-ით ნაკლებია. ლარი ნაჩვენებია ეროვნული ბანკის კურსით.' }}</p>

            @if ($projects === [])
                <div class="panel"><h2>{{ $en ? 'Create a project first' : 'ჯერ შექმენით პროექტი' }}</h2><p class="muted">{{ $en ? 'You have no project yet.' : 'პროექტი ჯერ არ არის.' }}</p><p style="margin-top:12px"><a class="btn pine" href="{{ route('account.home') }}">{{ $en ? 'Projects' : 'პროექტები' }}</a></p></div>
            @else
                <div class="panel row" style="justify-content:space-between">
                    <label style="min-width:260px">{{ $en ? 'Project' : 'პროექტი' }}
                        <select wire:model.live="projectId">
                            @foreach ($projects as $project)
                                <option value="{{ $project['id'] }}">{{ $project['name'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($rate)
                        <span class="muted">{{ $en ? 'National Bank rate' : 'ეროვნული ბანკის კურსი' }}: 1 USD = {{ number_format((float) $rate['rate'], 4, '.', '') }} GEL, {{ $en ? 'date' : 'თარიღი' }} {{ $rate['rated_on'] }}.</span>
                    @endif
                </div>

                <div class="bill-plans">
                    <article class="plan">
                        <div class="row" style="justify-content:space-between"><h2>Free</h2>@if ($currentPlan === 'free'){!! $currentBadge !!}@endif</div>
                        <p class="price">$0</p>
                        <p class="muted">{{ $en ? '100 successful requests a month. 0 USD.' : '100 წარმატებული მოთხოვნა თვეში. 0 USD.' }}</p>
                        <p class="xs mut">{{ $en ? 'Choosing it lowers this project\'s monthly limit to 100, even if it has 10 000 now.' : 'არჩევა ამ პროექტის თვიურ ზღვარს 100-მდე დაწევს, მათ შორის მაშინაც, თუ ახლა 10 000 აქვს.' }}</p>
                        @if ($currentPlan === 'free')
                        {!! $currentBtn !!}
                        @else
                        <button type="button" class="btn" wire:click="chooseFree" wire:confirm="{{ $en ? 'Switch this project to Free (100 a month)?' : 'ამ პროექტს Free გეგმაზე (თვეში 100) გადაიყვანთ?' }}">{{ $en ? 'Choose Free' : 'უფასო გეგმის არჩევა' }}</button>
                        @endif
                    </article>
                    @foreach (['basic' => 'Basic', 'premium' => 'Premium'] as $code => $label)
                        @php
                            $monthCents = \App\Billing\Plans::usdCents($code, 'month');
                            $yearCents = \App\Billing\Plans::usdCents($code, 'year');
                            $monthUsd = number_format($monthCents / 100, 2, '.', '');
                            $yearUsd = number_format($yearCents / 100, 2, '.', '');
                            $monthGel = $rate ? \App\Billing\Plans::formatTetri(\App\Billing\Plans::gelTetri($monthCents, $rate['rate'], $rate['quantity'])) : null;
                            $yearGel = $rate ? \App\Billing\Plans::formatTetri(\App\Billing\Plans::gelTetri($yearCents, $rate['rate'], $rate['quantity'])) : null;
                            $off = ! $configured;
                            $isCurrent = $currentPlan === $code;
                        @endphp
                        <article class="plan {{ $isCurrent ? 'now' : '' }}">
                            <div class="row" style="justify-content:space-between"><h2>{{ $label }}</h2>@if ($isCurrent){!! $currentBadge !!}@elseif ($code === 'basic')<span class="bd bd-ac">{{ $en ? 'Recommended' : 'რეკომენდებული' }}</span>@endif</div>
                            <p class="price">${{ rtrim(rtrim($monthUsd, '0'), '.') }} <span class="muted">/ {{ $en ? 'month' : 'თვე' }}</span></p>
                            <p class="muted">@if ($code === 'premium') {{ $en ? 'Unlimited successful requests a month.' : 'შეუზღუდავი წარმატებული მოთხოვნა თვეში.' }} @else {{ $en ? '2 000 successful requests a month.' : '2 000 წარმატებული მოთხოვნა თვეში.' }} @endif</p>
                            <p class="xs mut">{{ $en ? 'Month' : 'თვე' }}: {{ $monthUsd }} USD @if ($monthGel) / {{ $monthGel }} GEL @endif<br>{{ $en ? 'Year' : 'წელი' }}: {{ $yearUsd }} USD @if ($yearGel) / {{ $yearGel }} GEL @endif · −12%</p>
                            <div class="row">
                                @if ($isCurrent)
                                {!! $currentBtn !!}
                                @else
                                <button type="button" class="btn pine" wire:click="checkout('{{ $code }}', 'month')" @if ($off) disabled @endif>{{ $en ? 'Pay · month' : 'გადახდა · თვე' }}</button>
                                <button type="button" class="btn" wire:click="checkout('{{ $code }}', 'year')" @if ($off) disabled @endif>{{ $en ? 'Pay · year' : 'გადახდა · წელი' }}</button>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                @if ($mock)
                <p class="xs mut" data-code="mock mode">{{ $en ? 'TEST mode: the bank is not connected yet, so checkout opens a test payment page. No card is asked for and no money is charged.' : 'სატესტო რეჟიმი: ბანკი ჯერ არ არის მიერთებული, ამიტომ გადახდა სატესტო გვერდზე გადადის. ბარათის მონაცემები არ მოითხოვება და თანხა არ ჩამოიჭრება.' }}</p>
                @else
                <p class="xs mut">{{ $en ? 'Payment: Bank of Georgia. You return here after the bank page; the plan turns on only after the bank confirms.' : 'გადახდა: Bank of Georgia. ბანკის გვერდის შემდეგ აქ დაბრუნდებით; გეგმა ჩაირთვება მხოლოდ ბანკის დადასტურების შემდეგ.' }}</p>
                @endif
            @endif
            @endif

            @if (count($orders) > 0)
                <div class="panel" style="padding:0;overflow:hidden">
                    <h2 style="padding:18px 20px 0">{{ $en ? 'Orders' : 'შეკვეთები' }}</h2>
                    <div style="overflow-x:auto;margin-top:12px;border-top:1px solid var(--line)">
                        <table class="orders">
                            <thead><tr><th>{{ $en ? 'Plan' : 'გეგმა' }}</th><th>{{ $en ? 'Status' : 'სტატუსი' }}</th><th style="text-align:right">GEL</th></tr></thead>
                            <tbody>
                                @foreach ($orders as $order)
                                    <tr><td class="mono" data-label="{{ $en ? 'Plan' : 'გეგმა' }}"><span>{{ $order->plan_code }}@if (\App\Billing\MockPay::isMock($order)) <span class="bd bd-wa">TEST</span>@endif</span></td><td data-label="{{ $en ? 'Status' : 'სტატუსი' }}"><span class="bd bd-{{ $statusTone[$order->status] ?? 'ne' }}"><i></i>{{ $statusLabel[$order->status] ?? $order->status }}</span></td><td style="text-align:right" class="mono" data-label="GEL">{{ $order->gel_amount }} GEL</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
</div>
