<div id="vehicle-picker">
    <div class="vp-bar">
        @foreach ([
            'brand' => 'მარკა',
            'model' => 'მოდელი',
            'year' => 'წელი',
            'generation' => 'თაობა',
            'modification' => 'მოდიფიკაცია',
        ] as $step => $label)
            <div class="vp-field" data-step="{{ $step }}">
                <label class="vp-name" for="vp-{{ $step }}-q">{{ $label }}</label>
                <button type="button" data-vp-trigger disabled>
                    <span data-vp-label class="is-empty">აირჩიეთ</span>
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="M4 6l4 4 4-4"/></svg>
                </button>
                <div data-vp-pop hidden>
                    <input id="vp-{{ $step }}-q" data-vp-q type="search" placeholder="ძებნა" autocomplete="off" aria-label="{{ $label }}, ძებნა">
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
                <div id="vp-empty">ფოტო ჯერ არ არის</div>
                <img id="vp-img" alt="" hidden draggable="false">
                <div id="vp-shield" hidden></div>
                <button type="button" id="vp-flag" hidden aria-label="ფოტო არასწორია">
                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="8" cy="8" r="6.25"/><path d="M8 4.6v4" stroke-linecap="round"/><circle cx="8" cy="11.15" r="0.7" fill="currentColor" stroke="none"/></svg>
                </button>
                <p id="vp-flag-note" hidden>შეტყობინება მიღებულია. ხარვეზი მალე გამოვასწორებთ. მადლობა.</p>
            </div>
            <div class="vp-body">
                <h2 id="vp-title">ავტომობილი</h2>
                <dl id="vp-specs"></dl>
            </div>
        </div>
        <div id="vp-equipment">
            <details><summary>სტანდარტული</summary><div data-eq="standard"></div></details>
            <details><summary>დამატებითი</summary><div data-eq="optional"></div></details>
            <details><summary>არ არის</summary><div data-eq="not_available"></div></details>
        </div>
    </div>
</div>
<style>
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
    #vehicle-picker [data-vp-pop] { position: absolute; left: -1px; right: -1px; top: calc(100% + 8px); z-index: 40; overflow: hidden; border: 1px solid var(--slate-input); border-radius: 0.75rem; background: var(--slate-card); box-shadow: 0 18px 44px rgba(27, 18, 36, 0.12); transform-origin: top center; animation: vp-open 0.18s ease; }
    #vehicle-picker [data-vp-q] { width: 100%; padding: 0.75rem 1rem; border: 0; border-bottom: 1px solid var(--slate-border); background: transparent; color: inherit; font-size: 14px; outline: none; }
    #vehicle-picker [data-vp-hint] { margin: 0; padding: 0.6rem 1rem 0; font-size: 12px; color: var(--slate-muted-foreground); }
    #vehicle-picker [data-vp-list] { display: grid; grid-template-columns: repeat(auto-fill, minmax(14rem, 1fr)); max-height: 18rem; margin: 0; padding: 0.4rem; overflow: auto; list-style: none; }
    #vehicle-picker [data-vp-list] button { display: block; width: 100%; padding: 0.45rem 0.85rem; border: 0; background: transparent; color: inherit; text-align: left; font-size: 14px; cursor: pointer; }
    #vehicle-picker [data-vp-list] button.on,
    #vehicle-picker [data-vp-list] button:hover { background: color-mix(in srgb, var(--slate-primary) 14%, transparent); }
    #vp-status { min-height: 1.25rem; margin: 0.6rem 0 0; font-size: 12px; color: var(--slate-muted-foreground); }
    #vp-card { margin-top: 0.85rem; overflow: hidden; border: 1px solid var(--slate-border); border-radius: 0.75rem; background: var(--slate-card); animation: vp-open 0.22s ease; }
    #vehicle-picker .vp-sheet { display: block; }
    #vehicle-picker .vp-photo { position: relative; border-bottom: 1px solid var(--slate-border); background: #f4f4f5; -webkit-user-select: none; user-select: none; -webkit-touch-callout: none; }
    #vp-flag { position: absolute; right: 0.55rem; bottom: 0.55rem; z-index: 2; display: grid; place-items: center; width: 2rem; height: 2rem; padding: 0; border: 0; border-radius: 999px; background: #d1264a; color: #fff; cursor: pointer; }
    #vp-flag svg { width: 1rem; height: 1rem; }
    #vp-flag:focus-visible { outline: 2px solid #fff; outline-offset: 2px; }
    #vp-flag:disabled { cursor: default; }
    #vp-flag-note { position: absolute; left: 0.55rem; right: 3.2rem; bottom: 0.55rem; z-index: 2; margin: 0; padding: 0.45rem 0.6rem; border-radius: 0.5rem; background: rgba(27, 18, 36, 0.88); color: #fff; font-size: 13px; line-height: 1.35; }
    #vp-empty { display: grid; place-items: center; width: 100%; min-height: 11rem; background: #f4f4f5; color: #52525b; font-size: 14px; }
    #vp-img { display: block; width: 100%; height: auto; background: #f4f4f5; pointer-events: none; -webkit-user-drag: none; user-select: none; -webkit-touch-callout: none; }
    #vp-shield { position: absolute; inset: 0; z-index: 1; }
    #vehicle-picker .vp-body { padding: 1.1rem 1.25rem 1.2rem; }
    #vp-title { margin: 0; font-size: 1.05rem; font-weight: 600; letter-spacing: -0.01em; }
    #vp-specs { display: grid; grid-template-columns: 8rem minmax(0, 1fr) 8rem minmax(0, 1fr); gap: 0.55rem 1.5rem; margin: 0.9rem 0 0; font-size: 14px; }
    #vp-specs dt { color: var(--slate-muted-foreground); }
    #vp-specs dd { min-width: 0; margin: 0; overflow-wrap: break-word; }
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
<script src="/assets/vehicle-picker.js?v=20261020m"></script>
