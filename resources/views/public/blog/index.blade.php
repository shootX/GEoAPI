@php
    $en = $locale === 'en';
    $title = ($en ? 'Blog' : 'ბლოგი').' — GeoAPI';
    $description = $en ? 'Notes from GeoAPI.' : 'ჩანაწერები GeoAPI-დან.';
    $seo = [
        'canonical' => url('/'.$locale.'/blog'),
        'hreflang' => [
            'ka' => url('/ka/blog'),
            'en' => url('/en/blog'),
            'x-default' => url('/ka/blog'),
        ],
    ];
@endphp
@component('components.layouts.public', ['title' => $title, 'description' => $description, 'seo' => $seo, 'active' => 'blog', 'bleed' => true])
<section class="s-ed929b sec">
    <div class="wrap">
        <div class="s-33b371 row">
            <div><div class="eyebrow">{{ $en ? 'Blog' : 'ბლოგი' }}</div><h1 class="s-d2c171 h1">{{ $en ? 'Guides and news' : 'გაიდები და სიახლეები' }}</h1></div>
            <a class="btn b-sec hide-m" href="{{ url('/'.$locale.'/blog/feed.xml') }}"><x-ga.icon name="ext" :size="14" />RSS</a>
        </div>
        <nav class="s-90e308 row chips" aria-label="{{ $en ? 'Filters' : 'ფილტრი' }}">
            <a href="{{ url('/'.$locale.'/blog') }}" class="{{ ($category ?? '') === '' && ($tag ?? '') === '' ? 'on' : '' }}">{{ $en ? 'All' : 'ყველა' }}</a>
            @foreach ($categories as $item)
                <a href="{{ url('/'.$locale.'/blog?category='.$item->slug) }}" class="{{ ($category ?? '') === $item->slug ? 'on' : '' }}">{{ $item->label($locale) }}</a>
            @endforeach
            @foreach ($tags as $item)
                <a href="{{ url('/'.$locale.'/blog?tag='.$item->slug) }}" class="{{ ($tag ?? '') === $item->slug ? 'on' : '' }}">#{{ $item->label($locale) }}</a>
            @endforeach
        </nav>
        @if ($posts->isEmpty())
            <p class="mut">{{ $en ? 'No posts yet.' : 'ჩანაწერი ჯერ არ არის.' }}</p>
        @else
            <div class="s-87c641 g3">
                @foreach ($posts as $i => $post)
                    @php
                        $text = $post->translation($locale);
                        $first = $post->categories->first();
                    @endphp
                    <article>
                        <a href="{{ $post->publicUrl($locale) }}" class="cover cover-v{{ $loop->index % 3 }}">
                            @if ($post->coverUrl())
                                <img src="{{ $post->coverUrl() }}" alt="{{ $post->cover_alt ?: $text['title'] }}" loading="lazy">
                            @else
                                GET /api/v1/…
                            @endif
                        </a>
                        <div class="s-13b474 row">
                            @if ($first)<span class="bd bd-ac">{{ $first->label($locale) }}</span>@endif
                            <span class="xs mut">{{ $post->readingMinutes($locale) }} {{ $en ? 'min' : 'წთ' }} · {{ optional($post->publish_at ?? $post->created_at)->toDateString() }}</span>
                        </div>
                        <h2 class="s-d96538"><a href="{{ $post->publicUrl($locale) }}">{{ $text['title'] }}</a></h2>
                        @if ($text['excerpt'] !== '')
                            <p class="s-fe7b49 sm mut">{{ $text['excerpt'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
            <div class="s-e21473">{{ $posts->links() }}</div>
        @endif
    </div>
</section>
@endcomponent
