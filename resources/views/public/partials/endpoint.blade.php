@props(['title', 'text', 'path', 'response', 'anchor' => null, 'example' => null])
@php
    $samples = \App\Catalog\PublicSpec::codeSamples($example ?? $path);
@endphp
<section @if ($anchor) id="{{ $anchor }}" @endif class="grid min-w-0 items-start gap-6 border-b border-border py-8 lg:grid-cols-2">
    <div class="min-w-0">
        <h2 class="text-lg font-semibold">{{ $title }}</h2>
        <p class="mt-2 font-mono text-sm text-muted-foreground">GET {{ $path }}</p>
        <p class="mt-3 text-sm leading-6">{{ $text }}</p>
    </div>
    <div class="codebox min-w-0" data-code>
        <div class="tabs" role="tablist">
            @foreach ($samples as $label => $sample)
                <button type="button" data-code-tab="{{ $label }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>
        @foreach ($samples as $label => $sample)
            <pre data-code-panel="{{ $label }}" @if (! $loop->first) hidden @endif>{{ $sample }}</pre>
        @endforeach
        <div class="response">
            <p class="px-4 pt-3 text-xs font-semibold text-zinc-400">{{ __('Response') }}</p>
            <pre>{{ trim($response) }}</pre>
        </div>
    </div>
</section>
