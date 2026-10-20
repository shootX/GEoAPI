<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'CarAPI' }}</title>
    @if (!empty($description))
        <meta name="description" content="{{ $description }}">
    @endif
    <link rel="icon" href="{{ asset('assets/brand/favicon-32-lime.svg') }}" type="image/svg+xml">
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="ka" href="{{ url('/ka') }}">
    <link rel="alternate" hreflang="en" href="{{ url('/en') }}">
    <link rel="alternate" hreflang="x-default" href="{{ url('/') }}">
    <meta property="og:title" content="{{ $title ?? 'CarAPI' }}">
    @if (!empty($description))
        <meta property="og:description" content="{{ $description }}">
        <meta name="twitter:description" content="{{ $description }}">
    @endif
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ asset('assets/brand/favicon-32-lime.svg') }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="{{ $title ?? 'CarAPI' }}">
    <x-electrik::brand-styles />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        article section { border: 1px solid var(--border); border-radius: 0.75rem; padding: 1.25rem; }
        article h1 { font-size: 1.875rem; font-weight: 600; letter-spacing: -0.02em; }
        article h2 { margin-top: 1rem; font-size: 1.05rem; font-weight: 600; }
        article p, article li { margin-top: 0.75rem; color: var(--muted-foreground); }
        article a { text-decoration: underline; text-underline-offset: 4px; }
        article table { width: 100%; margin-top: 0.75rem; border-collapse: collapse; }
        article th, article td { padding: 0.45rem 0.75rem 0.45rem 0; text-align: left; vertical-align: top; }
        article code { font-size: 0.85em; }
    </style>
</head>
<body class="site min-h-screen bg-white text-foreground antialiased">
    <div class="flex min-h-screen flex-col">
        <header class="border-b border-border">
            <div class="mx-auto flex h-16 max-w-6xl items-center gap-2 px-4 sm:px-6">
                <a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2 text-base font-extrabold tracking-tight">
                    <span class="size-5 rounded-md bg-primary"></span>
                    CarAPI
                </a>
                <div class="ml-auto flex items-center gap-2">
                    <nav class="hidden items-center gap-3 text-sm min-[761px]:flex" aria-label="{{ __('Documentation') }}">
                        <a href="{{ url('/guide') }}" class="font-semibold text-muted-foreground hover:text-foreground">{{ __('Documentation') }}</a>
                        @auth
                            <a href="{{ url('/account') }}" class="font-semibold text-muted-foreground hover:text-foreground">{{ __('Cabinet') }}</a>
                        @else
                            <a href="{{ route('login') }}" class="font-semibold text-muted-foreground hover:text-foreground">{{ __('Sign in') }}</a>
                        @endauth
                    </nav>
                    @include('components.locale-switch')
                    @guest
                        <x-slate::button as="a" href="{{ route('register') }}" class="header-cta hero-cta">{{ __('Create account') }}</x-slate::button>
                    @endguest
                    <button
                        type="button"
                        class="inline-flex size-11 items-center justify-center rounded-md border border-border min-[761px]:hidden"
                        aria-expanded="false"
                        aria-controls="site-menu"
                        data-nav-toggle
                        aria-label="{{ __('Menu') }}"
                    >
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">
                            <path d="M3 5h12M3 9h12M3 13h12"/>
                        </svg>
                    </button>
                </div>
            </div>
            <nav id="site-menu" hidden class="border-t border-border px-4 py-2 min-[761px]:hidden">
                <a href="{{ url('/guide') }}" class="block py-3 text-sm font-semibold">{{ __('Documentation') }}</a>
                <a href="{{ route('pricing') }}" class="block py-3 text-sm font-semibold">{{ __('Pricing') }}</a>
                @auth
                    <a href="{{ url('/account') }}" class="block py-3 text-sm font-semibold">{{ __('Cabinet') }}</a>
                @else
                    <a href="{{ route('login') }}" class="block py-3 text-sm font-semibold">{{ __('Sign in') }}</a>
                    <a href="{{ route('register') }}" class="block py-3 text-sm font-semibold">{{ __('Create account') }}</a>
                @endauth
            </nav>
        </header>
        <main class="mx-auto w-full min-w-0 max-w-6xl flex-1 px-4 py-12 sm:px-6 md:py-24">
            {{ $slot }}
        </main>
        <footer class="border-t border-border">
            <div class="mx-auto flex max-w-6xl flex-col gap-4 px-4 py-8 text-sm sm:flex-row sm:items-center sm:justify-between sm:px-6">
                <p class="text-muted-foreground">© {{ date('Y') }} CarAPI</p>
                <nav class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:gap-x-5" aria-label="{{ __('Contact') }}">
                    <a class="accent" href="{{ url('/guide') }}">{{ __('Documentation') }}</a>
                    <a class="accent" href="{{ route('pricing') }}">{{ __('Pricing') }}</a>
                    <a class="accent" href="{{ url('/privacy') }}">{{ __('Privacy') }}</a>
                    <a class="accent" href="{{ url('/terms') }}">{{ __('Terms') }}</a>
                    <a class="accent" href="mailto:info@socialsave.cc">{{ __('Contact') }}</a>
                </nav>
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
