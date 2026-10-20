@component('components.layouts.public', [
    'title' => ($title ?? 'CarAPI'),
])
    <div class="mx-auto w-full max-w-md">
        {{ $slot }}
    </div>
@endcomponent
