@component('components.layouts.auth', [
    'title' => ($title ?? 'GeoAPI'),
])
    {{ $slot }}
@endcomponent
