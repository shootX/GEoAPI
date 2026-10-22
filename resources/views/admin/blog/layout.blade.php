@php
    $user = auth()->user();
    $name = (string) ($user?->name ?: $user?->email);
@endphp
{!! \Carapi\Platform\View::adminShellStart($heading, 'blog', $name, (string) $user?->email, false, csrf_field()->toHtml()) !!}
            {{ $slot }}
{!! \Carapi\Platform\View::adminShellEnd() !!}
