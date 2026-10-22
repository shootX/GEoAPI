@component('components.layouts.public', ['title' => $page->title.' — GeoAPI', 'description' => $page->title])
<article>
    @if ($page->updated_at)
        <p class="note">{{ app()->getLocale() === 'en' ? 'Updated' : 'განახლდა' }} {{ $page->updated_at->toDateString() }}</p>
    @endif
    <section class="panel">
        {!! $page->body !!}
    </section>
</article>
@endcomponent
