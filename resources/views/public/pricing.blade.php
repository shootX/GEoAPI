@php
    $en = app()->getLocale() === 'en';
@endphp
<div>
<div class="page-intro">
    <h1 class="text-3xl font-extrabold leading-tight">{{ __('Pricing') }}</h1>
    <p class="mt-3 text-base leading-7">{{ __('The free plan is the current limit. Pro is not on sale yet.') }}</p>
</div>
<div class="mk"><div class="plan-grid">
    <article class="price featured">
        <span class="tag">{{ $en ? 'Now' : 'ახლა' }}</span>
        <h2>Free</h2>
        <div class="amount">0 ₾</div>
        <ul>
            <li>{{ __('60 requests per minute and 10,000 successful requests per calendar month, per project.') }}</li>
            <li>{{ __('Server API') }}</li>
            <li>{{ __('Widget') }}</li>
        </ul>
        <a class="btn" href="{{ route('register') }}">{{ __('Create a free account') }}</a>
    </article>
    <article class="price">
        <span class="tag" style="background:#e7f6ef;color:#285A48">{{ __('Soon') }}</span>
        <h2>Pro — {{ __('Soon') }}</h2>
        <div class="amount">{{ $en ? 'Price soon' : 'ფასი მალე' }}</div>
        <ul>
            <li>{{ __('This plan is not available yet.') }}</li>
            <li>{{ $en ? 'The same catalog' : 'იგივე კატალოგი' }}</li>
            <li>{{ $en ? 'Not for sale yet' : 'ჯერ არ იყიდება' }}</li>
        </ul>
        <form class="notify" method="post" action="{{ route('coming-soon.store') }}">
            @csrf
            <label>{{ __('Email') }}
                <input type="email" name="email" required autocomplete="email" placeholder="email">
            </label>
            <button class="btn btn-line" type="submit">{{ __('Notify me at launch') }}</button>
        </form>
    </article>
</div></div>
<div class="mt-8 overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr>
                <th class="py-2 text-left"></th>
                <th class="py-2 text-left">Free</th>
                <th class="py-2 text-left">Pro</th>
            </tr>
        </thead>
        <tbody>
            <tr class="border-t border-border">
                <th class="py-3 text-left font-medium">{{ __('Requests per minute') }}</th>
                <td>60</td>
                <td>{{ __('Soon') }}</td>
            </tr>
            <tr class="border-t border-border">
                <th class="py-3 text-left font-medium">{{ __('Successful requests per month') }}</th>
                <td>10 000</td>
                <td>{{ __('Soon') }}</td>
            </tr>
            <tr class="border-t border-border">
                <th class="py-3 text-left font-medium">{{ __('Server API') }}</th>
                <td>{{ __('Included') }}</td>
                <td>{{ __('Soon') }}</td>
            </tr>
            <tr class="border-t border-border">
                <th class="py-3 text-left font-medium">{{ __('Widget') }}</th>
                <td>{{ __('Included') }}</td>
                <td>{{ __('Soon') }}</td>
            </tr>
        </tbody>
    </table>
</div>
</div>
