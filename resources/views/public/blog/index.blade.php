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
@component('components.layouts.public', ['title' => $title, 'description' => $description, 'seo' => $seo])
    <h1 class="text-3xl font-extrabold">{{ $en ? 'Blog' : 'ბლოგი' }}</h1>
    <p class="mt-3 text-sm leading-6 text-muted-foreground">
        <a href="{{ url('/'.$locale.'/blog/feed.xml') }}">RSS</a>
    </p>
    <nav class="mt-6 flex flex-wrap gap-3 text-sm" aria-label="{{ $en ? 'Filters' : 'ფილტრი' }}">
        <a href="{{ url('/'.$locale.'/blog') }}">{{ $en ? 'All' : 'ყველა' }}</a>
        @foreach ($categories as $item)
            <a href="{{ url('/'.$locale.'/blog?category='.$item->slug) }}">{{ $item->label($locale) }}</a>
        @endforeach
        @foreach ($tags as $item)
            <a href="{{ url('/'.$locale.'/blog?tag='.$item->slug) }}">#{{ $item->label($locale) }}</a>
        @endforeach
    </nav>
    @if ($posts->isEmpty())
        <p class="mt-8 text-sm text-muted-foreground">{{ $en ? 'No posts yet.' : 'ჩანაწერი ჯერ არ არის.' }}</p>
    @else
        <div class="mt-8 divide-y divide-border border-y border-border">
            @foreach ($posts as $post)
                @php $text = $post->translation($locale); @endphp
                <article class="py-6">
                    <h2 class="text-xl font-semibold"><a href="{{ $post->publicUrl($locale) }}">{{ $text['title'] }}</a></h2>
                    @if ($text['excerpt'] !== '')
                        <p class="mt-2 text-sm leading-6">{{ $text['excerpt'] }}</p>
                    @endif
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ optional($post->publish_at ?? $post->created_at)->toDateString() }}
                        · {{ $post->readingMinutes($locale) }} {{ $en ? 'min' : 'წთ' }}
                    </p>
                </article>
            @endforeach
        </div>
        <div class="mt-6">{{ $posts->links() }}</div>
    @endif
@endcomponent
