@php
    $en = app()->getLocale() === 'en';
    $t = $en ? [
        'nav1' => 'Features', 'nav2' => 'How it works', 'nav3' => 'Pricing', 'nav4' => 'Questions', 'nav5' => 'Contact',
        'account' => 'Free account', 'menu' => 'Menu',
        'eyebrow' => 'Catalog API',
        'h1' => 'Vehicle data, API or a ready widget',
        'lede' => 'Get brands, models, generations, and modifications from your server, or place a ready picker on the site.',
        'create' => 'Create a free account', 'docs' => 'Documentation',
        'key' => 'Key', 'car' => 'Car', 'gen' => 'Generation XV70', 'engine' => 'Engine 2.5',
        'try' => 'Try it here', 'flow' => 'Brand → model → generation → modification',
        'brands' => 'brands', 'models' => 'models', 'generations' => 'generations', 'modifications' => 'modifications', 'photos' => 'photos',
        'brand' => 'Brand', 'model' => 'Model', 'generation' => 'Generation', 'modification' => 'Modification',
        'featuresEyebrow' => 'Features', 'featuresTitle' => 'A catalog you build a screen on',
        'featuresLede' => 'The server API and the widget use one catalog. The secret key never reaches the browser.',
        'f1t' => 'Read it from your server', 'f1p' => 'Build your own screen on top of brands, models, generations, and modifications.',
        'f2t' => 'Drop a picker on your site', 'f2p' => 'Visitors choose a vehicle in the browser. The secret key never leaves your server.',
        'f3t' => 'Stable identifiers', 'f3p' => 'slug and id, pages with <b>page</b> and <b>per_page</b>. The response is JSON.',
        'f4t' => 'Start on the free plan', 'f4p' => '60 requests a minute and 10,000 successful requests a month, per project.',
        'howEyebrow' => 'How it works', 'howTitle' => 'Three steps to the catalog',
        'howLede' => 'An account, a key, and one chain: brand, model, generation, modification.',
        'c1' => 'The server key stays on your server.',
        'c2' => 'The widget uses the public key and passes the choice to your page.',
        'c3' => 'Generation rows include market variants. The total is 22 367.',
        's1t' => 'Project and key', 's1p' => 'A free account, a confirmed email, and one project.',
        's2t' => 'Request', 's2p' => 'GET /api/v1/brands, then models, generations, and modifications.',
        's3t' => 'Your screen or the widget', 's3p' => 'Your own interface, or a ready picker in the site form.',
        'priceEyebrow' => 'Pricing', 'priceTitle' => 'Free now, Pro soon',
        'priceLede' => 'Pro is not for sale yet. This page does not open a payment.',
        'now' => 'Now', 'free' => 'Free', 'soon' => 'Soon', 'proAmount' => 'Price soon',
        'p1' => '60 requests a minute', 'p2' => '10,000 successful requests a month', 'p3' => 'Server API and widget', 'p4' => 'Limit per project',
        'q1' => 'The same catalog', 'q2' => 'A higher limit will be announced', 'q3' => 'Not for sale yet', 'q4' => 'The price will be announced separately',
        'notify' => 'We will let you know',
        'storiesEyebrow' => 'In teams', 'storiesTitle' => 'Where GeoAPI fits',
        'storiesLede' => 'These are examples of how a catalog is used. They are not quotes from real customers.',
        'st1t' => 'A filter on a listing', 'st1p' => 'Four steps from brand to generation. A custom filter stands on that chain.', 'st1w' => 'Frontend', 'st1s' => 'Own screen', 'st1a' => 'F',
        'st2t' => 'A widget in a form', 'st2p' => 'The picker is on the site. The public key is in the browser. The secret stays on the server.', 'st2w' => 'Product', 'st2s' => 'Widget', 'st2a' => 'P',
        'st3t' => 'A familiar catalog', 'st3p' => 'Brand and generation follow one catalog structure.', 'st3w' => 'Catalog', 'st3s' => 'Data', 'st3a' => 'C',
        'faqEyebrow' => 'Questions', 'faqTitle' => 'Short answers',
        'faq1q' => 'Where does the data come from?', 'faq1a' => 'Collected by the Global auto industry and is its property.',
        'faq2q' => 'What is in the free plan?', 'faq2a' => '60 requests a minute and 10,000 successful requests in a calendar month, per project.',
        'faq3q' => 'Where does the secret key stay?', 'faq3a' => 'On the server request. The widget uses the public key and does not send the session cookie.',
        'faq4q' => 'Is Pro for sale?', 'faq4a' => 'No. Pro is coming later. This page does not open a payment.',
        'faq5q' => 'Does the API have a photo?', 'faq5a' => 'The current server API has no photo. The modification list and the detail are there.',
        'contactEyebrow' => 'Contact', 'contactTitle' => 'Write to us',
        'contactLede' => 'This form does not send a message. The live address is hello@geoapi.ge.',
        'name' => 'Name', 'email' => 'Email', 'message' => 'Message', 'send' => 'Send',
        'asideTitle' => 'Start with the catalog',
        'asideP' => '314 brands, 4 710 models, 22 367 generations. The account is free, and Pro is not for sale yet.',
        'emailLine' => 'Email', 'server' => 'The server is in Germany.',
        'foot' => 'Vehicle catalog API.',
        'product' => 'Product', 'doc' => 'Documentation', 'picker' => 'Picker', 'addresses' => 'Addresses', 'write' => 'Write to us',
        'legal' => 'Mockup. This file does not change the live site layout.',
        'pickBrand' => 'Choose a brand', 'pickPrev' => 'Choose the previous step first',
    ] : [
        'nav1' => 'შესაძლებლობები', 'nav2' => 'როგორ მუშაობს', 'nav3' => 'ფასები', 'nav4' => 'კითხვები', 'nav5' => 'კონტაქტი',
        'account' => 'უფასო ანგარიში', 'menu' => 'მენიუ',
        'eyebrow' => 'კატალოგის API',
        'h1' => 'ავტომობილების მონაცემები, API ან მზა ვიჯეტი',
        'lede' => 'მიიღეთ მარკები, მოდელები, თაობები და მოდიფიკაციები თქვენი სერვერიდან, ან ჩასვით მზა არჩევანი საიტზე.',
        'create' => 'უფასო ანგარიშის შექმნა', 'docs' => 'დოკუმენტაცია',
        'key' => 'გასაღები', 'car' => 'მანქანა', 'gen' => 'თაობა XV70', 'engine' => 'ძრავი 2.5',
        'try' => 'სცადეთ აქ', 'flow' => 'მარკა → მოდელი → თაობა → მოდიფიკაცია',
        'brands' => 'მარკა', 'models' => 'მოდელი', 'generations' => 'თაობა', 'modifications' => 'მოდიფიკაცია', 'photos' => 'ფოტო',
        'brand' => 'მარკა', 'model' => 'მოდელი', 'generation' => 'თაობა', 'modification' => 'მოდიფიკაცია',
        'featuresEyebrow' => 'შესაძლებლობები', 'featuresTitle' => 'კატალოგი, რომელზეც ეკრანს აწყობთ',
        'featuresLede' => 'სერვერული API და ვიჯეტი ერთ კატალოგს იყენებს. საიდუმლო გასაღები ბრაუზერში არ გადის.',
        'f1t' => 'წაიკითხეთ თქვენი სერვერიდან', 'f1p' => 'ააწყვეთ საკუთარი ეკრანი მარკებზე, მოდელებზე, თაობებსა და მოდიფიკაციებზე.',
        'f2t' => 'ჩასვით არჩევანი საიტზე', 'f2p' => 'სტუმარი ავტომობილს ბრაუზერში ირჩევს. საიდუმლო გასაღები სერვერს არ ტოვებს.',
        'f3t' => 'სუფთა იდენტიფიკატორები', 'f3p' => 'slug და id, გვერდები <b>page</b> და <b>per_page</b>. პასუხი JSON-ია.',
        'f4t' => 'დაიწყეთ უფასო გეგმით', 'f4p' => 'წუთში 60 მოთხოვნა და თვეში 10 000 წარმატებული მოთხოვნა, პროექტზე.',
        'howEyebrow' => 'როგორ მუშაობს', 'howTitle' => 'სამი ნაბიჯი კატალოგამდე',
        'howLede' => 'ანგარიში, გასაღები და ერთი ჯაჭვი: მარკა, მოდელი, თაობა, მოდიფიკაცია.',
        'c1' => 'სერვერული გასაღები მხოლოდ თქვენს სერვერზე რჩება.',
        'c2' => 'ვიჯეტი საჯარო გასაღებს იყენებს და არჩევანს თქვენს გვერდს გადასცემს.',
        'c3' => 'თაობის ჩანაწერები ბაზრის ვარიანტებსაც ითვლის. ჯამი 22 367-ია.',
        's1t' => 'პროექტი და გასაღები', 's1p' => 'უფასო ანგარიში, დადასტურებული ელფოსტა და ერთი პროექტი.',
        's2t' => 'მოთხოვნა', 's2p' => 'GET /api/v1/brands, შემდეგ მოდელები, თაობები და მოდიფიკაციები.',
        's3t' => 'თქვენი ეკრანი ან ვიჯეტი', 's3p' => 'საკუთარი ინტერფეისი, ან მზა არჩევანი საიტის ფორმაში.',
        'priceEyebrow' => 'ფასები', 'priceTitle' => 'უფასო ახლა, Pro მალე',
        'priceLede' => 'Pro ჯერ არ იყიდება. გადახდა ამ გვერდზე არ იხსნება.',
        'now' => 'ახლა', 'free' => 'უფასო', 'soon' => 'მალე', 'proAmount' => 'ფასი მალე',
        'p1' => '60 მოთხოვნა წუთში', 'p2' => '10 000 წარმატებული მოთხოვნა თვეში', 'p3' => 'სერვერული API და ვიჯეტი', 'p4' => 'ლიმიტი პროექტზე',
        'q1' => 'იგივე კატალოგი', 'q2' => 'გაზრდილი ლიმიტი გამოცხადდება', 'q3' => 'ჯერ არ იყიდება', 'q4' => 'ფასი ცალკე გამოცხადდება',
        'notify' => 'შეგატყობინებთ',
        'storiesEyebrow' => 'გუნდებში', 'storiesTitle' => 'სად ჯდება GeoAPI',
        'storiesLede' => 'ეს მაგალითებია, როგორ იყენებენ კატალოგს. რეალური მომხმარებლის ციტატა არ არის.',
        'st1t' => 'ფილტრი განცხადებაზე', 'st1p' => 'მარკიდან თაობამდე ოთხი საფეხურია. საკუთარი ფილტრი ამ ჯაჭვზე დგას.', 'st1w' => 'ფრონტენდი', 'st1s' => 'საკუთარი ეკრანი', 'st1a' => 'ფ',
        'st2t' => 'ვიჯეტი ფორმაში', 'st2p' => 'არჩევანი საიტზე ჩანს. საჯარო გასაღები ბრაუზერშია, საიდუმლო სერვერზე რჩება.', 'st2w' => 'პროდუქტი', 'st2s' => 'ვიჯეტი', 'st2a' => 'პ',
        'st3t' => 'ნაცნობი კატალოგი', 'st3p' => 'მარკა და თაობა ერთი კატალოგის სტრუქტურას მიჰყვება.', 'st3w' => 'კატალოგი', 'st3s' => 'მონაცემები', 'st3a' => 'კ',
        'faqEyebrow' => 'კითხვები', 'faqTitle' => 'მოკლე პასუხები',
        'faq1q' => 'საიდანაა მონაცემები?', 'faq1a' => 'შეგროვებულია Global auto industry-ს მიერ და წარმოადგენს მის საკუთრებას.',
        'faq2q' => 'რა შედის უფასო გეგმაში?', 'faq2a' => 'წუთში 60 მოთხოვნა და კალენდარულ თვეში 10 000 წარმატებული მოთხოვნა, თითო პროექტზე.',
        'faq3q' => 'სად რჩება საიდუმლო გასაღები?', 'faq3a' => 'სერვერულ მოთხოვნაზე. ვიჯეტი საჯარო გასაღებს იყენებს და სესიის cookie-ს არ აგზავნის.',
        'faq4q' => 'Pro უკვე იყიდება?', 'faq4a' => 'არა. Pro მალე გამოჩნდება. ამ გვერდზე გადახდა არ იხსნება.',
        'faq5q' => 'ფოტო აქვს API-ს?', 'faq5a' => 'მიმდინარე სერვერულ API-ს ფოტო არ აქვს. მოდიფიკაციების სია და დეტალი არის.',
        'contactEyebrow' => 'კონტაქტი', 'contactTitle' => 'მოგვწერეთ',
        'contactLede' => 'ფორმა ამ მოკაპში წერილს არ აგზავნის. ცოცხალი მისამართია hello@geoapi.ge.',
        'name' => 'სახელი', 'email' => 'ელფოსტა', 'message' => 'შეტყობინება', 'send' => 'გაგზავნა',
        'asideTitle' => 'დაიწყეთ კატალოგით',
        'asideP' => '314 მარკა, 4 710 მოდელი, 22 367 თაობა. ანგარიში უფასოა, Pro კი ჯერ არ იყიდება.',
        'emailLine' => 'ელფოსტა', 'server' => 'სერვერი გერმანიაშია.',
        'foot' => 'ავტომობილების კატალოგის API.',
        'product' => 'პროდუქტი', 'doc' => 'დოკუმენტაცია', 'picker' => 'ამომრჩევი', 'addresses' => 'მისამართები', 'write' => 'მოგვწერეთ',
        'legal' => 'მოკაპი. ცოცხალი საიტის განლაგება ამ ფაილით არ იცვლება.',
        'pickBrand' => 'აირჩიეთ მარკა', 'pickPrev' => 'ჯერ წინა საფეხური აირჩიეთ',
    ];
    $check = '<svg width="18" height="18" viewBox="0 0 18 18" fill="none" stroke="#408A71" stroke-width="1.8"><circle cx="9" cy="9" r="7"/><path d="M5.5 9.2l2.2 2.2 4.8-5"/></svg>';
@endphp
@component('components.layouts.public', [
    'title' => 'GeoAPI — '.($en ? 'Vehicle data' : 'ავტომობილების მონაცემები'),
    'description' => $en ? 'GeoAPI is a vehicle catalog API.' : 'GeoAPI არის ავტომობილების კატალოგის API.',
    'home' => true,
])
<div class="mk">
    <header class="site-header">
        <div class="wrap">
            <a class="logo" href="#top"><img src="{{ asset('assets/brand/geoapi-lockup-light.png') }}" alt="geoapi.ge" height="14"></a>
            <nav class="nav" id="nav" aria-label="{{ $en ? 'Main' : 'მთავარი' }}">
                <a href="#features">{{ $t['nav1'] }}</a>
                <a href="#how">{{ $t['nav2'] }}</a>
                <a href="#pricing">{{ $t['nav3'] }}</a>
                <a href="#faq">{{ $t['nav4'] }}</a>
                <a href="#contact">{{ $t['nav5'] }}</a>
            </nav>
            <div class="header-tools">
                <a class="header-cta" href="#pricing">{{ $t['account'] }}</a>
                <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="nav">{{ $t['menu'] }}</button>
            </div>
        </div>
    </header>
    <main id="top">
        <section class="hero">
            <div class="wrap hero-grid">
                <div>
                    <p class="eyebrow">{{ $t['eyebrow'] }}</p>
                    <h1>{{ $t['h1'] }}</h1>
                    <p>{{ $t['lede'] }}</p>
                    <div class="actions">
                        <a class="btn btn-mint" href="#pricing">{{ $t['create'] }}</a>
                        <a class="btn btn-ghost" href="#features">{{ $t['docs'] }}</a>
                    </div>
                </div>
                <div class="stage" aria-hidden="true">
                    <svg class="wires" viewBox="0 0 560 480" preserveAspectRatio="none">
                        <path d="M70 190 C 120 190, 140 200, 160 230"/>
                        <path d="M230 28 C 240 70, 230 90, 230 120"/>
                        <path d="M500 70 C 430 90, 400 120, 390 150"/>
                        <path d="M500 330 C 440 300, 410 260, 390 230"/>
                        <path d="M300 450 C 300 390, 300 340, 290 300"/>
                        <path d="M70 400 C 120 370, 140 330, 165 290"/>
                    </svg>
                    <article class="code-card">
                        <div class="code-bar"><i class="dot"></i><i class="dot"></i><i class="dot"></i><span>GET /api/v1/brands/toyota/models</span></div>
                        <pre>{
  <span class="k">"data"</span>: [
    { <span class="k">"slug"</span>: <span class="s">"camry"</span>, <span class="k">"name"</span>: <span class="s">"Camry"</span> },
    { <span class="k">"slug"</span>: <span class="s">"corolla"</span>, <span class="k">"name"</span>: <span class="s">"Corolla"</span> }
  ]
}</pre>
                    </article>
                    <div class="chips">
                        <div class="chip chip-key"><svg viewBox="0 0 24 24" fill="none" stroke="#285A48" stroke-width="1.7"><circle cx="8" cy="12" r="3"/><path d="M11 12h10l-2 2 2 2h-3"/></svg>{{ $t['key'] }}</div>
                        <div class="chip chip-brand"><svg viewBox="0 0 24 24" fill="none" stroke="#285A48" stroke-width="1.7"><circle cx="12" cy="12" r="8"/><path d="M8 14c1.2-2 6.8-2 8 0"/><path d="M9 10h.01M15 10h.01"/></svg>Toyota</div>
                        <div class="chip chip-car"><svg viewBox="0 0 24 24" fill="none" stroke="#285A48" stroke-width="1.7"><path d="M4 15h16l-1.2-4.2a2 2 0 0 0-1.9-1.3H7.1a2 2 0 0 0-1.9 1.3L4 15z"/><path d="M6 15v2M18 15v2"/><circle cx="7.5" cy="16.5" r="1"/><circle cx="16.5" cy="16.5" r="1"/></svg>{{ $t['car'] }}</div>
                        <div class="chip chip-gen"><svg viewBox="0 0 24 24" fill="none" stroke="#285A48" stroke-width="1.7"><path d="M5 7h14M5 12h14M5 17h9"/></svg>{{ $t['gen'] }}</div>
                        <div class="chip chip-engine"><svg viewBox="0 0 24 24" fill="none" stroke="#285A48" stroke-width="1.7"><rect x="5" y="8" width="10" height="8" rx="1"/><path d="M15 10h3l1 2-1 2h-3M8 8V5M11 8V5"/></svg>{{ $t['engine'] }}</div>
                        <div class="chip chip-json"><svg viewBox="0 0 24 24" fill="none" stroke="#285A48" stroke-width="1.7"><path d="M9 5c-2 0-3 1-3 3v2c0 1-1 2-2 2 1 0 2 1 2 2v2c0 2 1 3 3 3M15 5c2 0 3 1 3 3v2c0 1 1 2 2 2-1 0-2 1-2 2v2c0 2-1 3-3 3"/></svg>JSON</div>
                    </div>
                </div>
            </div>
        </section>
        <div class="demo-wrap">
            <div class="wrap">
                <section class="demo" aria-labelledby="demo-title">
                    <div class="demo-head">
                        <div>
                            <p class="eyebrow">{{ $t['try'] }}</p>
                            <h2 id="demo-title" style="font-size:28px">{{ $t['flow'] }}</h2>
                        </div>
                        @if ($catalogUp ?? false)
                            <div class="counts">
                                <div><b>{{ $brands }}</b> {{ $t['brands'] }}</div>
                                <div><b>{{ $models }}</b> {{ $t['models'] }}</div>
                                <div><b>{{ $generations }}</b> {{ $t['generations'] }}</div>
                                @if (! empty($modifications))
                                    <div><b>{{ $modifications }}</b> {{ $t['modifications'] }}</div>
                                @endif
                                @if (! empty($photos))
                                    <div><b>{{ $photos }}</b> {{ $t['photos'] }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                    @include('public.partials.vehicle-picker')
                </section>
            </div>
        </div>
        <section class="section" id="features">
            <div class="wrap">
                <div class="section-intro center">
                    <p class="eyebrow" style="justify-content:center">{{ $t['featuresEyebrow'] }}</p>
                    <h2>{{ $t['featuresTitle'] }}</h2>
                    <p class="lede">{{ $t['featuresLede'] }}</p>
                </div>
                <div class="features">
                    <article class="card"><div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#285A48" stroke-width="1.7"><rect x="4" y="4" width="16" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg></div><h3>{{ $t['f1t'] }}</h3><p>{{ $t['f1p'] }}</p></article>
                    <article class="card"><div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#285A48" stroke-width="1.7"><path d="M5 7h14M5 12h9M5 17h6"/></svg></div><h3>{{ $t['f2t'] }}</h3><p>{{ $t['f2p'] }}</p></article>
                    <article class="card"><div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#285A48" stroke-width="1.7"><path d="M8 7h11M8 12h11M8 17h11"/><circle cx="4.5" cy="7" r="1"/><circle cx="4.5" cy="12" r="1"/><circle cx="4.5" cy="17" r="1"/></svg></div><h3>{{ $t['f3t'] }}</h3><p>{!! $t['f3p'] !!}</p></article>
                    <article class="card"><div class="icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="#285A48" stroke-width="1.7"><circle cx="12" cy="12" r="8"/><path d="M12 8v5l3 2"/></svg></div><h3>{{ $t['f4t'] }}</h3><p>{{ $t['f4p'] }}</p></article>
                </div>
            </div>
        </section>
        <section class="section wash" id="how">
            <div class="wrap split">
                <div>
                    <p class="eyebrow">{{ $t['howEyebrow'] }}</p>
                    <h2>{{ $t['howTitle'] }}</h2>
                    <p class="lede" style="margin-top:10px">{{ $t['howLede'] }}</p>
                    <ul class="checks">
                        <li>{!! $check !!} {{ $t['c1'] }}</li>
                        <li>{!! $check !!} {{ $t['c2'] }}</li>
                        <li>{!! $check !!} {{ $t['c3'] }}</li>
                    </ul>
                </div>
                <div class="steps">
                    <article class="step"><div class="num">01</div><div><h3>{{ $t['s1t'] }}</h3><p>{{ $t['s1p'] }}</p></div></article>
                    <article class="step"><div class="num">02</div><div><h3>{{ $t['s2t'] }}</h3><p>{{ $t['s2p'] }}</p></div></article>
                    <article class="step"><div class="num">03</div><div><h3>{{ $t['s3t'] }}</h3><p>{{ $t['s3p'] }}</p></div></article>
                </div>
            </div>
        </section>
        <section class="section" id="pricing">
            <div class="wrap">
                <div class="section-intro center">
                    <p class="eyebrow" style="justify-content:center">{{ $t['priceEyebrow'] }}</p>
                    <h2>{{ $t['priceTitle'] }}</h2>
                    <p class="lede">{{ $t['priceLede'] }}</p>
                </div>
                <div class="prices">
                    <article class="price featured">
                        <span class="tag">{{ $t['now'] }}</span>
                        <h3>{{ $t['free'] }}</h3>
                        <div class="amount">0 ₾</div>
                        <ul>
                            <li>{{ $t['p1'] }}</li>
                            <li>{{ $t['p2'] }}</li>
                            <li>{{ $t['p3'] }}</li>
                            <li>{{ $t['p4'] }}</li>
                        </ul>
                        <a class="btn" href="#contact">{{ $t['create'] }}</a>
                    </article>
                    <article class="price">
                        <span class="tag" style="background:#e7f6ef;color:#285A48">{{ $t['soon'] }}</span>
                        <h3>Pro</h3>
                        <div class="amount">{{ $t['proAmount'] }}</div>
                        <ul>
                            <li>{{ $t['q1'] }}</li>
                            <li>{{ $t['q2'] }}</li>
                            <li>{{ $t['q3'] }}</li>
                            <li>{{ $t['q4'] }}</li>
                        </ul>
                        <a class="btn btn-line" href="#contact">{{ $t['notify'] }}</a>
                    </article>
                </div>
            </div>
        </section>
        <section class="section wash" id="stories">
            <div class="wrap">
                <div class="section-intro center">
                    <p class="eyebrow" style="justify-content:center">{{ $t['storiesEyebrow'] }}</p>
                    <h2>{{ $t['storiesTitle'] }}</h2>
                    <p class="lede">{{ $t['storiesLede'] }}</p>
                </div>
                <div class="quotes">
                    <article class="quote"><h3>{{ $t['st1t'] }}</h3><p>{{ $t['st1p'] }}</p><div class="who"><div class="avatar">{{ $t['st1a'] }}</div><div>{{ $t['st1w'] }}<br><small>{{ $t['st1s'] }}</small></div></div></article>
                    <article class="quote"><h3>{{ $t['st2t'] }}</h3><p>{{ $t['st2p'] }}</p><div class="who"><div class="avatar">{{ $t['st2a'] }}</div><div>{{ $t['st2w'] }}<br><small>{{ $t['st2s'] }}</small></div></div></article>
                    <article class="quote"><h3>{{ $t['st3t'] }}</h3><p>{{ $t['st3p'] }}</p><div class="who"><div class="avatar">{{ $t['st3a'] }}</div><div>{{ $t['st3w'] }}<br><small>{{ $t['st3s'] }}</small></div></div></article>
                </div>
            </div>
        </section>
        <section class="section" id="faq">
            <div class="wrap">
                <div class="section-intro center">
                    <p class="eyebrow" style="justify-content:center">{{ $t['faqEyebrow'] }}</p>
                    <h2>{{ $t['faqTitle'] }}</h2>
                </div>
                <div class="faq">
                    <details open><summary>{{ $t['faq1q'] }}</summary><p>{{ $t['faq1a'] }}</p></details>
                    <details><summary>{{ $t['faq2q'] }}</summary><p>{{ $t['faq2a'] }}</p></details>
                    <details><summary>{{ $t['faq3q'] }}</summary><p>{{ $t['faq3a'] }}</p></details>
                    <details><summary>{{ $t['faq4q'] }}</summary><p>{{ $t['faq4a'] }}</p></details>
                    <details><summary>{{ $t['faq5q'] }}</summary><p>{{ $t['faq5a'] }}</p></details>
                </div>
            </div>
        </section>
        <section class="section wash" id="contact">
            <div class="wrap contact">
                <div>
                    <p class="eyebrow">{{ $t['contactEyebrow'] }}</p>
                    <h2>{{ $t['contactTitle'] }}</h2>
                    <p class="lede" style="margin-top:10px">{{ $t['contactLede'] }}</p>
                    <form style="margin-top:18px" action="#" onsubmit="return false">
                        <label>{{ $t['name'] }}<input name="name" autocomplete="name"></label>
                        <label>{{ $t['email'] }}<input name="email" type="email" autocomplete="email"></label>
                        <label>{{ $t['message'] }}<textarea name="message"></textarea></label>
                        <button class="btn btn-pine" type="submit">{{ $t['send'] }}</button>
                    </form>
                </div>
                <aside class="aside">
                    <h2 style="font-size:32px">{{ $t['asideTitle'] }}</h2>
                    <p>{{ $t['asideP'] }}</p>
                    <p>{{ $t['emailLine'] }}: <a href="mailto:hello@geoapi.ge">hello@geoapi.ge</a><br>{{ $t['server'] }}</p>
                    <a class="btn btn-mint" href="#pricing">{{ $t['create'] }}</a>
                </aside>
            </div>
        </section>
    </main>
    <footer class="footer">
        <div class="wrap">
            <div class="footer-grid">
                <div>
                    <a class="logo" href="#top"><img src="{{ asset('assets/brand/geoapi-lockup.png') }}" alt="geoapi.ge" height="36"></a>
                    <p style="margin-top:10px">{{ $t['foot'] }}</p>
                </div>
                <div>
                    <h3>{{ $t['product'] }}</h3>
                    <a href="#features">{{ $t['nav1'] }}</a>
                    <a href="#how">{{ $t['nav2'] }}</a>
                    <a href="#pricing">{{ $t['nav3'] }}</a>
                </div>
                <div>
                    <h3>{{ $t['doc'] }}</h3>
                    <a href="#demo-title">{{ $t['picker'] }}</a>
                    <a href="#faq">{{ $t['nav4'] }}</a>
                    <a href="#features">{{ $t['addresses'] }}</a>
                </div>
                <div>
                    <h3>{{ $t['nav5'] }}</h3>
                    <a href="mailto:hello@geoapi.ge">hello@geoapi.ge</a>
                    <a href="#contact">{{ $t['write'] }}</a>
                </div>
            </div>
            <p class="legal">{{ $t['legal'] }}</p>
        </div>
    </footer>
</div>
<script>
    const menu = document.querySelector(".menu-toggle");
    const nav = document.getElementById("nav");
    menu.addEventListener("click", function () {
        const open = nav.classList.toggle("open");
        menu.setAttribute("aria-expanded", open ? "true" : "false");
    });
</script>
@endcomponent
