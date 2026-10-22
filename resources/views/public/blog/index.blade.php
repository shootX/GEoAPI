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
<section class="sec" style="padding-top:56px">
    <div class="wrap">
        <div class="row" style="justify-content:space-between;align-items:flex-end">
            <div><div class="eyebrow">{{ $en ? 'Blog' : 'ბლოგი' }}</div><h1 class="h1" style="margin-top:10px">{{ $en ? 'Guides and news' : 'გაიდები და სიახლეები' }}</h1></div>
            <a class="btn b-sec hide-m" href="{{ url('/'.$locale.'/blog/feed.xml') }}"><x-ga.icon name="ext" :size="14" />RSS</a>
        </div>
        <nav class="row chips" style="margin:28px 0 32px;gap:8px" aria-label="{{ $en ? 'Filters' : 'ფილტრი' }}">
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
            <div class="g3" style="row-gap:40px">
                @foreach ($posts as $i => $post)
                    @php
                        $text = $post->translation($locale);
                        $bg = ['#ECEBFB', '#0F0E2A', '#F7F7FB'][$loop->index % 3];
                        $fg = ['#4338CA', '#A5A0F0', '#4338CA'][$loop->index % 3];
                        $first = $post->categories->first();
                    @endphp
                    <article>
                        <a href="{{ $post->publicUrl($locale) }}" class="cover" style="background:{{ $bg }};color:{{ $fg }}">
                            @if ($post->coverUrl())
                                <img src="{{ $post->coverUrl() }}" alt="{{ $post->cover_alt ?: $text['title'] }}" loading="lazy">
                            @else
                                GET /api/v1/…
                            @endif
                        </a>
                        <div class="row" style="gap:8px;margin-top:14px">
                            @if ($first)<span class="bd bd-ac">{{ $first->label($locale) }}</span>@endif
                            <span class="xs mut">{{ $post->readingMinutes($locale) }} {{ $en ? 'min' : 'წთ' }} · {{ optional($post->publish_at ?? $post->created_at)->toDateString() }}</span>
                        </div>
                        <h2 style="font-size:18px;font-weight:600;line-height:1.4;margin-top:8px"><a href="{{ $post->publicUrl($locale) }}">{{ $text['title'] }}</a></h2>
                        @if ($text['excerpt'] !== '')
                            <p class="sm mut" style="margin-top:6px">{{ $text['excerpt'] }}</p>
                        @endif
                    </article>
                @endforeach
            </div>
            <div style="margin-top:48px">{{ $posts->links() }}</div>
        @endif
    </div>
</section>
@endcomponent
