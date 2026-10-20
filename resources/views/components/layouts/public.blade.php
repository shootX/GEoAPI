<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @php
        $seo = $seo ?? [];
        $canonical = $seo['canonical'] ?? url()->current();
        $hreflang = $seo['hreflang'] ?? ['ka' => url('/ka'), 'en' => url('/en'), 'x-default' => url('/')];
        $ogImage = $seo['image'] ?? asset('assets/brand/favicon-32-lime.svg');
        $ogType = $seo['type'] ?? 'website';
        $twitterCard = $seo['card'] ?? 'summary';
    @endphp
    <title>{{ $title ?? 'CarAPI' }}</title>
    @if (!empty($description))
        <meta name="description" content="{{ $description }}">
    @endif
    @if (!empty($robots))
        <meta name="robots" content="{{ $robots }}">
    @endif
    <link rel="icon" href="{{ asset('assets/brand/favicon-32-lime.svg') }}" type="image/svg+xml">
    <link rel="canonical" href="{{ $canonical }}">
    @foreach ($hreflang as $lang => $href)
        <link rel="alternate" hreflang="{{ $lang }}" href="{{ $href }}">
    @endforeach
    <meta property="og:title" content="{{ $title ?? 'CarAPI' }}">
    @if (!empty($description))
        <meta property="og:description" content="{{ $description }}">
        <meta name="twitter:description" content="{{ $description }}">
    @endif
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta name="twitter:card" content="{{ $twitterCard }}">
    <meta name="twitter:title" content="{{ $title ?? 'CarAPI' }}">
    <link rel="stylesheet" href="{{ asset('assets/public-design.css') }}?v=20261020s">
    <x-electrik::brand-styles />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        article section { border: 1px solid var(--slate-border, #d7e7df); border-radius: 0.75rem; padding: 1.25rem; background: #fff; }
        article h1 { font-size: 1.875rem; font-weight: 800; letter-spacing: -0.02em; color: #091413; }
        article h2 { margin-top: 1rem; font-size: 1.05rem; font-weight: 600; }
        article p, article li { margin-top: 0.75rem; color: var(--slate-muted-foreground, #3d564c); }
        article a { text-decoration: underline; text-underline-offset: 4px; }
        article table { width: 100%; margin-top: 0.75rem; border-collapse: collapse; }
        article th, article td { padding: 0.45rem 0.75rem 0.45rem 0; text-align: left; vertical-align: top; }
        article code { font-size: 0.85em; }
    </style>
</head>
@php
    $showBlog = \Illuminate\Support\Facades\Schema::hasTable('blog_posts')
        && \App\Models\BlogPost::query()->visible()->exists();
    $homeNav = ! empty($home ?? null);
    $nav = [
        ['href' => $homeNav ? '#features' : url('/').'#features', 'label' => __('Features')],
        ['href' => $homeNav ? '#how' : url('/').'#how', 'label' => __('How it works')],
        ['href' => $homeNav ? '#pricing' : route('pricing'), 'label' => __('Pricing')],
        ['href' => url('/demo'), 'label' => __('Demo')],
        ['href' => url('/guide'), 'label' => __('Documentation')],
    ];
    if ($showBlog) {
        $nav[] = ['href' => url('/blog'), 'label' => __('Blog')];
    }
@endphp
<body class="site min-h-screen text-foreground antialiased {{ $homeNav ? 'is-home' : '' }}">
    <div class="flex min-h-screen flex-col">
        <header class="site-header {{ $homeNav ? 'is-overlay' : 'is-solid' }}">
            <div class="m-wrap">
                <a class="m-logo" href="{{ url('/') }}"><span class="m-mark" aria-hidden="true"></span> CarAPI</a>
                <nav class="m-nav" aria-label="{{ __('Menu') }}">
                    @foreach ($nav as $item)
                        <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                    @endforeach
                    @auth
                        <a href="{{ url('/account') }}">{{ __('Cabinet') }}</a>
                    @else
                        <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
                    @endauth
                </nav>
                <nav class="m-lang" aria-label="{{ __('Language') }}">
                    <a href="{{ route('locale.switch', 'ka') }}" @if (app()->getLocale() === 'ka') aria-current="true" @endif>KA</a>
                    <span aria-hidden="true">/</span>
                    <a href="{{ route('locale.switch', 'en') }}" @if (app()->getLocale() === 'en') aria-current="true" @endif>EN</a>
                </nav>
                @guest
                    <a class="m-cta" href="{{ route('register') }}">{{ __('Free account') }}</a>
                @endguest
                <button type="button" class="m-menu" aria-expanded="false" aria-controls="site-menu" data-nav-toggle>{{ __('Menu') }}</button>
            </div>
            <nav id="site-menu" hidden>
                @foreach ($nav as $item)
                    <a href="{{ $item['href'] }}">{{ $item['label'] }}</a>
                @endforeach
                @auth
                    <a href="{{ url('/account') }}">{{ __('Cabinet') }}</a>
                @else
                    <a href="{{ route('login') }}">{{ __('Sign in') }}</a>
                    <a href="{{ route('register') }}">{{ __('Free account') }}</a>
                @endauth
            </nav>
        </header>
        <main class="site-main mx-auto w-full min-w-0 max-w-6xl flex-1 px-4 py-12 sm:px-6 md:py-16 {{ !empty($bleed) ? 'is-bleed' : '' }}">
            @if (session('coming_soon_saved'))
                <p class="mb-6 rounded-2xl border border-[#d7e7df] bg-white px-4 py-3 text-sm">{{ __('Thanks. We will write when Pro opens.') }}</p>
            @endif
            {{ $slot }}
        </main>
        <footer class="site-footer">
            <div class="m-wrap">
                <div class="footer-grid">
                    <div>
                        <a class="m-logo" href="{{ url('/') }}"><span class="m-mark" aria-hidden="true"></span> CarAPI</a>
                        <p style="margin-top:10px">{{ __('Vehicle catalog API. The source is drom.ru.') }}</p>
                    </div>
                    <div>
                        <h3>{{ __('Product') }}</h3>
                        <a href="{{ url('/') }}#features">{{ __('Features') }}</a>
                        <a href="{{ url('/') }}#how">{{ __('How it works') }}</a>
                        <a href="{{ route('pricing') }}">{{ __('Pricing') }}</a>
                    </div>
                    <div>
                        <h3>{{ __('Documentation') }}</h3>
                        <a href="{{ url('/demo') }}">{{ __('Demo') }}</a>
                        <a href="{{ url('/guide') }}">{{ __('Documentation') }}</a>
                        <a href="{{ url('/') }}#faq">{{ __('Questions') }}</a>
                    </div>
                    <div>
                        <h3>{{ __('Contact') }}</h3>
                        <a href="mailto:info@socialsave.cc">info@socialsave.cc</a>
                        <a href="{{ url('/privacy') }}">{{ __('Privacy') }}</a>
                        <a href="{{ url('/terms') }}">{{ __('Terms') }}</a>
                        @if ($showBlog)
                            <a href="{{ url('/blog') }}">{{ __('Blog') }}</a>
                        @endif
                    </div>
                </div>
                <p class="legal">© {{ date('Y') }} CarAPI</p>
            </div>
        </footer>
    </div>
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
