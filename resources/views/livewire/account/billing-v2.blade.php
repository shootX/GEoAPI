@php
    $en = app()->getLocale() === 'en';
    $fmt = fn (int $n): string => number_format($n, 0, '.', ' ');
    $lari = fn (int $cents): string => \App\Billing\Plans::lari($cents);
    $planNow = is_array($v2) ? (string) ($v2['plan'] ?? 'free') : 'free';
    $shared = is_array($v2) && ! empty($v2['shared']);
    $tbs = fn ($t) => $t ? \Illuminate\Support\Carbon::parse((string) $t, config('app.timezone'))->setTimezone('Asia/Tbilisi')->format('Y-m-d H:i') . ' (' . ($en ? 'Tbilisi' : 'თბილისი') . ')' : '';
    $isFree = strtolower((string) ($v2['label'] ?? '')) === 'free';
@endphp
<p class="muted">{{ $en ? 'Prices are in GEL and include VAT. A year is 15% less than 12 months and still resets the quota each month. Nothing is charged again unless you pay.' : 'ფასი ლარშია და დღგ-ს შეიცავს. წელიწადი 12 თვეზე 15%-ით ნაკლებია და კვოტა მაინც ყოველ თვე ნულდება. თანხა ხელახლა არ ჩამოიჭრება, სანამ თავად არ გადაიხდით.' }}</p>
@if ($shared)
    <div class="panel" data-v2-subscription>
        <h2>{{ $en ? 'Subscription' : 'გამოწერა' }} · {{ $v2['label'] }}</h2>
        <p class="muted">{{ $en ? 'Shared quota' : 'საერთო კვოტა' }}: {{ $fmt((int) $v2['used']) }} / {{ $fmt((int) $v2['quota']) }}. {{ $en ? 'Credits' : 'კრედიტი' }}: {{ $fmt((int) $v2['credits']) }}. {{ $en ? 'Active projects' : 'აქტიური პროექტები' }}: {{ (int) $v2['active'] }} / {{ (int) $v2['allowed'] }}. {{ $en ? 'Requests a minute' : 'მოთხოვნა წუთში' }}: {{ (int) $v2['rpm'] }}.</p>
        <p class="xs mut">{{ $en ? 'This period ends' : 'ეს პერიოდი სრულდება' }} {{ $tbs($v2['period_end']) }}.@unless ($isFree) {{ $en ? 'Paid through' : 'გადახდილია' }} {{ $tbs($v2['term_end']) }}.@endunless@if ($v2['pending']) {{ $en ? 'Next term' : 'შემდეგი ვადა' }}: {{ $v2['pending'] }}.@endif</p>
        @if (count($v2['credit_rows']) > 0)
            <ul class="sm">
                @foreach ($v2['credit_rows'] as $credit)
                    <li>{{ $fmt((int) $credit->remaining) }} · {{ $tbs($credit->expires_at) }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
<div class="bill-plans">
    @foreach (['starter' => 'Starter', 'growth' => 'Growth'] as $code => $label)
        @php
            $monthCents = \App\Billing\V2\Catalog::tetri($code, 'month');
            $yearCents = \App\Billing\V2\Catalog::tetri($code, 'year');
            $isCurrent = $planNow === $code;
            $higher = $shared && \App\Billing\V2\Catalog::rank($code) > \App\Billing\V2\Catalog::rank($planNow === 'legacy-grandfathered' ? 'free' : $planNow);
            $lower = $shared && \App\Billing\V2\Catalog::rank($code) < \App\Billing\V2\Catalog::rank($planNow);
        @endphp
        @php $picked = ! $isCurrent && $chosen === $code; @endphp
        <article id="plan-{{ $code }}" class="plan {{ $isCurrent ? 'now' : '' }} {{ $picked ? 'picked' : '' }}">
            <div class="s-a562f4 row"><h2>{{ $label }}</h2>@if ($isCurrent)<span class="bd bd-ok">{{ $en ? 'Current' : 'მიმდინარე' }}</span>@elseif ($picked)<span class="bd" data-code="picked">{{ $en ? 'Your choice' : 'თქვენი არჩევანი' }}</span>@endif</div>
            <p class="price">{{ $lari($monthCents) }} <span class="muted">/ {{ $en ? 'month' : 'თვე' }}</span></p>
            <p class="muted">{{ $fmt(\App\Billing\V2\Catalog::plans()[$code]['quota']) }} {{ $en ? 'successful requests a month.' : 'წარმატებული მოთხოვნა თვეში.' }}</p>
            <p class="xs mut">{{ $en ? 'Year' : 'წელი' }}: {{ $lari($yearCents) }} · −15%</p>
            <div class="row">
                @if ($lower)
                    <button type="button" class="btn" wire:click="scheduleDowngrade('{{ $code }}')">{{ $en ? 'At the next term' : 'შემდეგ ვადაზე' }}</button>
                @elseif ($isCurrent)
                    <button type="button" class="btn pine" wire:click="checkout('{{ $code }}', 'month')" @if (! $configured) disabled @endif>{{ $en ? 'Pay next month' : 'შემდეგი თვის გადახდა' }}</button>
                    <button type="button" class="btn" wire:click="checkout('{{ $code }}', 'year')" @if (! $configured) disabled @endif>{{ $en ? 'Pay a year' : 'წლის გადახდა' }}</button>
                @else
                    <button type="button" class="btn pine" wire:click="checkout('{{ $code }}', 'month')" @if (! $configured) disabled @endif>{{ $higher ? ($en ? 'Upgrade · month' : 'გაზრდა · თვე') : ($en ? 'Pay · month' : 'გადახდა · თვე') }}</button>
                    <button type="button" class="btn" wire:click="checkout('{{ $code }}', 'year')" @if (! $configured) disabled @endif>{{ $en ? 'Pay · year' : 'გადახდა · წელი' }}</button>
                @endif
            </div>
        </article>
    @endforeach
    <article class="plan">
        <h2>{{ $en ? 'Add-ons' : 'დამატებები' }}</h2>
        <p class="muted">{{ $en ? 'Extra active project: '.$lari(\App\Billing\V2\Catalog::EXTRA_PROJECT_TETRI).' for this period. Request packs expire after 90 days and are spent after the monthly quota. No overage is billed.' : 'დამატებითი აქტიური პროექტი: '.$lari(\App\Billing\V2\Catalog::EXTRA_PROJECT_TETRI).' ამ პერიოდზე. მოთხოვნის პაკეტი 90 დღეში იწურება და თვიური კვოტის შემდეგ იხარჯება. ზედმეტი ანგარიში არ იწერება.' }}</p>
        <div class="row">
            <button type="button" class="btn" wire:click="buyExtraProject" @if (! $configured) disabled @endif>{{ $en ? 'Extra project' : 'დამატებითი პროექტი' }} · {{ $lari(\App\Billing\V2\Catalog::EXTRA_PROJECT_TETRI) }}</button>
            @if (isset(\App\Billing\V2\Catalog::packs()[$planNow]))
                @php $pack = \App\Billing\V2\Catalog::packs()[$planNow]; @endphp
                <button type="button" class="btn pine" wire:click="buyCredits('{{ $planNow }}')" @if (! $configured) disabled @endif>{{ $fmt($pack['requests']) }} · {{ $lari($pack['tetri']) }}</button>
            @endif
        </div>
    </article>
</div>
<p class="xs mut">{{ $en ? 'Business is not for sale while its rate limit is still being measured. Enterprise is assigned by an administrator.' : 'Business ჯერ არ იყიდება, სანამ მისი სიჩქარის ზღვარი იზომება. Enterprise-ს ადმინისტრატორი ნიშნავს.' }}</p>
@if (count($orders) > 0)
    <div class="s-22a267 panel">
        <h2 class="s-bb9e9f">{{ $en ? 'Invoices' : 'ინვოისები' }}</h2>
        <div class="s-c7d5b9">
            <table class="orders">
                <thead><tr><th>{{ $en ? 'Plan' : 'გეგმა' }}</th><th>{{ $en ? 'Status' : 'სტატუსი' }}</th><th class="s-54c2af">GEL</th><th></th></tr></thead>
                <tbody>
                    @foreach ($orders as $order)
                        @php $invoiceId = \Illuminate\Support\Facades\DB::table('billing_invoices')->where('billing_order_id', $order->id)->value('id'); @endphp
                        <tr>
                            <td class="mono">{{ $order->plan_code }}@if (\App\Billing\MockPay::isMock($order)) <span class="bd bd-wa">TEST</span>@endif</td>
                            <td>{{ $order->status }}</td>
                            <td class="s-54c2af mono">{{ $order->gel_amount }}</td>
                            <td>@if ($invoiceId)<a href="{{ route('account.invoice', $invoiceId) }}">PDF</a>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
