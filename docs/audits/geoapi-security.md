# GeoAPI — უსაფრთხოების აუდიტი (WP-00)

არაფერი არ გამოსწორებულა. საიდუმლო მნიშვნელობა არ წაკითხულა და არ დაბეჭდილა. `.env.bak`-ის შიგთავსი, session ფაილები, SMTP პაროლი და production `APP_KEY` ამ ანგარიშში არ არის.

## 1. Stripe webhook — ხელმოწერა არ მოწმდება

`BLOCKED` როგორც გადახდის დადასტურება. `PARTIAL` როგორც ჩონჩხი.

`app/Http/Controllers/PaymentWebhookController.php:15-37`:

- კითხულობს `config('services.stripe.webhook_secret')`.
- ცარიელ მნიშვნელობაზე აბრუნებს 503 `billing_not_configured`.
- შემდეგ ამოწმებს მხოლოდ JSON-ის `id` და `type` სტრიქონებს.
- იძახებს `PaymentEvents::record('stripe', ...)`.
- `Stripe-Signature` ჰედერი, raw body, timestamp tolerance და HMAC ამ ფაილში არ არის. რეპოში `Stripe-Signature` / `hash_hmac` webhook-ისთვის არ მოიძებნა.

`config/services.php` `stripe` გასაღებს არ შეიცავს (`config/services.php:17-38`). Electrik-ის კომენტარი `config/electrik.php:224-226` ამბობს, რომ billing driver ნაგულისხმევად `stripe`-ია. ეს Laravel Cashier-ის კონფიგი არ არის და webhook-ის საიდუმლოს არ ავსებს.

მარშრუტი `POST /billing/provider-webhook` (`routes/product.php:82-83`) ავტორიზაციის გარეშეა და CSRF გამონაკლისშია (`bootstrap/app.php:48`).

`PaymentEvents.php:17-26` მხოლოდ `payment_events`-ში წერს `insertOrIgnore`-ით. unique `(provider, event_id)` მიგრაციაშია (`2026_12_31_900000_create_api_products.php:77`). ეს ივენთიდან გეგმას არ ანიჭებს: `payment_events`-ის სხვა მკითხველი აპლიკაციის PHP-ში არ მოიძებნა (მხოლოდ კონტროლერი, მიგრაცია და `tests/Unit/ProductAccessTest.php:119-123`). დუბლიკატის ტესტი ლოკალურად გავიდა (`VERIFIED_TEST_LOCAL`). ხელმოწერის უარყოფის ტესტი `MISSING`.

შედეგი: სანამ secret ცარიელია, კონტროლერი 503-ს აბრუნებს და არ წერს. თუ secret მოგვიანებით დაყენდება ამ კოდის შეცვლის გარეშე, ნებისმიერი კლიენტი `id`/`type`-ით ჩაწერს ივენთს ხელმოწერის გარეშე. ეს გადახდის დადასტურებად არ გამოდგება. Pro გაყიდვაში არ უნდა ჩაირთოს. POST ცოცხალ ჰოსტზე არ გაგზავნილა. `GET /billing/provider-webhook` 2026-10-21-ზე აბრუნებდა 404 (`VERIFIED_PRODUCTION` მხოლოდ GET-ზე).

გამოუყენებელი შესწორების მიმართულება (WP-02, ამ PR-ში არ გაკეთებულა): raw body + `Stripe-Signature` + timestamp tolerance; არასწორ/ვადაგასულ/ყალბ ხელმოწერაზე 400; secret-ის გარეშე 503; დუბლიკატზე მეორე grant-ის გარეშე 200; ივენთმა entitlement არ შეცვალოს, სანამ ბიზნეს-გეგმა ცალკე არ დამტკიცდება.

## 2. `.gitignore` და commit `b3ec455`

`VERIFIED_CODE` მიმდინარე ignore-ზე. Commit GitHub-ზე SHA-ით იხსნება და `main`-ის წინაპარი არ არის.

`.gitignore:3-5` იგნორებს მხოლოდ `.env`, `.env.backup`, `.env.production`. არ არის `.env.*`, `.env.bak*`, `*.bak`. `.gitignore:19-20` იგნორებს `/storage/*.key` და `/storage/pail`. `storage/framework/*` მთლიანად არ არის იგნორირებული. `storage/framework/.gitignore` ასახელებს ცალკეულ cache ფაილებს, არა `carapi-sessions/`. `storage/framework/sessions/.gitignore` მხოლოდ იმ დირექტორიას ფარავს.

Commit `b3ec4558022778b0534d9f233df9294be14689d8` (2026-10-20T18:09:19Z, შეტყობინება „Server local changes backup“, მშობელი `b746497f7e9c`) GitHub API-ით იკითხება. `git cat-file` ლოკალურ clone-ში ამ ობიექტს ვერ ხედავს. `compare` `61c6cd7...b3ec455`: `status=diverged`, `ahead_by=1`, `behind_by=4`. ანუ commit `main`-ზე არ არის, მაგრამ SHA ჯერ კიდევ საჯაროდ ხელმისაწვდომია.

დამატებული სახელები, შიგთავსის გარეშე:

- `.env.bak-2026-10-20-1633` (status `added`, GitHub-ის `changes` 116)
- `storage/framework/carapi-sessions/sess_500d8f607a1669b3c4e41a04669f1dd9`
- `storage/framework/carapi-sessions/sess_998e4c8a541edc98498e9c3564e4fc3b`
- `public/assets/fonts/bpg-arial.woff2`

HEAD-ზე ეს ფაილები არ დევს (`gh api contents` `main`-ზე `.env.bak-...` და `bpg-arial.woff2` აბრუნებს 404). Ignore-ის ხვრელი რჩება: იგივე სახელის ხელახლა დამატებას `.gitignore` არ შეაჩერებს.

ეს აუდიტი blob-ს არ გადმოწერს და ისტორიას არ წერილავს. WP-00-ის ფარგლებს გარეთ რჩება: ignore-ის გამკაცრება (`.env.*`, `*.bak`, `storage/framework/carapi-sessions/`), ისტორიიდან საიდუმლოს ამოღება და გასაღებების როტაცია, თუ ბექაპი რეალურ secret-ს შეიცავს. როტაცია აქ არ გაკეთებულა, რადგან შიგთავსი განზრახ არ წაკითხულა.

## 3. CSRF გამონაკლისები

`VERIFIED_CODE`. `bootstrap/app.php:35-48` Laravel-ის CSRF შემოწმებას არ ატარებს გზებზე `login`, `register`, `logout`, `account`, `account/*`, `admin`, `admin/*`, `email/*`, `forgot-password`, `reset-password`, `api/*`, `widget/*`, `billing/provider-webhook`.

Legacy ავტორიზაციას საკუთარი შემოწმება აქვს: `legacy/src/Auth/Router.php:392-396` კითხულობს `_csrf` ან `X-CSRF-TOKEN`. Blade ფორმები `admin/*`-ზე მაინც ბეჭდავენ `@csrf` (`resources/views/admin/blog/index.blade.php`), მაგრამ Laravel middleware ამ პრეფიქსზე ტოკენს აღარ მოითხოვს. ამ აუდიტმა ყალბი cross-site მოთხოვნა არ გაუშვა. დასკვნა შეზღუდულია: ფრეიმვორკის CSRF ამ გზებზე გამორთულია (`VERIFIED_CODE`); დამატებითი დაცვა ყოველ ფორმაზე ცალ-ცალკე სრულად არ გადამოწმებულა (`PARTIAL`).

## 4. გასაღებები და სესია

`VERIFIED_CODE`. საიდუმლო გასაღები ბაზაში hash-ად ინახება (`ApiKeys.php:20` და `:39`). სრული ტოკენის ხელახალი ჩვენება ამ აუდიტში არ შემოწმებულა UI-დან. `geoapi_pk_` HTML-ში დასაშვებია და origin allowlist ავთენტიფიკაცია არ არის (`docs/WIDGET_INTEGRATION.md:5`, კოდი `WidgetRepository.php`).

სესიის cookie `geoapi_platform` და ძველი `carapi_platform` დაშიფვრის სიიდან ამოღებულია (`bootstrap/app.php:24-27`). `tests/Unit/SessionIdTest.php` ლოკალურ suite-ში გავიდა: მიიღება მხოლოდ 32 hex.

## 5. საჯარო ჰედერები

`VERIFIED_PRODUCTION` 2026-10-21T19:12:07Z, `GET /` და `/guide`: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, HSTS `max-age=31536000; includeSubDomains`, და CSP რომელიც ემთხვევა `app/Http/Middleware/SecurityHeaders.php:13`. CSP შეიცავს `script-src 'unsafe-inline' 'unsafe-eval'` და `style-src 'unsafe-inline'`. ეს დაკვირვებაა, ექსპლოიტი არ გაშვებულა.

`GET /up` 200 იყო და იმავე ჩამონათვალში CSP / `X-Frame-Options` არ ჩანდა. გვერდი არის Laravel-ის `vendor/laravel/framework/src/Illuminate/Foundation/resources/health-up.blade.php`, რომელიც ტვირთავს `fonts.bunny.net` და `cdn.jsdelivr.net`. Health მარშრუტი `bootstrap/app.php:20`-ით რეგისტრდება web ჯგუფის გარეთ, ამიტომ `SecurityHeaders` მას არ ედება (`VERIFIED_CODE` + `VERIFIED_PRODUCTION`). საიდუმლოს ეს გვერდი არ ბეჭდავს. დაფიქსირდა მხოლოდ „Application up“ შაბლონი.

`GET /api/v1/brands` აბრუნებს `Cache-Control: no-store` და `WWW-Authenticate: Bearer` (`VERIFIED_PRODUCTION`).

## 6. `bpg-arial.woff2`

`VERIFIED_PRODUCTION`: `GET https://geoapi.ge/assets/fonts/bpg-arial.woff2` → 404, branded HTML, 2026-10-21T19:12:07Z.

`VERIFIED_CODE`: HEAD-ში `public/assets/fonts/bpg-arial.woff2` არ არის. `public/assets/fonts/fonts.css` ამ URL-ს არ შეიცავს. იგივე სახელი არ არის `public-design.css`, `site.css`, `cabinet.css` არც ლოკალურ ხეში და არც იმ მომენტის ცოცხალ პასუხებში.

მითითება არსებობს მხოლოდ diverged commit-ში: `b3ec455`-ის `public/assets/fonts/fonts.css` ხაზები 2 და 6, `font-family: "BPG Arial"` და `src: url("/assets/fonts/bpg-arial.woff2")`. ეს CSS ახლა საიტის `GET /assets/fonts/fonts.css`-ით არ ბრუნდება (`VERIFIED_PRODUCTION`, სხეული შეიცავს Noto/Roboto-ს და არა `bpg-arial`). მიმდინარე მთავარი გვერდი ამ ფაილს არ ითხოვს. URL მაინც 404-ია, თუ ძველი CSS ან პირდაპირი ბმული მას მოითხოვს.

## 7. Deploy ბილიკი

`VERIFIED_CODE` workflow-ზე. ცოცხალი nginx `root` — `UNKNOWN`.

HEAD `.github/workflows/deploy.yml:58` და `:88` შედის `/www/wwwroot/geoapi.ge`-ში. ეს არის `git pull`-ის დირექტორია, არა დოკუმენტის root. Laravel-ის web root, თუ ეს checkout სწორია, იქნებოდა ამ გზის `public/` ქვედირექტორია. ეს დასკვნა კონვენციაა; ცოცხალი კონფიგი არ წაკითხულა.

`b746497:.github/workflows/deploy.yml:58` იმავე ბრძანებით შედიოდა `/www/wwwroot/car.socialsave.cc`-ში. `904e3a0:.user.ini` წერდა `open_basedir=/www/wwwroot/car.socialsave.cc/:/tmp/`. ეს არის checkout/open_basedir, არა სტრიქონი `.../public`. `git log -S '/www/wwwroot/car.socialsave.cc/public'` ამ clone-ში ცარიელია. `docs/PROJECT_AUDIT.md:24` ამბობს, რომ nginx document root არის ფარდობითი `public/` და ასახელებს სერვერის ფაილს `/www/server/panel/vhost/nginx/geoapi.ge.conf`, რომელიც რეპოში არ დევს. იმ დოკუმენტის პირველი ნაწილი თავად ამბობს, რომ ტექსტი ისტორიულია.

მოცემული სტრიქონი „live nginx root = `/www/wwwroot/car.socialsave.cc/public`“ ამ აუდიტით `UNKNOWN`-ია. დადასტურებული შეუსაბამობა სხვაა: workflow-მ გზა გადაარქვა `car.socialsave.cc`-დან `geoapi.ge`-ზე commit `1298895`-ში, ხოლო ხუთივე Deploy job გამოტოვებულია ტესტის ჩავარდნის გამო (`docs/audits/geoapi-ci.md`). Actions-ს არც ერთი გზა რეალურად არ დაუდასტურებია SSH-ით. სანამ სერვერის კონფიგი არ წაიკითხება, არ ვიცით, რომელი დირექტორია ემსახურება `https://geoapi.ge`-ს.

დაკვირვება, რომელიც გზას არ ამხელს: 2026-10-21-ის საჯარო HTML, OpenAPI `1.1.0` და widget-ის SHA-256 ემთხვევა HEAD `61c6cd7`-ს (`VERIFIED_PRODUCTION`). საიტი ამ commit-ის საჯარო ფაილებს ემსახურება. ეს არ ამტკიცებს დისკის გზას.
