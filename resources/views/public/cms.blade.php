@component('components.layouts.public', ['title' => $page->title.' — GeoAPI', 'description' => $page->title])
<article class="space-y-6 text-sm leading-6">
    <section class="panel">
        {!! $page->body !!}
    </section>
</article>
@endcomponent
