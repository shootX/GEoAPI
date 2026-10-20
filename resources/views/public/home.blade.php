@component('components.layouts.public', [
    'title' => 'CarAPI — '.__('Vehicle data'),
    'description' => __('Get vehicle brands, models, and generations through the API, or add a ready vehicle picker to your website.'),
    'bleed' => true,
])
    <section class="home-hero">
        <div class="mx-auto grid max-w-6xl items-start gap-10 lg:grid-cols-2">
            <div class="max-w-xl">
                <p class="text-sm font-semibold tracking-wide text-[#B0E4CC]">API v1</p>
                <h1 class="hero-title mt-3 text-balance">
                    <span class="lead">{{ __('Vehicle data') }}</span>
                    <span class="phrase">{{ __('API or a ready widget') }}</span>
                </h1>
                <p class="home-lead mt-4 text-base leading-7">{{ __('Get vehicle brands, models, and generations through the API, or add a ready vehicle picker to your website.') }}</p>
                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <x-slate::button as="a" href="{{ route('register') }}" class="hero-cta">{{ __('Create a free account') }}</x-slate::button>
                    <x-slate::button as="a" href="{{ url('/guide') }}" variant="outline" class="hero-cta">{{ __('Documentation') }}</x-slate::button>
                </div>
            </div>
            <div class="codebox">
                <div class="flex items-center justify-between px-3 py-2 text-xs text-[#8fb3a4]">
                    <span>curl</span>
                    <button type="button" class="font-semibold text-[#f4faf7]" data-copy-target="home-example" data-copied="{{ __('Copied') }}">{{ __('Copy') }}</button>
                </div>
                <pre id="home-example">curl {{ rtrim(config('app.url'), '/') }}/api/v1/brands \
  -H "Authorization: Bearer carapi_sk_…"</pre>
            </div>
        </div>
    </section>
    <section class="home-demo" aria-label="{{ __('Vehicle picker') }}">
        <div class="mx-auto max-w-6xl">
            @include('public.partials.vehicle-picker')
        </div>
    </section>
    <section class="home-band">
        <div class="mx-auto grid max-w-6xl gap-4 md:grid-cols-3">
            <x-slate::card>
                <x-slate::card-header>
                    <x-slate::card-title>{{ __('Read it from your server') }}</x-slate::card-title>
                </x-slate::card-header>
                <x-slate::card-content>
                    <p class="text-sm leading-6 text-muted-foreground">{{ __('Build your own screen on top of brands, models, generations, and modifications.') }}</p>
                </x-slate::card-content>
            </x-slate::card>
            <x-slate::card>
                <x-slate::card-header>
                    <x-slate::card-title>{{ __('Drop a picker on your site') }}</x-slate::card-title>
                </x-slate::card-header>
                <x-slate::card-content>
                    <p class="text-sm leading-6 text-muted-foreground">{{ __('Visitors choose a vehicle in the browser. The secret key never leaves your server.') }}</p>
                </x-slate::card-content>
            </x-slate::card>
            <x-slate::card>
                <x-slate::card-header>
                    <x-slate::card-title>{{ __('Start on the free plan') }}</x-slate::card-title>
                </x-slate::card-header>
                <x-slate::card-content>
                    <p class="text-sm leading-6 text-muted-foreground">{{ __('60 requests per minute and 10,000 successful requests per calendar month, per project.') }}</p>
                </x-slate::card-content>
            </x-slate::card>
        </div>
    </section>
    <section class="home-band border-t border-[#d7e7df]">
        <div class="mx-auto max-w-6xl">
            <h2 class="text-lg font-semibold">{{ __('Catalog') }}</h2>
            @if ($brands)
                <dl class="mt-4 grid grid-cols-3 gap-3">
                    <div>
                        <dt class="text-sm text-muted-foreground">{{ __('brands') }}</dt>
                        <dd class="mt-1 text-4xl font-semibold tabular-nums text-[#285A48]">{{ $brands }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">{{ __('models') }}</dt>
                        <dd class="mt-1 text-4xl font-semibold tabular-nums text-[#285A48]">{{ $models }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">{{ __('generations') }}</dt>
                        <dd class="mt-1 text-4xl font-semibold tabular-nums text-[#285A48]">{{ $generations }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-sm text-muted-foreground">{{ __('Generation rows, including market variants. This matches the admin generations total.') }}</p>
                @if ($generationGroups && $generationGroups !== $generations)
                    <p class="mt-1 text-sm text-muted-foreground">{{ __('Chassis groups') }}: {{ $generationGroups }}</p>
                @endif
            @endif
            <p class="mt-4 text-sm">
                <span class="text-muted-foreground">{{ __('Uptime') }}</span>
                {{ $catalogUp ? __('Operational') : __('Unavailable') }}
                @if ($measuredAt)
                    <span class="text-muted-foreground">· {{ __('Updated') }} {{ $measuredAt }}</span>
                @endif
            </p>
        </div>
    </section>
@endcomponent
