@component('components.layouts.public', [
    'title' => __('Forbidden').' — GeoAPI',
    'description' => __('You do not have access to that page.'),
])
    <div style="max-width:560px;margin:40px auto;text-align:center">
        <div class="mono" style="font-size:13px;color:var(--ac);letter-spacing:.08em">403</div>
        <h1 class="h1" style="margin-top:10px;font-size:40px">{{ __('Forbidden') }}</h1>
        <p class="lead" style="margin-top:12px">{{ __('You do not have access to that page.') }}</p>
        <p style="margin-top:28px"><a class="btn b-pri b-lg" href="{{ url('/') }}">{{ __('Back to the home page') }}</a></p>
    </div>
@endcomponent
