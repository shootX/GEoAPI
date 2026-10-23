@php
    $en = $locale === 'en';
    $headline = $translation['meta_title'] !== '' ? $translation['meta_title'] : $translation['title'];
    $summary = $translation['meta_description'] !== '' ? $translation['meta_description'] : $translation['excerpt'];
    $image = $post->coverUrl() ?: asset('assets/brand/apple-touch-icon.png');
    $hreflang = [];
    foreach (['ka', 'en'] as $code) {
        if ($post->hasLocale($code)) {
            $hreflang[$code] = $post->publicUrl($code);
        }
    }
    $hreflang['x-default'] = $post->hasLocale('ka') ? $post->publicUrl('ka') : $post->publicUrl($locale);
    $canonical = $post->publicUrl($locale);
    $published = ($post->publish_at ?? $post->created_at)->toAtomString();
    $jsonLd = [
        [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $translation['title'],
            'description' => $summary,
            'datePublished' => $published,
            'dateModified' => $post->updated_at->toAtomString(),
            'inLanguage' => $locale,
            'mainEntityOfPage' => $canonical,
            'image' => $image,
            'author' => ['@type' => 'Organization', 'name' => 'GeoAPI'],
            'publisher' => ['@type' => 'Organization', 'name' => 'GeoAPI'],
        ],
        [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'GeoAPI', 'item' => url('/')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $en ? 'Blog' : 'ბლოგი', 'item' => url('/'.$locale.'/blog')],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $translation['title'], 'item' => $canonical],
            ],
        ],
    ];
@endphp
@component('components.layouts.public', [
    'title' => $headline.' — GeoAPI',
    'description' => $summary,
    'robots' => $draft ? 'noindex, nofollow' : null,
    'seo' => [
        'canonical' => $canonical,
        'hreflang' => $hreflang,
        'image' => $image,
        'type' => 'article',
        'card' => $post->coverUrl() ? 'summary_large_image' : 'summary',
    ],
    'active' => 'blog',
    'bleed' => true,
])
<div class="s-bae40f sec">
    <div class="s-3f7efa wrap">
        <div class="s-f0abc1 sm mut row"><a href="{{ url('/'.$locale.'/blog') }}">{{ $en ? 'Blog' : 'ბლოგი' }}</a>@if ($post->categories->isNotEmpty())<x-ga.icon name="chev" :size="12" />{{ $post->categories->first()->label($locale) }}@endif</div>
        <h1 class="s-1d914c h1">{{ $translation['title'] }}</h1>
        <div class="s-1e64d0 row sm mut">
            <span class="s-1100f9">G</span>
            {{ $en ? 'GeoAPI team' : 'GeoAPI გუნდი' }} · {{ optional($post->publish_at ?? $post->created_at)->toDateString() }} · {{ $post->readingMinutes($locale) }} {{ $en ? 'min read' : 'წთ კითხვა' }}
            @if ($draft)
                · <span class="bd bd-wa">{{ $en ? 'Draft' : 'შავი' }}</span>
            @endif
        </div>
        @if ($post->coverUrl())
            <div class="cover"><img src="{{ $post->coverUrl() }}" alt="{{ $post->cover_alt ?: $translation['title'] }}"></div>
        @endif
        <div class="s-d34d92 post-body">{!! $body !!}</div>
        @if ($post->categories->isNotEmpty() || $post->tags->isNotEmpty())
            <p class="s-196905 row chips">
                @foreach ($post->categories as $item)
                    <a href="{{ url('/'.$locale.'/blog?category='.$item->slug) }}">{{ $item->label($locale) }}</a>
                @endforeach
                @foreach ($post->tags as $item)
                    <a href="{{ url('/'.$locale.'/blog?tag='.$item->slug) }}">#{{ $item->label($locale) }}</a>
                @endforeach
            </p>
        @endif
        @if ($related->isNotEmpty())
            <section class="s-f0aa63">
                <h3>{{ $en ? 'Related posts' : 'მსგავსი ჩანაწერები' }}</h3>
                <div class="s-1b0f49 g2">
                    @foreach ($related as $item)
                        <a class="fc" href="{{ $item->publicUrl($locale) }}"><span class="xs mut">{{ optional($item->publish_at ?? $item->created_at)->toDateString() }}</span><h4 class="s-96ad60">{{ $item->translation($locale)['title'] }}</h4></a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endcomponent
