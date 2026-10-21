@props(['page'])
<section id="{{ $page['id'] }}" class="max-w-3xl border-b border-border py-8 text-sm leading-6">
    <h2 class="text-lg font-semibold">{{ $page['title'] }}</h2>
    @foreach ($page['paragraphs'] as $paragraph)
        <p class="mt-3">{{ $paragraph }}</p>
    @endforeach
</section>
