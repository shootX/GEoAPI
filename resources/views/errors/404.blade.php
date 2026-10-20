@component('components.layouts.public', [
    'title' => __('Page not found').' — CarAPI',
    'description' => __('That address does not exist.'),
])
    <h1 class="text-3xl font-extrabold">{{ __('Page not found') }}</h1>
    <p class="mt-3 text-base">{{ __('That address does not exist.') }}</p>
    <p class="mt-6"><a class="accent" href="{{ url('/') }}">{{ __('Back to the home page') }}</a></p>
@endcomponent
