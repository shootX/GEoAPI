@props(['page'])
<section id="{{ $page['id'] }}" class="doc-prose">
    <h2 class="doc-h">{{ $page['title'] }}</h2>
    @foreach ($page['paragraphs'] as $paragraph)
        <p>{{ $paragraph }}</p>
    @endforeach
</section>
