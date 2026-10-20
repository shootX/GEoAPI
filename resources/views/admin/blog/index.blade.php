@component('admin.blog.layout', ['heading' => 'ბლოგი'])
    <p class="admin-meta"><a href="/admin/blog/create">ახალი ჩანაწერი</a></p>
    <table class="admin-table">
        <thead><tr><th>სათაური</th><th>სლაგი</th><th>სტატუსი</th><th></th></tr></thead>
        <tbody>
        @forelse ($posts as $post)
            <tr>
                <td data-label="სათაური"><a href="/admin/blog/{{ $post->id }}/edit">{{ $post->translation('ka')['title'] ?: $post->translation('en')['title'] }}</a></td>
                <td data-label="სლაგი">{{ $post->slug }}</td>
                <td data-label="სტატუსი">{{ $post->status }}</td>
                <td>
                    <form method="post" action="/admin/blog/{{ $post->id }}">
                        @csrf @method('DELETE')
                        <button type="submit">წაშლა</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="4"><p class="admin-empty">ჩანაწერი არ არის.</p></td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="admin-pager">{{ $posts->links() }}</div>

    <div class="admin-split">
        <section>
            <h2>კატეგორიები</h2>
            <ul>
                @foreach ($categories as $item)
                    <li>{{ $item->label('ka') }} ({{ $item->slug }})
                        <form method="post" action="/admin/blog/categories/{{ $item->id }}">@csrf @method('DELETE')<button type="submit">წაშლა</button></form>
                    </li>
                @endforeach
            </ul>
            <form class="admin-filters" method="post" action="/admin/blog/categories">
                @csrf
                <label>სახელი ქართულად<input name="name[ka]" maxlength="80"></label>
                <label>სახელი ინგლისურად<input name="name[en]" maxlength="80"></label>
                <label>სლაგი<input name="slug" pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></label>
                <button type="submit">დამატება</button>
            </form>
        </section>
        <section>
            <h2>თეგები</h2>
            <ul>
                @foreach ($tags as $item)
                    <li>{{ $item->label('ka') }} ({{ $item->slug }})
                        <form method="post" action="/admin/blog/tags/{{ $item->id }}">@csrf @method('DELETE')<button type="submit">წაშლა</button></form>
                    </li>
                @endforeach
            </ul>
            <form class="admin-filters" method="post" action="/admin/blog/tags">
                @csrf
                <label>სახელი ქართულად<input name="name[ka]" maxlength="80"></label>
                <label>სახელი ინგლისურად<input name="name[en]" maxlength="80"></label>
                <label>სლაგი<input name="slug" pattern="[a-z0-9]+(?:-[a-z0-9]+)*"></label>
                <button type="submit">დამატება</button>
            </form>
        </section>
    </div>
@endcomponent
