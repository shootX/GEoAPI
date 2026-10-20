@component('components.layouts.public', [
    'title' => __('Forbidden').' — GeoAPI',
    'description' => __('You do not have access to that page.'),
])
    <h1 class="text-3xl font-extrabold">{{ __('Forbidden') }}</h1>
    <p class="mt-3 text-base">{{ __('You do not have access to that page.') }}</p>
    <p class="mt-6"><a class="accent" href="{{ url('/') }}">{{ __('Back to the home page') }}</a></p>
@endcomponent
