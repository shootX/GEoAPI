@component('components.layouts.public', [
    'title' => __('Page not found').' — GeoAPI',
    'description' => __('That address does not exist.'),
])
    <div class="s-b5c03c">
        <div class="s-20c260 mono">404</div>
        <h1 class="s-97768e h1">{{ __('Page not found') }}</h1>
        <p class="s-56f435 lead">{{ __('That address does not exist.') }}</p>
        <p class="s-583f75"><a class="btn b-pri b-lg" href="{{ url('/') }}">{{ __('Back to the home page') }}</a></p>
    </div>
@endcomponent
