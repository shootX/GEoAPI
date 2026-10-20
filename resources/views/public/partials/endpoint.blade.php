@props(['title', 'text', 'path', 'response', 'anchor' => null])
@php
    $url = rtrim((string) config('app.url'), '/').$path;
    $samples = [
        'curl' => "curl ". $url ." \\\n  -H \"Authorization: Bearer carapi_sk_…\"",
        'JavaScript' => "const response = await fetch(".json_encode($url).", {\n  headers: { Authorization: 'Bearer carapi_sk_…' },\n});\nconst data = await response.json();",
        'PHP' => "\$ch = curl_init(".json_encode($url).");\ncurl_setopt_array(\$ch, [\n    CURLOPT_HTTPHEADER => ['Authorization: Bearer carapi_sk_…'],\n    CURLOPT_RETURNTRANSFER => true,\n]);\necho curl_exec(\$ch);",
    ];
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
