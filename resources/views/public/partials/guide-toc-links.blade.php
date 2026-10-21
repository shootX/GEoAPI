@foreach ($before as $id)
    <a href="#{{ $id }}">{{ $pageById[$id]['title'] }}</a>
@endforeach
@foreach ($sections as $index => $section)
    <a href="#guide-{{ $index }}">{{ $section['title'] }}</a>
@endforeach
@foreach ($between as $id)
    <a href="#{{ $id }}">{{ $pageById[$id]['title'] }}</a>
@endforeach
<a href="#guide-widget">{{ __('Widget') }}</a>
@foreach ($after as $id)
    <a href="#{{ $id }}">{{ $pageById[$id]['title'] }}</a>
@endforeach
