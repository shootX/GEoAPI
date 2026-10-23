@props(['title', 'active'])
@php $en = app()->getLocale() === 'en'; @endphp
<header class="top">
    <button class="btn menu" id="menu" type="button" aria-expanded="false" aria-controls="side" aria-label="{{ __('Menu') }}" data-menu-toggle><x-ga.icon name="menu" :size="20" /></button><a class="mlogo" href="{{ url('/') }}"><img src="{{ asset('assets/brand/geoapi-lockup-light.svg') }}" alt="geoapi.ge" height="22" width="69"></a>
    <h1>{{ $title }}</h1>
    <div class="who"><span class="s-cda68d seg hide-m"><a href="{{ route('locale.switch', 'ka') }}" class="{{ $en ? '' : 'on' }}">KA</a><a href="{{ route('locale.switch', 'en') }}" class="{{ $en ? 'on' : '' }}">EN</a></span></div>
</header>
<nav class="tabs-set" aria-label="{{ $en ? 'Settings' : 'პარამეტრები' }}">
    <a href="{{ route('settings.profile') }}" @if ($active === 'profile') aria-current="page" @endif><x-ga.icon name="user" :size="16" />{{ $en ? 'Profile' : 'პროფილი' }}</a>
    <a href="{{ route('settings.security') }}" @if ($active === 'security') aria-current="page" @endif><x-ga.icon name="lock" :size="16" />{{ $en ? 'Security' : 'უსაფრთხოება' }}</a>
</nav>
