@component('components.layouts.public', [
    'title' => __('Vehicle picker').' — CarAPI',
    'description' => __('Choose a brand, model, year, generation, and modification.'),
])
    <h1 class="text-3xl font-extrabold leading-tight">{{ __('Vehicle picker') }}</h1>
    <p class="mt-3 max-w-2xl text-base leading-7">{{ __('Choose a brand, model, year, generation, and modification.') }}</p>
    @include('public.partials.vehicle-picker')
    <details id="vp-response" class="api-fold">
        <summary>{{ __('View the API response') }}</summary>
        <div class="codebox mt-3">
            <pre id="vp-json"></pre>
        </div>
    </details>
    <section class="mt-10">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">{{ __('Embed code') }}</h2>
            <button type="button" class="inline-flex h-11 items-center rounded-md border border-border px-4 text-sm font-semibold" data-copy-target="vp-embed" data-copied="{{ __('Copied') }}">{{ __('Copy') }}</button>
        </div>
        <div class="codebox mt-3">
            <pre id="vp-embed">{{ '<script src="'.rtrim((string) config('app.url'), '/').'/widget/v1/carapi-filter.js"></script>' }}
{{ '<carapi-filter public-key="demo"></carapi-filter>' }}</pre>
        </div>
    </section>
@endcomponent
