@component('components.layouts.public', [
    'title' => ($title ?? 'GeoAPI'),
])
    <div class="mx-auto w-full max-w-md">
        {{ $slot }}
    </div>
@endcomponent
