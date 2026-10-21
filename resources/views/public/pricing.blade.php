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
    $basicMonth = 5900;
    $basicYear = \App\Billing\Plans::usdCents('basic', 'year');
    $premiumMonth = 9900;
    $premiumYear = \App\Billing\Plans::usdCents('premium', 'year');
@endphp
<div>
<div class="page-intro">
    <h1 class="text-3xl font-extrabold leading-tight">{{ __('Pricing') }}</h1>
    <p class="mt-3 text-base leading-7">{{ $en ? 'A new project starts at 100 successful requests a month. An existing project keeps 10 000 until a plan is chosen. Prices include VAT. A year is 12% below twelve monthly prices.' : 'ახალი პროექტი თვეში 100 წარმატებული მოთხოვნით იწყება. არსებული პროექტი 10 000-ს ინარჩუნებს, სანამ გეგმას აირჩევს. ფასი დღგ-ს შეიცავს. წელიწადი 12 თვის ფასზე 12%-ით ნაკლებია.' }}</p>
    @if ($rate)
        <p class="mt-2 text-sm">{{ $en ? 'National Bank of Georgia rate' : 'ეროვნული ბანკის კურსი' }}: 1 USD = {{ $rate['rate'] }} GEL, {{ $rate['rated_on'] }}.</p>
    @else
        <p class="mt-2 text-sm">{{ $en ? 'the rate is unavailable. GEL is shown on the billing page after the rate is stored.' : 'კურსი ჯერ არ არის. ლარი გადახდის გვერდზე გამოჩნდება, როცა კურსი შეინახება.' }}</p>
    @endif
</div>
<div class="mk"><div class="plan-grid">
    <article class="price featured">
        <span class="tag">{{ $en ? 'Now' : 'ახლა' }}</span>
        <h2>Free</h2>
        <div class="amount">0 USD</div>
        <ul>
            <li>{{ $en ? '100 successful requests a month for a new project.' : 'ახალ პროექტზე თვეში 100 წარმატებული მოთხოვნა.' }}</li>
            <li>{{ $en ? 'An existing project keeps 10 000 until a plan is chosen.' : 'არსებული პროექტი 10 000-ს ინარჩუნებს, სანამ გეგმას აირჩევს.' }}</li>
            <li>{{ $en ? '60 requests a minute. The daily cap is unchanged.' : 'წუთში 60 მოთხოვნა. დღიური ზღვარი უცვლელია.' }}</li>
        </ul>
        <a class="btn" href="{{ route('register') }}">{{ __('Create a free account') }}</a>
    </article>
    <article class="price">
        <span class="tag">Basic</span>
        <h2>Basic</h2>
        <div class="amount">59 USD</div>
        <ul>
            <li>{{ $en ? '2 000 successful requests a month.' : 'თვეში 2 000 წარმატებული მოთხოვნა.' }}</li>
            <li>{{ $en ? 'Year: '.number_format($basicYear / 100, 2, '.', '').' USD'.($gel($basicYear) ? ' / '.$gel($basicYear).' GEL' : '') : 'წელი: '.number_format($basicYear / 100, 2, '.', '').' USD'.($gel($basicYear) ? ' / '.$gel($basicYear).' GEL' : '') }}</li>
            <li>{{ $gel($basicMonth) ? ($en ? 'Month: '.$gel($basicMonth).' GEL' : 'თვე: '.$gel($basicMonth).' GEL') : ($en ? 'GEL follows the stored bank rate.' : 'ლარი შენახულ საბანკო კურსს მიჰყვება.') }}</li>
        </ul>
        <a class="btn" href="{{ route('register') }}">{{ $en ? 'Create an account' : 'ანგარიშის შექმნა' }}</a>
    </article>
    <article class="price">
        <span class="tag">Premium</span>
        <h2>Premium</h2>
        <div class="amount">99 USD</div>
        <ul>
            <li>{{ $en ? 'Unlimited successful requests a month.' : 'თვეში შეუზღუდავი წარმატებული მოთხოვნა.' }}</li>
            <li>{{ $en ? 'Year: '.number_format($premiumYear / 100, 2, '.', '').' USD'.($gel($premiumYear) ? ' / '.$gel($premiumYear).' GEL' : '') : 'წელი: '.number_format($premiumYear / 100, 2, '.', '').' USD'.($gel($premiumYear) ? ' / '.$gel($premiumYear).' GEL' : '') }}</li>
            <li>{{ $gel($premiumMonth) ? ($en ? 'Month: '.$gel($premiumMonth).' GEL' : 'თვე: '.$gel($premiumMonth).' GEL') : ($en ? 'GEL follows the stored bank rate.' : 'ლარი შენახულ საბანკო კურსს მიჰყვება.') }}</li>
        </ul>
        <a class="btn" href="{{ route('register') }}">{{ $en ? 'Create an account' : 'ანგარიშის შექმნა' }}</a>
    </article>
</div></div>
</div>
