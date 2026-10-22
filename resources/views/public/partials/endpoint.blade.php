@props(['title', 'text', 'path', 'response', 'anchor' => null, 'example' => null])
@php
    $samples = \App\Catalog\PublicSpec::codeSamples($example ?? $path);
@endphp
<section @if ($anchor) id="{{ $anchor }}" @endif class="doc-sec">
    <div class="doc-text">
        <h2 class="doc-h">{{ $title }}</h2>
        <div class="row" style="margin-top:14px;border:1px solid var(--line);border-radius:10px;padding:10px 14px;flex-wrap:nowrap;background:#fff">
            <span class="bd mono" style="background:var(--ac);color:#fff;border-radius:5px;font-size:11px;font-weight:600">GET</span>
            <span class="mono" style="font-size:14px;overflow-wrap:anywhere">{{ $path }}</span>
        </div>
        <p style="margin-top:14px;color:var(--ink2)">{{ $text }}</p>
    </div>
    <div class="col" style="gap:12px;min-width:0">
        <div class="code" data-code>
            <div class="ch" role="tablist">
                @foreach ($samples as $label => $sample)
                    <button type="button" data-code-tab="{{ $label }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            @foreach ($samples as $label => $sample)
                <pre data-code-panel="{{ $label }}" @if (! $loop->first) hidden @endif>{{ $sample }}</pre>
            @endforeach
        </div>
        <div class="code">
            <div class="ch"><span class="on">200</span><span style="margin-left:auto">{{ __('Response') }}</span></div>
            <pre style="max-height:420px;overflow:auto">{{ trim($response) }}</pre>
        </div>
    </div>
</section>
