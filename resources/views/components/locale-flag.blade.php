@props(['code'])

@if ($code === 'ka')
    <svg {{ $attributes->merge(['class' => 'h-4 w-6 shrink-0 overflow-hidden rounded-[2px] ring-1 ring-black/10']) }} width="24" height="16" viewBox="0 0 30 20" aria-hidden="true">
        <rect width="30" height="20" fill="#fff"/>
        <rect x="12.5" width="5" height="20" fill="#ff0000"/>
        <rect y="7.5" width="30" height="5" fill="#ff0000"/>
        <path fill="#ff0000" d="M5.2 2.2h1.1v3.2H5.2zM3.4 3.2h4.7v1.1H3.4zM21.2 2.2h1.1v3.2h-1.1zM19.4 3.2h4.7v1.1h-4.7zM5.2 14.6h1.1v3.2H5.2zM3.4 15.6h4.7v1.1H3.4zM21.2 14.6h1.1v3.2h-1.1zM19.4 15.6h4.7v1.1h-4.7z"/>
    </svg>
@else
    @php($clip = 'en-flag-'.uniqid())
    <svg {{ $attributes->merge(['class' => 'h-4 w-6 shrink-0 overflow-hidden rounded-[2px] ring-1 ring-black/10']) }} width="24" height="16" viewBox="0 0 60 30" aria-hidden="true">
        <clipPath id="{{ $clip }}"><path d="M0 0h60v30H0z"/></clipPath>
        <g clip-path="url(#{{ $clip }})">
            <path d="M0 0h60v30H0z" fill="#012169"/>
            <path d="M0 0l60 30M60 0L0 30" stroke="#fff" stroke-width="6"/>
            <path d="M0 0l60 30M60 0L0 30" stroke="#C8102E" stroke-width="4"/>
            <path d="M30 0v30M0 15h60" stroke="#fff" stroke-width="10"/>
            <path d="M30 0v30M0 15h60" stroke="#C8102E" stroke-width="6"/>
        </g>
    </svg>
@endif
