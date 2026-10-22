<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
@include('components.layouts.partials.head')
</head>
@php
    $showBlog = \Illuminate\Support\Facades\Schema::hasTable('blog_posts')
        && \App\Models\BlogPost::query()->visible()->exists();
    $homeNav = ! empty($home ?? null);
    $en = app()->getLocale() === 'en';
    $active = $active ?? '';
    // "Integrations" is a Proposed screen (phase 5) and stays out of the nav until it ships.
    $nav = [
        ['key' => 'docs', 'href' => url('/guide'), 'label' => $en ? 'Documentation' : 'დოკუმენტაცია'],
        ['key' => 'pricing', 'href' => route('pricing'), 'label' => $en ? 'Pricing' : 'ტარიფები'],
        ['key' => 'demo', 'href' => url('/demo'), 'label' => $en ? 'Demo' : 'დემო'],
    ];
    if ($showBlog) {
        $nav[] = ['key' => 'blog', 'href' => url('/blog'), 'label' => $en ? 'Blog' : 'ბლოგი'];
    }
    $slogan = $en ? 'Brands, models, generations and specifications — one API.' : 'ბრენდები, მოდელები, თაობები და სპეციფიკაციები — ერთი API-ით.';
@endphp
<body class="site ga min-h-screen antialiased {{ $homeNav ? 'is-home' : '' }}">
    <header class="hd">
        <div class="wrap">
            <a href="{{ url('/') }}" aria-label="GeoAPI.ge"><img src="{{ asset('assets/brand/geoapi-lockup-light.svg') }}" alt="geoapi.ge" style="height:30px;display:block" width="94" height="30"></a>
            <nav class="nav" aria-label="{{ __('Menu') }}">
                @foreach ($nav as $item)
                    <a href="{{ $item['href'] }}" class="{{ $active === $item['key'] ? 'on' : '' }}" @if ($active === $item['key']) aria-current="page" @endif>{{ $item['label'] }}</a>
                @endforeach
            </nav>
            <div class="hd-r">
                <span class="seg hide-m" style="padding:3px" aria-label="{{ __('Language') }}">
                    <a href="{{ route('locale.switch', 'ka') }}" class="{{ $en ? '' : 'on' }}" @unless ($en) aria-current="true" @endunless>KA</a>
                    <a href="{{ route('locale.switch', 'en') }}" class="{{ $en ? 'on' : '' }}" @if ($en) aria-current="true" @endif>EN</a>
                </span>
                @auth
                    <a class="btn b-pri hide-m" href="{{ url('/account') }}">{{ $en ? 'Cabinet' : 'კაბინეტი' }}</a>
                @else
                    <a class="btn b-gh hide-m" href="{{ route('login') }}">{{ $en ? 'Sign in' : 'შესვლა' }}</a>
                    <a class="btn b-pri hide-m" href="{{ route('register') }}">{{ $en ? 'Start free' : 'დაიწყე უფასოდ' }}</a>
                @endauth
                <button type="button" class="burger" aria-expanded="false" aria-controls="site-menu" aria-label="{{ __('Menu') }}" data-nav-toggle><x-ga.icon name="menu" :size="24" /></button>
            </div>
        </div>
    </header>
    <nav id="site-menu" class="m-menu" hidden>
        <div class="wrap">
            @foreach ($nav as $item)
                <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
            @endforeach
            @auth
                <a href="{{ url('/account') }}">{{ $en ? 'Cabinet' : 'კაბინეტი' }}</a>
            @else
                <a href="{{ route('login') }}">{{ $en ? 'Sign in' : 'შესვლა' }}</a>
                <a class="btn b-pri" style="justify-content:center;margin-top:6px;color:#fff" href="{{ route('register') }}">{{ $en ? 'Start free' : 'დაიწყე უფასოდ' }}</a>
            @endauth
            <span class="seg" style="padding:3px;margin-top:8px;align-self:flex-start">
                <a href="{{ route('locale.switch', 'ka') }}" class="{{ $en ? '' : 'on' }}">KA</a>
                <a href="{{ route('locale.switch', 'en') }}" class="{{ $en ? 'on' : '' }}">EN</a>
            </span>
        </div>
    </nav>
    <main class="site-main {{ !empty($bleed) || $homeNav ? 'is-bleed' : 'wrap' }}" style="{{ !empty($bleed) || $homeNav ? '' : 'padding-block:56px 96px' }}">
        @if (session('coming_soon_saved'))
            <p class="al al-ok" style="margin-bottom:24px">{{ __('Thanks. We will write when Pro opens.') }}</p>
        @endif
        {{ $slot }}
    </main>
    <footer class="ft">
        <div class="wrap">
            <div>
                <img src="{{ asset('assets/brand/geoapi-lockup-light.svg') }}" alt="geoapi.ge" style="height:28px" width="88" height="28">
                <p class="sm mut" style="margin-top:10px;max-width:300px">{{ $slogan }}</p>
                <p class="sm" style="margin-top:12px"><a href="mailto:hello@geoapi.ge" style="color:var(--ac)">hello@geoapi.ge</a></p>
            </div>
            <div class="cols">
                <div><b>{{ $en ? 'Product' : 'პროდუქტი' }}</b><a href="{{ route('pricing') }}">{{ $en ? 'Pricing' : 'ტარიფები' }}</a><a href="{{ url('/demo') }}">{{ $en ? 'Demo' : 'დემო' }}</a></div>
                <div><b>{{ $en ? 'Developers' : 'დეველოპერებს' }}</b><a href="{{ url('/guide') }}">{{ $en ? 'Documentation' : 'დოკუმენტაცია' }}</a><a href="{{ url('/api/v1/docs') }}">API reference</a><a href="{{ url('/api/v1/openapi.json') }}">OpenAPI JSON</a><a href="{{ url('/api/v1/postman.json') }}">Postman</a></div>
                <div><b>{{ $en ? 'Company' : 'კომპანია' }}</b>@if ($showBlog)<a href="{{ url('/blog') }}">{{ $en ? 'Blog' : 'ბლოგი' }}</a>@endif<a href="{{ url('/terms') }}">{{ $en ? 'Terms' : 'წესები' }}</a><a href="{{ url('/privacy') }}">{{ $en ? 'Privacy' : 'კონფიდენციალურობა' }}</a></div>
            </div>
        </div>
        <div class="wrap xs mut" style="margin-top:28px;display:flex;justify-content:space-between"><span>© {{ date('Y') }} GeoAPI.ge</span><span>KA · EN</span></div>
    </footer>
    <script>
        (function () {
            var toggle = document.querySelector('[data-nav-toggle]');
            var menu = document.getElementById('site-menu');
            function closeMenu() {
                if (!menu || !toggle) return;
                menu.hidden = true;
                toggle.setAttribute('aria-expanded', 'false');
            }
            if (toggle && menu) {
                toggle.addEventListener('click', function () {
                    var open = menu.hidden;
                    menu.hidden = !open;
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                });
                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') closeMenu();
                });
            }
            document.addEventListener('click', function (event) {
                var tab = event.target.closest('[data-code-tab]');
                if (tab) {
                    var box = tab.closest('[data-code]');
                    box.querySelectorAll('[data-code-tab]').forEach(function (button) {
                        button.setAttribute('aria-selected', button === tab ? 'true' : 'false');
                    });
                    box.querySelectorAll('[data-code-panel]').forEach(function (panel) {
                        panel.hidden = panel.getAttribute('data-code-panel') !== tab.getAttribute('data-code-tab');
                    });
                    return;
                }
                var copy = event.target.closest('[data-copy-target]');
                if (!copy) return;
                var node = document.getElementById(copy.getAttribute('data-copy-target'));
                if (!node || !navigator.clipboard) return;
                var previous = copy.textContent;
                navigator.clipboard.writeText(node.innerText).then(function () {
                    copy.textContent = copy.getAttribute('data-copied') || previous;
                    setTimeout(function () { copy.textContent = previous; }, 2000);
                });
            });
        })();
    </script>
</body>
</html>
