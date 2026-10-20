@component('components.layouts.public', [
    'title' => 'CarAPI — '.__('Vehicle data'),
    'description' => __('Get vehicle brands, models, and generations through the API, or add a ready vehicle picker to your website.'),
])
    <div class="max-w-3xl">
        <p class="text-sm font-medium text-muted-foreground">API v1</p>
        <h1 class="hero-title mt-3">
            <span class="lead">{{ __('Vehicle data') }}</span>
            <span class="phrase">{{ __('API or a ready widget') }}</span>
        </h1>
        <p class="mt-4 max-w-2xl text-base leading-7">{{ __('Get vehicle brands, models, and generations through the API, or add a ready vehicle picker to your website.') }}</p>
        <div class="mt-6 flex flex-wrap items-center gap-3">
            <x-slate::button as="a" href="{{ route('register') }}" class="hero-cta">{{ __('Create a free account') }}</x-slate::button>
            <x-slate::button as="a" href="{{ url('/guide') }}" variant="outline" class="hero-cta">{{ __('Documentation') }}</x-slate::button>
        </div>
    </div>
    @include('public.partials.vehicle-picker')
    <div class="mt-16 grid gap-4 md:grid-cols-3">
        <x-slate::card>
            <x-slate::card-header>
                <x-slate::card-title>{{ __('Server API') }}</x-slate::card-title>
            </x-slate::card-header>
            <x-slate::card-content>
                <p class="text-sm text-muted-foreground">{{ __('GET /api/v1. Authorization uses a Bearer header and a carapi_sk_ key.') }}</p>
            </x-slate::card-content>
        </x-slate::card>
        <x-slate::card>
            <x-slate::card-header>
                <x-slate::card-title>{{ __('Widget') }}</x-slate::card-title>
            </x-slate::card-header>
            <x-slate::card-content>
                <p class="text-sm text-muted-foreground">{{ __('A carapi_pk_ key and an allowed origin. It does not open another product for buying cars.') }}</p>
            </x-slate::card-content>
        </x-slate::card>
        <x-slate::card>
            <x-slate::card-header>
                <x-slate::card-title>{{ __('Limit') }}</x-slate::card-title>
            </x-slate::card-header>
            <x-slate::card-content>
                <p class="text-sm text-muted-foreground">{{ __('60 requests per minute and 10,000 successful requests per calendar month, per project.') }}</p>
            </x-slate::card-content>
        </x-slate::card>
    </div>
    <section class="mt-16 border-t border-border pt-10">
        <h2 class="text-lg font-semibold">{{ __('Catalog') }}</h2>
        @if ($brands)
            <dl class="mt-4 grid grid-cols-3 gap-3">
                <div>
                    <dt class="text-sm text-muted-foreground">{{ __('brands') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $brands }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">{{ __('models') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $models }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">{{ __('generations') }}</dt>
                    <dd class="mt-1 text-2xl font-semibold tabular-nums">{{ $generations }}</dd>
                </div>
            </dl>
        @endif
        <p class="mt-4 text-sm">
            <span class="text-muted-foreground">{{ __('Uptime') }}</span>
            {{ $catalogUp ? __('Operational') : __('Unavailable') }}
        </p>
        <div class="codebox mt-4">
            <div class="flex items-center justify-between px-3 py-2 text-xs text-zinc-400">
                <span>curl</span>
                <button type="button" class="font-semibold text-zinc-100" data-copy-target="home-example" data-copied="{{ __('Copied') }}">{{ __('Copy') }}</button>
            </div>
            <pre id="home-example">curl {{ rtrim(config('app.url'), '/') }}/api/v1/brands \
  -H "Authorization: Bearer carapi_sk_…"</pre>
        </div>
    </section>
@endcomponent
