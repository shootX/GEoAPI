@php
    $user = auth()->user();
    $name = (string) ($user?->name ?: $user?->email);
@endphp
{!! \Geoapi\Platform\View::adminShellStart($heading, 'blog', $name, (string) $user?->email, false, csrf_field()->toHtml()) !!}
            {{ $slot }}
{!! \Geoapi\Platform\View::adminShellEnd() !!}
