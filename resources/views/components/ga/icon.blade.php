@props(['name', 'size' => 16])
@php
    $paths = [
        'chk' => '<path d="M4 10.5l4 4 8-9"/>',
        'x' => '<path d="M5 5l10 10M15 5L5 15"/>',
        'copy' => '<rect x="7" y="7" width="10" height="10" rx="2"/><path d="M13 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/>',
        'info' => '<circle cx="10" cy="10" r="7.5"/><path d="M10 9v5M10 6.5v.01"/>',
        'warn' => '<path d="M10 3l8 14H2z"/><path d="M10 8v4M10 14.5v.01"/>',
        'err' => '<circle cx="10" cy="10" r="7.5"/><path d="M7.5 7.5l5 5M12.5 7.5l-5 5"/>',
        'ok' => '<circle cx="10" cy="10" r="7.5"/><path d="M6.5 10.2l2.4 2.4 4.6-5"/>',
        'plus' => '<path d="M10 4v12M4 10h12"/>',
        'search' => '<circle cx="9" cy="9" r="5.5"/><path d="M13 13l4 4"/>',
        'down' => '<path d="M5 8l5 5 5-5"/>',
        'sort' => '<path d="M7 8l3-3 3 3M7 12l3 3 3-3"/>',
        'up' => '<path d="M6 12l4-4 4 4"/>',
        'home' => '<path d="M3 9l7-5.5L17 9v8H3z"/>',
        'fold' => '<path d="M2.5 5.5a1.5 1.5 0 0 1 1.5-1.5h4l2 2h6a1.5 1.5 0 0 1 1.5 1.5v8a1.5 1.5 0 0 1-1.5 1.5H4a1.5 1.5 0 0 1-1.5-1.5z"/>',
        'key' => '<circle cx="6.5" cy="13.5" r="3.5"/><path d="M9 11l7.5-7.5M14 6l2 2M12 8l1.5 1.5"/>',
        'bar' => '<path d="M4 16V9M10 16V4M16 16v-5"/>',
        'card' => '<rect x="2.5" y="4.5" width="15" height="11" rx="2"/><path d="M2.5 8.5h15"/>',
        'gear' => '<circle cx="10" cy="10" r="2.5"/><path d="M10 2.5v2M10 15.5v2M2.5 10h2M15.5 10h2M4.7 4.7l1.4 1.4M13.9 13.9l1.4 1.4M4.7 15.3l1.4-1.4M13.9 6.1l1.4-1.4"/>',
        'user' => '<circle cx="10" cy="7" r="3.5"/><path d="M3.5 17c1-3.5 3.5-5 6.5-5s5.5 1.5 6.5 5"/>',
        'db' => '<ellipse cx="10" cy="5" rx="6.5" ry="2.5"/><path d="M3.5 5v10c0 1.4 2.9 2.5 6.5 2.5s6.5-1.1 6.5-2.5V5M3.5 10c0 1.4 2.9 2.5 6.5 2.5s6.5-1.1 6.5-2.5"/>',
        'doc' => '<path d="M5 2.5h7l3.5 3.5v11.5H5z"/><path d="M12 2.5V6h3.5M7.5 10h5M7.5 13h5"/>',
        'box' => '<path d="M3 6.5L10 3l7 3.5v7L10 17l-7-3.5z"/><path d="M3 6.5L10 10l7-3.5M10 10v7"/>',
        'rot' => '<path d="M16 10a6 6 0 1 1-1.8-4.3M16 3.5v3.5h-3.5"/>',
        'eye' => '<path d="M2 10s3-5.5 8-5.5S18 10 18 10s-3 5.5-8 5.5S2 10 2 10z"/><circle cx="10" cy="10" r="2.3"/>',
        'lock' => '<rect x="4" y="9" width="12" height="8.5" rx="2"/><path d="M6.5 9V6.5a3.5 3.5 0 0 1 7 0V9"/>',
        'glob' => '<circle cx="10" cy="10" r="7.5"/><path d="M2.5 10h15M10 2.5c2.2 2.3 2.2 12.7 0 15M10 2.5c-2.2 2.3-2.2 12.7 0 15"/>',
        'mail' => '<rect x="2.5" y="4.5" width="15" height="11" rx="2"/><path d="M3 5.5l7 5 7-5"/>',
        'log' => '<path d="M4 4h12M4 8h12M4 12h8M4 16h6"/>',
        'menu' => '<path d="M3 6h14M3 10h14M3 14h14"/>',
        'ext' => '<path d="M8 4H4v12h12v-4M11 3h6v6M17 3l-8 8"/>',
        'chev' => '<path d="M8 5l5 5-5 5"/>',
        'trash' => '<path d="M4 6h12M8 6V4h4v2M5.5 6l1 11h7l1-11"/>',
        'mobile' => '<rect x="6" y="2.5" width="8" height="15" rx="2"/>',
        'left' => '<path d="M12 5l-5 5 5 5"/>'
    ];
@endphp
{{-- Sizes are classes in geoapi-inline.css (no style attribute under the CSP); add a new size there. --}}
<svg {{ $attributes->merge(['class' => 'ic ic-'.$size]) }} viewBox="0 0 20 20" aria-hidden="true">{!! $paths[$name] ?? '' !!}</svg>
