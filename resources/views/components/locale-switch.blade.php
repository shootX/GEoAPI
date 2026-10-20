@php
    $locales = ['ka' => 'ქართული', 'en' => 'English'];
    $current = array_key_exists(app()->getLocale(), $locales) ? app()->getLocale() : 'ka';
@endphp

<details data-locale-switch class="relative">
    <summary
        class="flex cursor-pointer list-none items-center gap-1.5 rounded-md px-1.5 py-1 hover:bg-muted"
        aria-label="{{ $locales[$current] }}"
    >
        <x-locale-flag :code="$current" />
        <span class="text-xs font-semibold tracking-wide">{{ strtoupper($current) }}</span>
    </summary>
    <div class="absolute end-0 z-50 mt-1 flex min-w-24 flex-col gap-1 rounded-lg border border-border bg-card p-1 shadow-lg">
        @foreach ($locales as $code => $label)
            <a
                href="{{ route('locale.switch', $code) }}"
                aria-label="{{ $label }}"
                @if ($current === $code) aria-current="true" @endif
                class="{{ $current === $code ? 'flex items-center gap-1.5 rounded-md px-1.5 py-1 ring-2 ring-primary' : 'flex items-center gap-1.5 rounded-md px-1.5 py-1 hover:bg-muted' }}"
            >
                <x-locale-flag :code="$code" />
                <span class="text-xs font-semibold tracking-wide">{{ strtoupper($code) }}</span>
            </a>
        @endforeach
    </div>
</details>

@once
    <style>
        details[data-locale-switch] > summary::-webkit-details-marker { display: none; }
    </style>
    <script>
        document.addEventListener('click', (event) => {
            document.querySelectorAll('details[data-locale-switch][open]').forEach((node) => {
                if (!node.contains(event.target)) {
                    node.open = false;
                }
            });
        });
        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            document.querySelectorAll('details[data-locale-switch][open]').forEach((node) => {
                node.open = false;
            });
        });
    </script>
@endonce
