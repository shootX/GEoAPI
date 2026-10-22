@props(['title', 'text', 'path', 'response', 'anchor' => null, 'example' => null])
@php
    $samples = \App\Catalog\PublicSpec::codeSamples($example ?? $path);
    $uid = 'cs-'.\Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(8));
@endphp
<section @if ($anchor) id="{{ $anchor }}" @endif class="doc-sec">
    <div class="doc-text">
        <h2 class="doc-h">{{ $title }}</h2>
        <div class="s-c8779f row">
            <span class="s-cd5979 bd mono">GET</span>
            <span class="s-6ee9de mono">{{ $path }}</span>
        </div>
        <p class="s-a79feb">{{ $text }}</p>
    </div>
    <div class="s-a6dded col">
        <div class="code" data-code>
            <div class="ch" role="tablist" aria-label="{{ __('Code sample') }}">
                @foreach ($samples as $label => $sample)
                    <button type="button" role="tab" id="{{ $uid }}-t{{ $loop->index }}" aria-controls="{{ $uid }}-p{{ $loop->index }}" data-code-tab="{{ $label }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
            @foreach ($samples as $label => $sample)
                <pre role="tabpanel" id="{{ $uid }}-p{{ $loop->index }}" aria-labelledby="{{ $uid }}-t{{ $loop->index }}" tabindex="0" data-code-panel="{{ $label }}" @if (! $loop->first) hidden @endif>{{ $sample }}</pre>
            @endforeach
        </div>
        <div class="code">
            <div class="ch"><span class="on">200</span><span class="s-6d0006">{{ __('Response') }}</span></div>
            <pre tabindex="0" class="s-9687cb">{{ trim($response) }}</pre>
        </div>
    </div>
</section>
