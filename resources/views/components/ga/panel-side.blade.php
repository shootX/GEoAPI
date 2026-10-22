@props(['active' => 'overview', 'spa' => false, 'project' => null, 'plan' => null])
@php
    $en = app()->getLocale() === 'en';
    $user = auth()->user();
    $initials = $user ? mb_strtoupper(mb_substr((string) $user->name, 0, 1)) : 'G';
    $items = [
        ['overview', 'home', $en ? 'Overview' : 'მიმოხილვა'],
        ['keys', 'key', $en ? 'API keys' : 'API გასაღებები'],
        ['plan', 'bar', $en ? 'Plan' : 'გეგმა'],
        ['catalog', 'db', $en ? 'Catalog' : 'კატალოგი'],
    ];
@endphp
<aside class="side" id="side">
    <a class="logo" href="{{ url('/') }}"><img src="{{ asset('assets/brand/geoapi-lockup-light.svg') }}" alt="geoapi.ge" height="26"></a>
    @if ($project)
        <div class="proj"><span class="av">{{ mb_strtoupper(mb_substr((string) $project, 0, 1)) }}</span><div style="flex:1;min-width:0;line-height:1.25"><div class="pn">{{ $project }}</div><div class="xs mut">{{ $plan }}</div></div></div>
    @endif
    <nav class="nav" aria-label="{{ $en ? 'Cabinet' : 'პანელი' }}">
        @foreach ($items as [$key, $icon, $label])
            @if ($spa)
                <button type="button" data-screen="{{ $key }}" @if ($active === $key) aria-current="page" @endif><x-ga.icon :name="$icon" :size="18" /><span>{{ $label }}</span></button>
            @else
                <a href="{{ route('account.home') }}#{{ $key }}"><x-ga.icon :name="$icon" :size="18" /><span>{{ $label }}</span></a>
            @endif
        @endforeach
        <a href="{{ route('account.billing') }}" @if ($active === 'billing') aria-current="page" @endif><x-ga.icon name="card" :size="18" /><span>{{ $en ? 'Billing' : 'ბილინგი' }}</span></a>
        <div class="gr">{{ $en ? 'ACCOUNT' : 'ანგარიში' }}</div>
        <a href="{{ \Illuminate\Support\Facades\Route::has('settings.profile') ? route('settings.profile') : url('/account') }}"><x-ga.icon name="user" :size="18" /><span>{{ $en ? 'Settings' : 'პარამეტრები' }}</span></a>
        <a href="{{ url('/guide') }}"><x-ga.icon name="doc" :size="18" /><span style="flex:1">{{ $en ? 'Documentation' : 'დოკუმენტაცია' }}</span><x-ga.icon name="ext" :size="12" /></a>
    </nav>
    @if ($user)
        <div class="me"><span class="av round">{{ $initials }}</span><div style="min-width:0;line-height:1.3"><div class="pn">{{ $user->name }}</div><div class="xs mut" style="overflow:hidden;text-overflow:ellipsis">{{ $user->email }}</div></div></div>
    @endif
</aside>
