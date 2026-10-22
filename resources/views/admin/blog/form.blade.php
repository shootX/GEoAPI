@php
    $ka = $post->translation('ka');
    $en = $post->translation('en');
    $selectedCategories = $post->exists ? $post->categories->pluck('id')->all() : [];
    $selectedTags = $post->exists ? $post->tags->pluck('id')->all() : [];
    $action = $post->exists ? '/admin/blog/'.$post->id : '/admin/blog';
@endphp
@component('admin.blog.layout', ['heading' => $post->exists ? 'ჩანაწერის რედაქტირება' : 'ახალი ჩანაწერი'])
    <a class="admin-back" href="/admin/blog">ბლოგი</a>
    @if ($errors->any())
        <p class="admin-meta">{{ $errors->first() }}</p>
    @endif
    <form class="admin-filters" method="post" action="{{ $action }}" enctype="multipart/form-data" id="blog-form">
        @csrf
        @if ($post->exists) @method('PUT') @endif
        <label>სტატუსი
            <select name="status" id="blog-status">
                @foreach (['draft' => 'შავი', 'published' => 'გამოქვეყნებული', 'scheduled' => 'დაგეგმილი'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $post->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>გამოქვეყნების დრო
            <input type="datetime-local" name="publish_at" value="{{ old('publish_at', optional($post->publish_at)->format('Y-m-d\TH:i')) }}">
        </label>
        <label>სლაგი
            <input name="slug" id="blog-slug" value="{{ old('slug', $post->slug) }}" pattern="[a-z0-9]+(?:-[a-z0-9]+)*" maxlength="120">
        </label>
        <button type="button" id="blog-slug-fill">სლაგის შევსება</button>
        <label>სათაური ქართულად<input name="translations[ka][title]" id="title-ka" value="{{ old('translations.ka.title', $ka['title']) }}" maxlength="200"></label>
        <label>ტექსტი ქართულად (Markdown)<textarea name="translations[ka][body]" rows="12">{{ old('translations.ka.body', $ka['body']) }}</textarea></label>
        <label>მოკლე ტექსტი ქართულად<input name="translations[ka][excerpt]" value="{{ old('translations.ka.excerpt', $ka['excerpt']) }}" maxlength="500"></label>
        <label>SEO სათაური ქართულად
            <input name="translations[ka][meta_title]" id="meta-title-ka" value="{{ old('translations.ka.meta_title', $ka['meta_title']) }}" maxlength="70">
            <small id="hint-title-ka"></small>
        </label>
        <label>SEO აღწერა ქართულად
            <input name="translations[ka][meta_description]" id="meta-desc-ka" value="{{ old('translations.ka.meta_description', $ka['meta_description']) }}" maxlength="200">
            <small id="hint-desc-ka"></small>
        </label>
        <label>სათაური ინგლისურად<input name="translations[en][title]" id="title-en" value="{{ old('translations.en.title', $en['title']) }}" maxlength="200"></label>
        <label>ტექსტი ინგლისურად (Markdown)<textarea name="translations[en][body]" rows="12">{{ old('translations.en.body', $en['body']) }}</textarea></label>
        <label>მოკლე ტექსტი ინგლისურად<input name="translations[en][excerpt]" value="{{ old('translations.en.excerpt', $en['excerpt']) }}" maxlength="500"></label>
        <label>SEO სათაური ინგლისურად
            <input name="translations[en][meta_title]" id="meta-title-en" value="{{ old('translations.en.meta_title', $en['meta_title']) }}" maxlength="70">
            <small id="hint-title-en"></small>
        </label>
        <label>SEO აღწერა ინგლისურად
            <input name="translations[en][meta_description]" id="meta-desc-en" value="{{ old('translations.en.meta_description', $en['meta_description']) }}" maxlength="200">
            <small id="hint-desc-en"></small>
        </label>
        <fieldset>
            <legend>კატეგორიები</legend>
            @foreach ($categories as $item)
                <label><input type="checkbox" name="category_ids[]" value="{{ $item->id }}" @checked(in_array($item->id, old('category_ids', $selectedCategories)))> {{ $item->label('ka') }}</label>
            @endforeach
        </fieldset>
        <fieldset>
            <legend>თეგები</legend>
            @foreach ($tags as $item)
                <label><input type="checkbox" name="tag_ids[]" value="{{ $item->id }}" @checked(in_array($item->id, old('tag_ids', $selectedTags)))> {{ $item->label('ka') }}</label>
            @endforeach
        </fieldset>
        <label>ყდის სურათი<input type="file" name="file" accept="image/*"></label>
        <label>ყდის alt<input name="cover_alt" value="{{ old('cover_alt', $post->cover_alt) }}" maxlength="200"></label>
        @if ($post->coverUrl())
            <p><img src="{{ $post->coverUrl() }}" alt="" width="240"></p>
        @endif
        <button type="submit">შენახვა</button>
    </form>
    <section class="admin-group" id="snippet">
        <h2>Google-ის ესკიზი</h2>
        <p id="snippet-title" style="color:#1a0dab;font-size:18px;margin:0"></p>
        <p id="snippet-url" style="color:#006621;font-size:13px;margin:2px 0"></p>
        <p id="snippet-desc" style="color:#545454;font-size:13px;margin:0"></p>
    </section>
    <script @nonce>
        (function () {
            var map = {'ა':'a','ბ':'b','გ':'g','დ':'d','ე':'e','ვ':'v','ზ':'z','თ':'t','ი':'i','კ':'k','ლ':'l','მ':'m','ნ':'n','ო':'o','პ':'p','ჟ':'zh','რ':'r','ს':'s','ტ':'t','უ':'u','ფ':'p','ქ':'k','ღ':'gh','ყ':'q','შ':'sh','ჩ':'ch','ც':'ts','ძ':'dz','წ':'ts','ჭ':'ch','ხ':'kh','ჯ':'j','ჰ':'h'};
            function slugify(value) {
                var lower = String(value || '').toLowerCase();
                var out = '';
                for (var i = 0; i < lower.length; i++) out += map[lower[i]] || lower[i];
                return out.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 120);
            }
            var slug = document.getElementById('blog-slug');
            document.getElementById('blog-slug-fill').addEventListener('click', function () {
                var title = document.getElementById('title-en').value || document.getElementById('title-ka').value;
                slug.value = slugify(title) || 'post';
            });
            function hint(input, node, min, max) {
                var n = input.value.length;
                node.textContent = n + ' სიმბოლო. რეკომენდებულია ' + min + '–' + max + '.';
            }
            function paint() {
                var title = document.getElementById('meta-title-ka').value || document.getElementById('title-ka').value || document.getElementById('title-en').value;
                var desc = document.getElementById('meta-desc-ka').value || document.getElementById('meta-desc-en').value;
                document.getElementById('snippet-title').textContent = title || 'სათაური';
                document.getElementById('snippet-url').textContent = location.origin + '/ka/blog/' + (slug.value || 'slug');
                document.getElementById('snippet-desc').textContent = desc || 'აღწერა';
                hint(document.getElementById('meta-title-ka'), document.getElementById('hint-title-ka'), 50, 60);
                hint(document.getElementById('meta-title-en'), document.getElementById('hint-title-en'), 50, 60);
                hint(document.getElementById('meta-desc-ka'), document.getElementById('hint-desc-ka'), 120, 160);
                hint(document.getElementById('meta-desc-en'), document.getElementById('hint-desc-en'), 120, 160);
            }
            document.getElementById('blog-form').addEventListener('input', paint);
            paint();
        })();
    </script>
@endcomponent
