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
<div class="sec" style="padding-top:48px">
    <div class="wrap" style="max-width:800px">
        <div class="sm mut row" style="gap:6px"><a href="{{ url('/'.$locale.'/blog') }}">{{ $en ? 'Blog' : 'ბლოგი' }}</a>@if ($post->categories->isNotEmpty())<x-ga.icon name="chev" :size="12" />{{ $post->categories->first()->label($locale) }}@endif</div>
        <h1 class="h1" style="margin-top:14px;font-size:42px">{{ $translation['title'] }}</h1>
        <div class="row sm mut" style="margin:16px 0 28px;gap:10px">
            <span style="width:32px;height:32px;border-radius:50%;background:var(--soft);color:var(--ac);display:grid;place-items:center;font-weight:600;font-size:12px">G</span>
            {{ $en ? 'GeoAPI team' : 'GeoAPI გუნდი' }} · {{ optional($post->publish_at ?? $post->created_at)->toDateString() }} · {{ $post->readingMinutes($locale) }} {{ $en ? 'min read' : 'წთ კითხვა' }}
            @if ($draft)
                · <span class="bd bd-wa">{{ $en ? 'Draft' : 'შავი' }}</span>
            @endif
        </div>
        @if ($post->coverUrl())
            <div class="cover"><img src="{{ $post->coverUrl() }}" alt="{{ $post->cover_alt ?: $translation['title'] }}"></div>
        @endif
        <div class="post-body" style="margin-top:36px">{!! $body !!}</div>
        @if ($post->categories->isNotEmpty() || $post->tags->isNotEmpty())
            <p class="row chips" style="margin-top:24px;gap:8px">
                @foreach ($post->categories as $item)
                    <a href="{{ url('/'.$locale.'/blog?category='.$item->slug) }}">{{ $item->label($locale) }}</a>
                @endforeach
                @foreach ($post->tags as $item)
                    <a href="{{ url('/'.$locale.'/blog?tag='.$item->slug) }}">#{{ $item->label($locale) }}</a>
                @endforeach
            </p>
        @endif
        @if ($related->isNotEmpty())
            <section style="border-top:1px solid var(--line);margin-top:40px;padding-top:28px">
                <h3>{{ $en ? 'Related posts' : 'მსგავსი ჩანაწერები' }}</h3>
                <div class="g2" style="margin-top:16px">
                    @foreach ($related as $item)
                        <a class="fc" href="{{ $item->publicUrl($locale) }}"><span class="xs mut">{{ optional($item->publish_at ?? $item->created_at)->toDateString() }}</span><h4 style="margin-top:4px">{{ $item->translation($locale)['title'] }}</h4></a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endcomponent
