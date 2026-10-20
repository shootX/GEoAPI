@php
    $en = app()->getLocale() === 'en';
    $fmt = fn (int $n): string => number_format($n, 0, '.', ' ');
    $max = 1;
    foreach ($days as $day) {
        $max = max($max, (int) $day['used']);
    }
    $quota = $limit > 0 ? min(100, round(($used / $limit) * 100, 1)) : 0;
    $who = trim((string) (auth()->user()->name ?? auth()->user()->email ?? ''));
    $plan = $en ? 'Free' : 'უფასო';
@endphp
<div>
<div class="app">
    <aside class="side" id="side">
        <a class="logo" href="{{ url('/') }}"><span class="mark"></span> CarAPI</a>
        <nav class="nav" aria-label="{{ $en ? 'Cabinet' : 'პანელი' }}">
            <button type="button" data-screen="overview" aria-current="page">{{ $en ? 'Overview' : 'მიმოხილვა' }}</button>
            <button type="button" data-screen="keys">{{ $en ? 'API keys' : 'API გასაღებები' }}</button>
            <button type="button" data-screen="plan">{{ $en ? 'Plan' : 'გეგმა' }}</button>
            <button type="button" data-screen="catalog">{{ $en ? 'Catalog' : 'კატალოგი' }}</button>
        </nav>
    </aside>
    <div class="main">
        <header class="top">
            <button class="btn menu" id="menu" type="button" aria-expanded="false" aria-controls="side">{{ __('Menu') }}</button>
            <h1 id="title">{{ $en ? 'Overview' : 'მიმოხილვა' }}</h1>
            <p class="who">{{ $who }} · {{ $plan }}</p>
        </header>
        <div class="content">
            @if ($unavailable)
                <p class="banner">{{ $en ? 'The project database is temporarily unavailable.' : 'პროექტების ბაზა დროებით მიუწვდომელია.' }}</p>
            @endif
            <section class="screen active" id="overview">
                <div class="tiles">
                    <article class="tile"><span>{{ $en ? 'Used this month' : 'გამოყენებული თვეში' }}</span><b>{{ $fmt($used) }}</b></article>
                    <article class="tile"><span>{{ $en ? 'Remaining' : 'დარჩენილი' }}</span><b>{{ $fmt($remaining) }}</b></article>
                    <article class="tile"><span>{{ $en ? 'Per minute' : 'წუთში' }}</span><b>{{ $perMinute }}</b></article>
                    <article class="tile"><span>{{ $en ? 'Active keys' : 'აქტიური გასაღები' }}</span><b>{{ $activeKeys }}</b></article>
                </div>
                <section class="panel">
                    <h2>{{ $en ? 'Monthly quota' : 'თვიური კვოტა' }}</h2>
                    <div class="quota" aria-label="{{ $fmt($used) }} / {{ $fmt($limit) }}"><span style="width: {{ $quota }}%"></span></div>
                    <p class="muted" style="margin-top:8px">{{ $fmt($used) }} / {{ $fmt($limit) }}. {{ $en ? 'Free plan, per project.' : 'უფასო გეგმა, პროექტზე.' }}</p>
                </section>
                <section class="panel">
                    <h2>{{ $en ? 'Requests in the last 30 days' : 'მოთხოვნები ბოლო 30 დღეში' }}</h2>
                    <svg class="chart" viewBox="0 0 600 180" role="img" aria-label="{{ $en ? 'Requests in the last 30 days' : 'ბოლო 30 დღის მოთხოვნები' }}">
                        @foreach ($days as $index => $day)
                            @php $h = max(4, (int) round(((int) $day['used'] / $max) * 150)); @endphp
                            <rect x="{{ 12 + $index * 19 }}" y="{{ 165 - $h }}" width="14" height="{{ $h }}" rx="3"></rect>
                        @endforeach
                    </svg>
                </section>
                <section class="panel">
                    <h2>{{ $en ? 'Project' : 'პროექტი' }}</h2>
                    @if ($projects !== [])
                        <div class="row" style="margin-bottom:12px">
                            @foreach ($projects as $row)
                                <button type="button" class="btn {{ (int) $row['id'] === (int) ($project['id'] ?? 0) ? 'pine' : '' }}" wire:click="selectProject({{ (int) $row['id'] }})">{{ $row['name'] }}</button>
                            @endforeach
                            @if ($project)
                                <a class="btn" href="{{ url('/account/projects/'.$project['id']) }}">{{ $en ? 'Open desk' : 'სრული გვერდი' }}</a>
                            @endif
                        </div>
                    @endif
                    <form wire:submit="create" class="row">
                        <label>{{ $en ? 'Name' : 'სახელი' }}<input wire:model="name" required maxlength="120"></label>
                        <label>{{ $en ? 'Website' : 'საიტი' }}<input wire:model="website_url" placeholder="https://example.com"></label>
                        <button class="btn pine" type="submit">{{ $en ? 'Create' : 'შექმნა' }}</button>
                    </form>
                    @error('name') <p class="err">{{ $message }}</p> @enderror
                    @error('website_url') <p class="err">{{ $message }}</p> @enderror
                </section>
            </section>

            <section class="screen" id="keys">
                @if (session('revealed_key'))
                    <p class="reveal">{{ session('revealed_key') }}</p>
                @endif
                <form class="panel row" wire:submit="createKey">
                    <label>{{ $en ? 'Name' : 'სახელი' }}<input wire:model="key_name" required maxlength="80" placeholder="{{ $en ? 'Site' : 'საიტი' }}"></label>
                    <label>{{ $en ? 'Type' : 'ტიპი' }}
                        <select wire:model="key_kind">
                            <option value="public">{{ $en ? 'Public' : 'საჯარო' }}</option>
                            <option value="secret">{{ $en ? 'Secret' : 'საიდუმლო' }}</option>
                        </select>
                    </label>
                    <button class="btn pine" type="submit">{{ $en ? 'Create' : 'შექმნა' }}</button>
                </form>
                @error('key_name') <p class="err">{{ $message }}</p> @enderror
                <div class="keys">
                    @if (is_array($widget) && ! empty($widget['public_key']))
                        <article class="key">
                            <div class="key-row"><strong>{{ $en ? 'Widget' : 'ვიჯეტი' }}</strong><span class="pill">{{ $en ? 'Public' : 'საჯარო' }}</span><span class="pill">{{ $en ? 'Active' : 'აქტიური' }}</span></div>
                            <code>{{ $widget['public_key'] }}</code>
                        </article>
                    @endif
                    @foreach ($keys as $key)
                        @php $off = ($key['revoked_at'] ?? null) !== null; @endphp
                        <article class="key">
                            <div class="key-row">
                                <strong>{{ $key['name'] }}</strong>
                                <span class="pill">{{ $en ? 'Secret' : 'საიდუმლო' }}</span>
                                <span class="pill {{ $off ? 'off' : '' }}">{{ $off ? ($en ? 'Revoked' : 'გაუქმებული') : ($en ? 'Active' : 'აქტიური') }}</span>
                            </div>
                            <code>carapi_sk_{{ substr((string) $key['key_selector'], 0, 8) }}••••</code>
                            @unless ($off)
                                <div class="key-row">
                                    <button class="btn" type="button" wire:click="revokeKey({{ (int) $key['id'] }})">{{ $en ? 'Revoke' : 'გაუქმება' }}</button>
                                </div>
                            @endunless
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="screen" id="plan">
                <div class="plans">
                    <article class="plan now">
                        <span class="pill">{{ $en ? 'Current' : 'მიმდინარე' }}</span>
                        <h2>{{ $en ? 'Free' : 'უფასო' }}</h2>
                        <p>0 ₾</p>
                        <ul>
                            <li>{{ $perMinute }} {{ $en ? 'requests a minute' : 'მოთხოვნა წუთში' }}</li>
                            <li>{{ $fmt($limit) }} {{ $en ? 'successful requests a month' : 'წარმატებული მოთხოვნა თვეში' }}</li>
                            <li>{{ $en ? 'Server API and widget' : 'სერვერული API და ვიჯეტი' }}</li>
                        </ul>
                    </article>
                    <article class="plan">
                        <span class="pill wait">{{ __('Soon') }}</span>
                        <h2>Pro</h2>
                        <p>{{ $en ? 'Price soon' : 'ფასი მალე' }}</p>
                        <ul>
                            <li>{{ $en ? 'The same catalog' : 'იგივე კატალოგი' }}</li>
                            <li>{{ $en ? 'A higher limit will be announced' : 'გაზრდილი ლიმიტი გამოცხადდება' }}</li>
                            <li>{{ $en ? 'Not for sale yet' : 'ჯერ არ იყიდება' }}</li>
                        </ul>
                        <form method="post" action="{{ route('coming-soon.store') }}">
                            @csrf
                            <label>{{ __('Email') }}<input type="email" name="email" required value="{{ auth()->user()->email }}"></label>
                            <button class="btn" type="submit" style="margin-top:8px">{{ __('Notify me at launch') }}</button>
                        </form>
                    </article>
                </div>
            </section>

            <section class="screen" id="catalog">
                <div class="grid" id="catalog-grid"></div>
                <p class="muted" id="catalog-note" hidden></p>
            </section>
        </div>
    </div>
</div>
<div class="drawer-back" id="drawer-back"></div>
<aside class="drawer" id="drawer" aria-hidden="true">
    <div class="shot" id="drawer-shot"></div>
    <div class="pad">
        <h2 id="drawer-title"></h2>
        <dl class="specs" id="drawer-specs"></dl>
        <p class="url" id="drawer-url"></p>
        <pre id="drawer-json"></pre>
        <button class="btn" id="drawer-close" type="button">{{ $en ? 'Close' : 'დახურვა' }}</button>
    </div>
</aside>
<script>
    (function () {
        const titles = { overview: @json($en ? 'Overview' : 'მიმოხილვა'), keys: @json($en ? 'API keys' : 'API გასაღებები'), plan: @json($en ? 'Plan' : 'გეგმა'), catalog: @json($en ? 'Catalog' : 'კატალოგი') };
        const side = document.getElementById('side');
        const menu = document.getElementById('menu');
        document.querySelectorAll('.nav button').forEach(function (button) {
            button.addEventListener('click', function () {
                document.querySelectorAll('.nav button').forEach(function (item) { item.removeAttribute('aria-current'); });
                document.querySelectorAll('.screen').forEach(function (screen) { screen.classList.remove('active'); });
                button.setAttribute('aria-current', 'page');
                document.getElementById(button.dataset.screen).classList.add('active');
                document.getElementById('title').textContent = titles[button.dataset.screen];
                side.classList.remove('open');
                menu.setAttribute('aria-expanded', 'false');
            });
        });
        menu.addEventListener('click', function () {
            const open = side.classList.toggle('open');
            menu.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        const car = '<svg viewBox="0 0 160 72" fill="none" stroke="#285A48" stroke-width="2.2"><path d="M14 46h132l-10-18a8 8 0 0 0-7-4H34a8 8 0 0 0-7 4L14 46z"/><path d="M28 46v6M132 46v6"/><circle cx="42" cy="52" r="7" fill="#B0E4CC"/><circle cx="118" cy="52" r="7" fill="#B0E4CC"/></svg><span class="watermark">GEOAPI.GE</span>';
        const grid = document.getElementById('catalog-grid');
        const note = document.getElementById('catalog-note');
        const drawer = document.getElementById('drawer');
        const back = document.getElementById('drawer-back');
        function closeDrawer() {
            drawer.classList.remove('open');
            back.classList.remove('open');
            drawer.setAttribute('aria-hidden', 'true');
        }
        document.getElementById('drawer-close').addEventListener('click', closeDrawer);
        back.addEventListener('click', closeDrawer);
        function openDrawer(item) {
            document.getElementById('drawer-shot').innerHTML = car;
            document.getElementById('drawer-title').textContent = item.brand + ' ' + item.model;
            const specs = [['{{ $en ? 'Brand' : 'მარკა' }}', item.brand], ['{{ $en ? 'Model' : 'მოდელი' }}', item.model]];
            const list = document.getElementById('drawer-specs');
            list.innerHTML = '';
            specs.forEach(function (pair) {
                const wrap = document.createElement('div');
                wrap.innerHTML = '<dt></dt><dd></dd>';
                wrap.querySelector('dt').textContent = pair[0];
                wrap.querySelector('dd').textContent = pair[1];
                list.appendChild(wrap);
            });
            const url = 'GET /api/picker/brands/' + item.brandId + '/models';
            document.getElementById('drawer-url').textContent = url;
            document.getElementById('drawer-json').textContent = JSON.stringify({ data: [{ id: item.modelId, name: item.model }] }, null, 2);
            drawer.classList.add('open');
            back.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
        }
        fetch('/api/picker/brands?page=1&per_page=8', { headers: { Accept: 'application/json' } })
            .then(function (response) { if (!response.ok) throw new Error('fail'); return response.json(); })
            .then(function (payload) {
                const brands = (payload.data || []).slice(0, 4);
                return Promise.all(brands.map(function (brand) {
                    return fetch('/api/picker/brands/' + encodeURIComponent(brand.id) + '/models?page=1&per_page=2', { headers: { Accept: 'application/json' } })
                        .then(function (response) { return response.ok ? response.json() : { data: [] }; })
                        .then(function (models) {
                            return (models.data || []).slice(0, 2).map(function (model) {
                                return { brand: brand.name, brandId: brand.id, model: model.name, modelId: model.id };
                            });
                        });
                }));
            })
            .then(function (groups) {
                groups.flat().slice(0, 6).forEach(function (item) {
                    const button = document.createElement('button');
                    button.className = 'card';
                    button.type = 'button';
                    button.innerHTML = '<div class="shot">' + car + '</div><div class="body"><strong></strong><p class="muted"></p></div>';
                    button.querySelector('strong').textContent = item.brand + ' ' + item.model;
                    button.querySelector('p').textContent = item.brand;
                    button.addEventListener('click', function () { openDrawer(item); });
                    grid.appendChild(button);
                });
            })
            .catch(function () {
                note.hidden = false;
                note.textContent = @json(__('Catalog is temporarily unavailable'));
            });
    })();
</script>
</div>
