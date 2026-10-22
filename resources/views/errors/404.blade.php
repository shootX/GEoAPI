@component('components.layouts.public', [
    'title' => __('Page not found').' — GeoAPI',
    'description' => __('That address does not exist.'),
])
    <div style="max-width:560px;margin:40px auto;text-align:center">
        <div class="mono" style="font-size:13px;color:var(--ac);letter-spacing:.08em">404</div>
        <h1 class="h1" style="margin-top:10px;font-size:40px">{{ __('Page not found') }}</h1>
        <p class="lead" style="margin-top:12px">{{ __('That address does not exist.') }}</p>
        <p style="margin-top:28px"><a class="btn b-pri b-lg" href="{{ url('/') }}">{{ __('Back to the home page') }}</a></p>
    </div>
@endcomponent
