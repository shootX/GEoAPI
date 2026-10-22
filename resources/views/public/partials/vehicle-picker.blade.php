<div id="vehicle-picker">
    <div class="vp-bar">
        @foreach ([
            'brand' => __('Brand'),
            'model' => __('Model'),
            'year' => __('Year'),
            'generation' => __('Generation'),
            'modification' => __('Modification'),
        ] as $step => $label)
            <div class="vp-field" data-step="{{ $step }}">
                <label class="vp-name" for="vp-{{ $step }}-q">{{ $label }}</label>
                <button type="button" data-vp-trigger disabled>
                    <span data-vp-label class="is-empty">{{ $step === 'brand' ? __('Choose') : __('Choose a brand first') }}</span>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 6l4 4 4-4"/></svg>
                </button>
                <div data-vp-pop hidden>
                    <input id="vp-{{ $step }}-q" data-vp-q type="search" placeholder="{{ __('Search') }}" autocomplete="off" aria-label="{{ $label }}, {{ __('Search') }}">
                    <p data-vp-hint hidden></p>
                    <ul data-vp-list role="listbox"></ul>
                </div>
            </div>
        @endforeach
    </div>
    <p id="vp-status" aria-live="polite"></p>
    <div id="vp-card" hidden>
        <div class="vp-sheet">
            <div class="vp-photo" id="vp-frame">
                <div id="vp-empty">
                    <svg viewBox="0 0 160 72" fill="none" stroke="#4338CA" stroke-width="2.2" aria-hidden="true"><path d="M14 46h132l-10-18a8 8 0 0 0-7-4H34a8 8 0 0 0-7 4L14 46z"/><path d="M28 46v6M132 46v6"/><circle cx="42" cy="52" r="7" fill="#A5A0F0"/><circle cx="118" cy="52" r="7" fill="#A5A0F0"/></svg>
                    <span>{{ __('No photo yet') }}</span>
                    <span class="vp-mark">GEOAPI.GE</span>
                </div>
                <img id="vp-img" alt="" hidden draggable="false">
                <div id="vp-shield" hidden></div>
                <button type="button" id="vp-flag" hidden aria-label="ფოტო არასწორია">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="8" cy="8" r="6.25"/><path d="M8 4.6v4" stroke-linecap="round"/><circle cx="8" cy="11.15" r="0.7" fill="currentColor" stroke="none"/></svg>
                </button>
                <p id="vp-flag-note" hidden></p>
            </div>
            <div class="vp-body">
                <h2 id="vp-title">{{ app()->getLocale() === 'en' ? 'Vehicle' : 'ავტომობილი' }}</h2>
                <dl id="vp-specs"></dl>
            </div>
        </div>
        <h3 class="vp-eq-h">{{ app()->getLocale() === 'en' ? 'Trim/equipment' : 'კომპლექტაცია' }}</h3>
        <div id="vp-equipment">
            <details><summary>{{ __('Standard') }}</summary><div data-eq="standard"></div></details>
            <details><summary>{{ __('Optional') }}</summary><div data-eq="optional"></div></details>
            <details><summary>{{ __('Not included') }}</summary><div data-eq="not_available"></div></details>
            <details><summary>{{ __('Unknown') }}</summary><div data-eq="unknown"></div></details>
        </div>
    </div>
</div>
<style @nonce>
    #vehicle-picker { margin-top: 1.75rem; }
    #vehicle-picker:has(.open) { position: relative; z-index: 30; }
    #vehicle-picker .vp-bar { position: relative; display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); border: 1px solid var(--slate-border); border-radius: 0.75rem; background: var(--slate-card); }
    #vehicle-picker .vp-field { min-width: 0; padding: 0.7rem 0.9rem 0.8rem; }
    #vehicle-picker .vp-field:first-child { border-radius: 0.7rem 0 0 0.7rem; }
    #vehicle-picker .vp-field:last-child { border-radius: 0 0.7rem 0.7rem 0; }
    #vehicle-picker .vp-field + .vp-field { border-left: 1px solid var(--slate-border); }
    #vehicle-picker .vp-field.open { background: color-mix(in srgb, var(--slate-primary) 8%, var(--slate-card)); }
    #vehicle-picker .vp-name { display: block; margin: 0 0 0.2rem; font-size: 11px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; color: var(--slate-muted-foreground); }
    #vehicle-picker [data-vp-trigger] { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; width: 100%; min-height: 1.6rem; padding: 0; border: 0; background: transparent; color: inherit; text-align: left; font-size: 14px; font-weight: 500; }
    #vehicle-picker [data-vp-trigger]:focus-visible { outline: none; }
    #vehicle-picker .vp-field.open [data-vp-trigger] { color: inherit; }
    #vehicle-picker [data-vp-trigger]:disabled { opacity: 0.45; cursor: not-allowed; }
    #vehicle-picker [data-vp-trigger] svg { width: 0.9rem; height: 0.9rem; flex: none; color: var(--slate-muted-foreground); transition: transform 0.18s ease; }
    #vehicle-picker .vp-field.open [data-vp-trigger] svg { transform: rotate(180deg); }
    #vehicle-picker [data-vp-label] { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #vehicle-picker .is-empty { color: var(--slate-muted-foreground); font-weight: 400; }
    #vehicle-picker [data-vp-pop] { position: absolute; left: -1px; right: -1px; top: calc(100% + 8px); z-index: 40; overflow: hidden; border: 1px solid var(--slate-input); border-radius: 0.75rem; background: var(--slate-card); box-shadow: 0 18px 44px rgba(15, 14, 42, 0.12); transform-origin: top center; animation: vp-open 0.18s ease; }
    #vehicle-picker [data-vp-q] { width: 100%; padding: 0.75rem 1rem; border: 0; border-bottom: 1px solid var(--slate-border); background: transparent; color: inherit; font-size: 14px; outline: none; }
    #vehicle-picker [data-vp-hint] { margin: 0; padding: 0.6rem 1rem 0; font-size: 12px; color: var(--slate-muted-foreground); }
    #vehicle-picker [data-vp-list] { display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); max-height: 18rem; margin: 0; padding: 0.4rem; overflow: auto; list-style: none; }
    #vehicle-picker [data-vp-list] button { display: block; width: 100%; padding: 0.45rem 0.85rem; border: 0; background: transparent; color: inherit; text-align: left; font-size: 14px; cursor: pointer; }
    #vehicle-picker [data-vp-list] button.on,
    #vehicle-picker [data-vp-list] button:hover { background: color-mix(in srgb, var(--slate-primary) 14%, transparent); }
    #vp-status { min-height: 1.25rem; margin: 0.6rem 0 0; font-size: 12px; color: var(--slate-muted-foreground); }
    #vp-card { margin-top: 0.85rem; overflow: hidden; border: 1px solid var(--slate-border); border-radius: 0.75rem; background: var(--slate-card); animation: vp-open 0.22s ease; }
    #vehicle-picker .vp-sheet { display: block; }
    #vehicle-picker .vp-photo { position: relative; border-bottom: 1px solid var(--slate-border); background: #ECEBFB; -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; }
    #vp-flag { position: absolute; right: 0.55rem; bottom: 0.55rem; z-index: 2; display: grid; place-items: center; width: 2rem; height: 2rem; padding: 0; border: 0; border-radius: 999px; background: #B3261E; color: #fff; cursor: pointer; }
    #vp-flag svg { width: 1rem; height: 1rem; }
    #vp-flag:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    #vp-flag:disabled { cursor: default; }
    #vp-flag-note { position: absolute; left: 0.55rem; right: 3.2rem; bottom: 0.55rem; z-index: 2; margin: 0; padding: 0.45rem 0.6rem; border-radius: 0.5rem; background: rgba(15, 14, 42, 0.88); color: #fff; font-size: 13px; line-height: 1.35; }
    #vp-empty { position: relative; display: grid; place-items: center; width: 100%; min-height: 190px; background: linear-gradient(180deg, #F3F2FD 0%, #DEDCF8 100%); color: #4A4866; font-size: 14px; }
    #vp-empty svg { width: 120px; height: 64px; }
    #vp-empty .vp-mark { position: absolute; right: 14px; bottom: 10px; color: rgba(15, 14, 42, 0.42); font-size: 12px; font-weight: 700; letter-spacing: 0.16em; }
    #vehicle-picker.is-busy [data-vp-trigger] { color: transparent; }
    #vehicle-picker.is-busy .vp-field { background-image: linear-gradient(90deg, #EFEEF4 0%, #F7F7FB 50%, #EFEEF4 100%); background-size: 200% 100%; animation: vp-skel 1.1s ease infinite; }
    @keyframes vp-skel { from { background-position: 100% 0; } to { background-position: -100% 0; } }
    #vp-img { display: block; width: 100%; height: auto; background: #ECEBFB; pointer-events: none; -webkit-user-drag: none; user-select: none; -webkit-touch-callout: none; }
    #vp-shield { position: absolute; inset: 0; z-index: 1; }
    #vehicle-picker .vp-body { padding: 1.1rem 1.25rem 1.2rem; }
    #vp-title { margin: 0; font-size: 1.05rem; font-weight: 600; letter-spacing: -0.01em; }
    #vp-specs { display: grid; grid-template-columns: 8rem minmax(0, 1fr) 8rem minmax(0, 1fr); gap: 0.55rem 1.5rem; margin: 0.9rem 0 0; font-size: 14px; }
    #vp-specs dt { color: var(--slate-muted-foreground); }
    #vp-specs dd { min-width: 0; margin: 0; overflow-wrap: break-word; }
    #vehicle-picker .vp-eq-h { margin: 0; padding: 0.85rem 1rem 0; font-size: 15px; font-weight: 600; border-top: 1px solid var(--slate-border); }
    #vp-equipment { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); border-top: 1px solid var(--slate-border); }
    #vp-equipment details { min-width: 0; padding: 0.75rem 1rem 0.9rem; }
    #vp-equipment details + details { border-left: 1px solid var(--slate-border); }
    #vp-equipment summary { cursor: pointer; font-size: 14px; font-weight: 600; }
    #vp-equipment [data-eq] { max-height: 14rem; overflow: auto; padding-top: 0.45rem; font-size: 14px; }
    #vp-equipment p { margin: 0; padding: 0.15rem 0; }
    #vp-equipment .vp-strong { font-weight: 600; }
    #vp-equipment .vp-muted { color: var(--slate-muted-foreground); }
    @keyframes vp-open { from { opacity: 0; transform: translateY(-6px); } to { opacity: 1; transform: none; } }
    @media (max-width: 900px) {
        #vehicle-picker .vp-bar { grid-template-columns: 1fr 1fr; }
        #vehicle-picker .vp-field:nth-child(odd) { border-left: 0; }
        #vehicle-picker .vp-field:nth-child(n + 3) { border-top: 1px solid var(--slate-border); }
        #vehicle-picker .vp-field:first-child,
        #vehicle-picker .vp-field:last-child { border-radius: 0; }
        #vehicle-picker .vp-field:last-child { grid-column: 1 / -1; }
        #vehicle-picker [data-vp-list] { grid-template-columns: 1fr; }
        #vp-specs { grid-template-columns: 7rem minmax(0, 1fr); }
        #vp-equipment { grid-template-columns: 1fr; }
        #vp-equipment details + details { border-left: 0; border-top: 1px solid var(--slate-border); }
    }
    @media (prefers-reduced-motion: reduce) {
        #vehicle-picker [data-vp-pop], #vp-card { animation: none; }
    }
</style>
@php
    $specLocale = app()->getLocale() === 'en' ? 'en' : 'ka';
    $specPairs = [
        'sedan' => ['სედანი', 'Sedan'], 'სედანი' => ['სედანი', 'Sedan'],
        'hatchback' => ['ჰეჩბეკი', 'Hatchback'], 'ჰეჩბეკი' => ['ჰეჩბეკი', 'Hatchback'],
        'wagon' => ['უნივერსალი', 'Wagon'], 'უნივერსალი' => ['უნივერსალი', 'Wagon'], 'estate' => ['უნივერსალი', 'Wagon'],
        'suv' => ['ჯიპი', 'SUV'], 'ჯიპი' => ['ჯიპი', 'SUV'], 'crossover' => ['კროსოვერი', 'Crossover'], 'კროსოვერი' => ['კროსოვერი', 'Crossover'],
        'coupe' => ['კუპე', 'Coupe'], 'კუპე' => ['კუპე', 'Coupe'], 'convertible' => ['კაბრიოლეტი', 'Convertible'], 'კაბრიოლეტი' => ['კაბრიოლეტი', 'Convertible'],
        'pickup' => ['პიკაპი', 'Pickup'], 'პიკაპი' => ['პიკაპი', 'Pickup'], 'van' => ['ფურგონი', 'Van'], 'ფურგონი' => ['ფურგონი', 'Van'],
        'minivan' => ['მინივენი', 'Minivan'], 'მინივენი' => ['მინივენი', 'Minivan'], 'liftback' => ['ლიფტბეკი', 'Liftback'], 'ლიფტბეკი' => ['ლიფტბეკი', 'Liftback'],
        'petrol' => ['ბენზინი', 'Petrol'], 'gasoline' => ['ბენზინი', 'Petrol'], 'ბენზინი' => ['ბენზინი', 'Petrol'],
        'diesel' => ['დიზელი', 'Diesel'], 'დიზელი' => ['დიზელი', 'Diesel'], 'hybrid' => ['ჰიბრიდი', 'Hybrid'], 'ჰიბრიდი' => ['ჰიბრიდი', 'Hybrid'],
        'electric' => ['ელექტრო', 'Electric'], 'ელექტრო' => ['ელექტრო', 'Electric'], 'lpg' => ['გაზი', 'LPG'], 'გაზი' => ['გაზი', 'LPG'],
        'automatic' => ['ავტომატიკა', 'Automatic'], 'ავტომატიკა' => ['ავტომატიკა', 'Automatic'],
        'manual' => ['მექანიკა', 'Manual'], 'მექანიკა' => ['მექანიკა', 'Manual'],
        'robot' => ['რობოტი', 'Robot'], 'რობოტი' => ['რობოტი', 'Robot'], 'cvt' => ['ვარიატორი', 'CVT'], 'ვარიატორი' => ['ვარიატორი', 'CVT'],
        'fwd' => ['წინა წამყვანი', 'FWD'], 'rwd' => ['უკანა წამყვანი', 'RWD'], 'awd' => ['სრული წამყვანი', 'AWD'], 'awd 4x4' => ['სრული წამყვანი', 'AWD'],
        '4wd' => ['სრული წამყვანი', 'AWD'], '4x4' => ['სრული წამყვანი', 'AWD'],
    ];
    $specValues = [];
    foreach ($specPairs as $key => $pair) {
        $specValues[mb_strtolower($key)] = $specLocale === 'en' ? $pair[1] : $pair[0];
    }
@endphp
<script type="application/json" id="vp-copy">{!! json_encode([
    'chooseBrand' => __('Choose a brand'),
    'chooseModel' => __('Choose a model'),
    'chooseYear' => __('Choose a year'),
    'chooseGeneration' => __('Choose a generation'),
    'chooseModification' => __('Choose a modification'),
    'loading' => __('Loading'),
    'unavailable' => __('Catalog is temporarily unavailable'),
    'searchFrom' => $specLocale === 'en' ? 'Search starts at 3 letters' : 'ძებნა იწყება 3 ასოდან',
    'notFound' => $specLocale === 'en' ? 'Nothing found' : 'ვერ მოიძებნა',
    'shown' => $specLocale === 'en' ? 'Showing' : 'ნაჩვენებია',
    'empty' => [
        'model' => $specLocale === 'en' ? 'This brand has no models' : 'ამ მარკას მოდელი არ აქვს',
        'year' => $specLocale === 'en' ? 'This model has no years' : 'ამ მოდელს წელი არ აქვს',
        'generation' => $specLocale === 'en' ? 'This year has no generation' : 'ამ წელს თაობა არ აქვს',
        'modification' => $specLocale === 'en' ? 'This generation has no modification' : 'ამ თაობას მოდიფიკაცია არ აქვს',
        'brand' => $specLocale === 'en' ? 'No brand found' : 'მარკა ვერ მოიძებნა',
    ],
    'labels' => [
        'brand' => __('Brand'), 'model' => __('Model'), 'generation' => __('Generation'), 'years' => $specLocale === 'en' ? 'Years' : 'წლები',
        'body' => $specLocale === 'en' ? 'Body' : 'ძარა', 'engine' => $specLocale === 'en' ? 'Engine' : 'ძრავი',
        'power' => $specLocale === 'en' ? 'Power' : 'სიმძლავრე', 'torque' => $specLocale === 'en' ? 'Torque' : 'ბრუნვა',
        'fuel' => $specLocale === 'en' ? 'Fuel' : 'საწვავი', 'tank' => $specLocale === 'en' ? 'Tank' : 'ავზი',
        'gearbox' => $specLocale === 'en' ? 'Gearbox' : 'კოლოფი', 'drive' => $specLocale === 'en' ? 'Drive' : 'წამყვანი',
        'use' => $specLocale === 'en' ? 'Use' : 'ხარჯი', 'speed' => $specLocale === 'en' ? 'Speed' : 'სიჩქარე',
        'size' => $specLocale === 'en' ? 'Size' : 'ზომები', 'clearance' => $specLocale === 'en' ? 'Clearance' : 'კლირენსი',
        'weight' => $specLocale === 'en' ? 'Weight' : 'წონა', 'seats' => $specLocale === 'en' ? 'Seats' : 'ადგილები',
        'trunk' => $specLocale === 'en' ? 'Trunk' : 'საბარგული', 'brakes' => $specLocale === 'en' ? 'Brakes' : 'მუხრუჭები',
        'suspension' => $specLocale === 'en' ? 'Suspension' : 'საკიდარი', 'tires' => $specLocale === 'en' ? 'Tires' : 'საბურავები',
        'vehicle' => $specLocale === 'en' ? 'Vehicle' : 'ავტომობილი',
        'hp' => $specLocale === 'en' ? 'hp' : 'ცხ.ძ.', 'kw' => $specLocale === 'en' ? 'kW' : 'კვტ',
        'front' => $specLocale === 'en' ? 'front' : 'წინ', 'rear' => $specLocale === 'en' ? 'rear' : 'უკან',
        'city' => $specLocale === 'en' ? 'city' : 'ქალაქი', 'highway' => $specLocale === 'en' ? 'highway' : 'ტრასა', 'mixed' => $specLocale === 'en' ? 'mixed' : 'შერეული',
        'none' => $specLocale === 'en' ? 'None' : 'არ არის', 'other' => $specLocale === 'en' ? 'Other' : 'სხვა',
        'photoWrong' => __('Photo does not match'),
        'reportOk' => $specLocale === 'en' ? 'Report received. We will fix it soon. Thank you.' : 'შეტყობინება მიღებულია. ხარვეზი მალე გამოვასწორებთ. მადლობა.',
        'reportFail' => $specLocale === 'en' ? 'The report was not sent' : 'შეტყობინება ვერ გაიგზავნა',
    ],
    'values' => $specValues,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}</script>
<script src="/assets/vehicle-picker.js?v=20261022f"></script>
