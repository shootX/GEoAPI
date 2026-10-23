@component('components.layouts.public', [
    'title' => __('Forbidden').' — GeoAPI',
    'description' => __('You do not have access to that page.'),
])
    <div class="s-b5c03c">
        <div class="s-20c260 mono">403</div>
        <h1 class="s-97768e h1">{{ __('Forbidden') }}</h1>
        <p class="s-56f435 lead">{{ __('You do not have access to that page.') }}</p>
        <p class="s-583f75"><a class="btn b-pri b-lg" href="{{ url('/') }}">{{ __('Back to the home page') }}</a></p>
    </div>
@endcomponent
