@php
    $en = app()->getLocale() === 'en';
    $fmt = fn (int $n): string => number_format($n, 0, '.', ' ');
    $max = 1;
    foreach ($days as $day) {
        $max = max($max, (int) $day['used']);
    }
    $quota = $limit > 0 ? min(100, round(($used / $limit) * 100, 1)) : 0;
    $plan = ! empty($unlimited) ? 'Premium' : ((int) $limit === 2000 ? 'Basic' : 'Free');
    $who = (string) ($project['name'] ?? ($en ? 'Project' : 'პროექტი'));
@endphp
<div>
<div class="app">
    <x-ga.panel-side :spa="true" active="overview" :project="$project['name'] ?? null" :plan="$plan" />
    <div class="main">
        <header class="top">
            <button class="btn menu" id="menu" type="button" aria-expanded="false" aria-controls="side" aria-label="{{ __('Menu') }}"><x-ga.icon name="menu" :size="20" /></button>
            <h1 id="title">{{ $en ? 'Overview' : 'მიმოხილვა' }}</h1>
            <div class="who"><span class="seg hide-m" style="padding:3px"><a href="{{ route('locale.switch', 'ka') }}" class="{{ $en ? '' : 'on' }}">KA</a><a href="{{ route('locale.switch', 'en') }}" class="{{ $en ? 'on' : '' }}">EN</a></span><span class="hide-m">{{ $who }}</span><span class="bd {{ $plan === 'Free' ? 'bd-out' : 'bd-ac' }}">{{ $plan }}</span></div>
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
                <div class="g21">
                <section class="panel">
                    <h2>{{ $en ? 'Requests in the last 30 days' : 'მოთხოვნები ბოლო 30 დღეში' }}</h2>
                    <svg class="chart" viewBox="0 0 600 180" preserveAspectRatio="none" role="img" aria-label="{{ $en ? 'Requests in the last 30 days' : 'ბოლო 30 დღის მოთხოვნები' }}">
                        @foreach ([20, 70, 120] as $y)<line x1="0" x2="600" y1="{{ $y }}" y2="{{ $y }}" stroke="#E4E3EE" stroke-dasharray="3 4"></line>@endforeach
                        @foreach ($days as $index => $day)
                            @php $h = max(4, (int) round(((int) $day['used'] / $max) * 150)); @endphp
                            <rect x="{{ 3 + $index * 20 }}" y="{{ 165 - $h }}" width="14" height="{{ $h }}" rx="3"></rect>
                        @endforeach
                    </svg>
                    <p class="xs mut" style="margin-top:8px">{{ $en ? 'HTTP 200 only · widget and API share one counter' : 'მხოლოდ HTTP 200 · ვიჯეტი და API ერთ მრიცხველზე' }}</p>
                </section>
                <section class="panel">
                    <div class="row" style="justify-content:space-between;margin-bottom:14px"><h2 style="margin:0">{{ $en ? 'Plan' : 'გეგმა' }}</h2><span class="bd {{ $plan === 'Free' ? 'bd-out' : 'bd-ac' }}">{{ $plan }}</span></div>
                    <div class="row" style="justify-content:space-between;margin-bottom:8px"><span style="font-weight:500">{{ $en ? 'Monthly quota' : 'თვიური კვოტა' }}</span><span class="mono sm"><b>{{ $fmt($used) }}</b> <span class="mut">/ {{ ! empty($unlimited) ? '∞' : $fmt($limit) }}</span></span></div>
                    <div class="quota" aria-label="{{ $fmt($used) }} / {{ $fmt($limit) }}"><span style="width: {{ ! empty($unlimited) ? 0 : $quota }}%"></span></div>
                    <p class="muted" style="margin-top:8px">@if (! empty($unlimited)){{ $en ? $fmt($used).' successful requests. Unlimited for this project.' : $fmt($used).' წარმატებული მოთხოვნა. ამ პროექტზე შეუზღუდავია.' }}@else{{ $en ? $fmt($used).' successful requests of '.$fmt($limit).'. '.$plan.' plan, per project.' : $fmt($used).' წარმატებული მოთხოვნა '.$fmt($limit).'-დან. '.$plan.' გეგმა, პროექტზე.' }}@endif</p>
                    <div class="mini-plans">
                        @foreach ([['Free', '100'], ['Basic', '2 000'], ['Premium', '∞']] as [$n, $q])
                            <div class="{{ $plan === $n ? 'on' : '' }}"><b>{{ $n }}</b><span class="xs mut">{{ $q }} / {{ $en ? 'mo' : 'თვე' }}</span></div>
                        @endforeach
                    </div>
                    <a class="btn" style="width:100%;justify-content:center;margin-top:14px" href="{{ route('account.billing') }}">{{ $en ? 'Change the plan' : 'გეგმის შეცვლა' }}</a>
                </section>
                </div>
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
                            <div class="key-row"><button class="btn copy" type="button" data-copy="{{ $widget['public_key'] }}">{{ $en ? 'Copy' : 'კოპირება' }}</button><button class="btn" type="button">{{ $en ? 'Revoke' : 'გაუქმება' }}</button></div>
                        </article>
                    @endif
                    @foreach ($keys as $key)
                        @php
                            $off = ($key['revoked_at'] ?? null) !== null;
                            $secret = (string) ($key['demo_value'] ?? ('geoapi_sk_'.($key['key_selector'] ?? '')));
                            $masked = mb_substr($secret, 0, 12).'••••••••';
                        @endphp
                        <article class="key">
                            <div class="key-row">
                                <strong>{{ $key['name'] }}</strong>
                                <span class="pill">{{ $en ? 'Secret' : 'საიდუმლო' }}</span>
                                <span class="pill {{ $off ? 'off' : '' }}">{{ $off ? ($en ? 'Revoked' : 'გაუქმებული') : ($en ? 'Active' : 'აქტიური') }}</span>
                            </div>
                            <code>{{ $masked }}</code>
                            @unless ($off)
                                <div class="key-row">
                                    <button class="btn copy" type="button" data-copy="{{ $secret }}">{{ $en ? 'Copy' : 'კოპირება' }}</button>
                                    @if (empty($demo))
                                        <button class="btn" type="button" wire:click="revokeKey({{ (int) $key['id'] }})">{{ $en ? 'Revoke' : 'გაუქმება' }}</button>
                                    @else
                                        <button class="btn" type="button">{{ $en ? 'Revoke' : 'გაუქმება' }}</button>
                                    @endif
                                </div>
                            @endunless
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="screen" id="plan">
                <div class="plans">
                    @foreach ([
                        ['Free', '$0', $en ? '100 successful requests a month' : '100 წარმატებული მოთხოვნა თვეში'],
                        ['Basic', '$59', $en ? '2 000 successful requests a month' : '2 000 წარმატებული მოთხოვნა თვეში'],
                        ['Premium', '$99', $en ? 'Unlimited successful requests' : 'შეუზღუდავი წარმატებული მოთხოვნა'],
                    ] as [$name, $price, $quotaText])
                        <article class="plan {{ $plan === $name ? 'now' : '' }}">
                            <div class="row" style="justify-content:space-between"><h2>{{ $name }}</h2>@if ($plan === $name)<span class="bd bd-ac">{{ $en ? 'Current' : 'მიმდინარე' }}</span>@endif</div>
                            <p class="price">{{ $price }} <span class="muted">/ {{ $en ? 'month' : 'თვე' }}</span></p>
                            <ul>
                                <li>{{ $quotaText }}</li>
                                <li>{{ $perMinute }} {{ $en ? 'requests a minute' : 'მოთხოვნა წუთში' }}</li>
                                <li>{{ $en ? 'Server API and widget' : 'სერვერული API და ვიჯეტი' }}</li>
                            </ul>
                        </article>
                    @endforeach
                </div>
                <div class="panel row" style="justify-content:space-between">
                    <p class="muted">{{ $en ? 'The plan applies to this project. Prices include VAT; a year is 12% cheaper.' : 'გეგმა მოქმედებს ამ პროექტზე. ფასი დღგ-ს შეიცავს; წლიური 12%-ით იაფია.' }}</p>
                    <a class="btn pine" href="{{ route('account.billing') }}">{{ $en ? 'Change the plan' : 'გეგმის შეცვლა' }}</a>
                </div>
            </section>

            <section class="screen" id="catalog">
                <div class="grid" id="catalog-grid">
                    @foreach ($cars as $car)
                        <button class="card" type="button" data-car="{{ json_encode($car, JSON_UNESCAPED_UNICODE) }}">
                            <div class="shot"><svg viewBox="0 0 160 72" fill="none" stroke="#4338CA" stroke-width="2.2"><path d="M14 46h132l-10-18a8 8 0 0 0-7-4H34a8 8 0 0 0-7 4L14 46z"/><path d="M28 46v6M132 46v6"/><circle cx="42" cy="52" r="7" fill="#A5A0F0"/><circle cx="118" cy="52" r="7" fill="#A5A0F0"/></svg><span class="watermark">GEOAPI.GE</span></div>
                            <div class="body"><strong>{{ $car['brand'] }} {{ $car['model'] }}</strong><p class="muted">{{ $car['years'] }} · {{ $car['body'] }}</p></div>
                        </button>
                    @endforeach
                </div>
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
        var initial = (location.hash || '').replace('#', '');
        var initialButton = initial ? document.querySelector('.nav button[data-screen="' + initial + '"]') : null;
        if (initialButton) initialButton.click();
        menu.addEventListener('click', function () {
            const open = side.classList.toggle('open');
            menu.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.querySelectorAll('.copy').forEach(function (button) {
            button.addEventListener('click', function () {
                const value = button.getAttribute('data-copy') || '';
                if (navigator.clipboard) navigator.clipboard.writeText(value);
                button.textContent = @json($en ? 'Copied' : 'დაკოპირდა');
            });
        });
        const car = '<svg viewBox="0 0 160 72" fill="none" stroke="#4338CA" stroke-width="2.2"><path d="M14 46h132l-10-18a8 8 0 0 0-7-4H34a8 8 0 0 0-7 4L14 46z"/><path d="M28 46v6M132 46v6"/><circle cx="42" cy="52" r="7" fill="#A5A0F0"/><circle cx="118" cy="52" r="7" fill="#A5A0F0"/></svg><span class="watermark">GEOAPI.GE</span>';
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
            const specs = [
                ['{{ $en ? 'Brand' : 'მარკა' }}', item.brand],
                ['{{ $en ? 'Model' : 'მოდელი' }}', item.model],
                ['{{ $en ? 'Years' : 'წლები' }}', item.years || ''],
                ['{{ $en ? 'Body' : 'ძარა' }}', item.body || ''],
                ['{{ $en ? 'Engine' : 'ძრავი' }}', item.engine || ''],
                ['{{ $en ? 'Power' : 'სიმძლავრე' }}', item.power || '']
            ].filter(function (pair) { return pair[1] !== ''; });
            const list = document.getElementById('drawer-specs');
            list.innerHTML = '';
            specs.forEach(function (pair) {
                const wrap = document.createElement('div');
                wrap.innerHTML = '<dt></dt><dd></dd>';
                wrap.querySelector('dt').textContent = pair[0];
                wrap.querySelector('dd').textContent = pair[1];
                list.appendChild(wrap);
            });
            const slug = item.slug || item.brandId;
            const url = slug ? 'GET /api/v1/brands/' + slug + '/models' : '';
            document.getElementById('drawer-url').textContent = url;
            const modelId = item.id || item.modelId;
            const modelSlug = String(item.model || '').toLowerCase().replace(/\s+/g, '-');
            document.getElementById('drawer-json').textContent = JSON.stringify({
                data: [{ id: String(modelId), slug: modelSlug, name: item.model }],
                meta: { page: 1, per_page: 50, total: 1 }
            }, null, 2);
            drawer.classList.add('open');
            back.classList.add('open');
            drawer.setAttribute('aria-hidden', 'false');
        }
        grid.querySelectorAll('[data-car]').forEach(function (button) {
            button.addEventListener('click', function () {
                openDrawer(JSON.parse(button.getAttribute('data-car')));
            });
        });
        if (grid.children.length) return;
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
