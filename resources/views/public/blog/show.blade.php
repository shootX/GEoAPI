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
])
    <article>
        <p class="text-sm"><a href="{{ url('/'.$locale.'/blog') }}">{{ $en ? 'Blog' : 'ბლოგი' }}</a></p>
        <h1 class="mt-3">{{ $translation['title'] }}</h1>
        <p class="mt-2 text-sm text-muted-foreground">
            {{ optional($post->publish_at ?? $post->created_at)->toDateString() }}
            · {{ $post->readingMinutes($locale) }} {{ $en ? 'min read' : 'წუთი' }}
            @if ($draft)
                · {{ $en ? 'Draft' : 'შავი' }}
            @endif
        </p>
        @if ($post->coverUrl())
            <p class="mt-6"><img src="{{ $post->coverUrl() }}" alt="{{ $post->cover_alt ?: $translation['title'] }}"></p>
        @endif
        <div class="mt-6">{!! $body !!}</div>
        @if ($post->categories->isNotEmpty() || $post->tags->isNotEmpty())
            <p class="mt-6 text-sm">
                @foreach ($post->categories as $item)
                    <a href="{{ url('/'.$locale.'/blog?category='.$item->slug) }}">{{ $item->label($locale) }}</a>
                @endforeach
                @foreach ($post->tags as $item)
                    <a href="{{ url('/'.$locale.'/blog?tag='.$item->slug) }}">#{{ $item->label($locale) }}</a>
                @endforeach
            </p>
        @endif
    </article>
    @if ($related->isNotEmpty())
        <section class="mt-10">
            <h2 class="text-lg font-semibold">{{ $en ? 'Related posts' : 'მსგავსი ჩანაწერები' }}</h2>
            <ul class="mt-3">
                @foreach ($related as $item)
                    <li><a href="{{ $item->publicUrl($locale) }}">{{ $item->translation($locale)['title'] }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endcomponent
