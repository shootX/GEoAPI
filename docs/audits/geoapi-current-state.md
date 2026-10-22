# GeoAPI — WP-00 მდგომარეობა (ისტორიული)

> **ისტორიული snapshot, 2026-10-21 (WP-00).** ეს დოკუმენტი აღწერს მდგომარეობას WP-01–WP-12-მდე და აღარ არის მიმდინარე. მას შემდეგ დაემატა SDK, MCP server და CLI (`packages/`), CI მწვანეა, ბილინგი (BOG) და ინვოისები არსებობს. მიმდინარე მდგომარეობა: `docs/TASKS.md`, `docs/CATALOG_API.md`, `docs/ROLLBACK.md`, `docs/PERFORMANCE.md`.
>
> **Historical snapshot (WP-00, 2026-10-21). Not current.** See `docs/TASKS.md` for the current state.

აუდიტის თარიღი: 2026-10-21. რეჟიმი: მხოლოდ წაკითხვა. აპლიკაციის კოდი, მიგრაცია, `.env` და სერვერი არ შეცვლილა. VIN ამ პროექტის მიზანი არ არის და ახალი VIN კოდი არ დაწერილა.

სტატუსის ტეგები: `VERIFIED_CODE`, `VERIFIED_TEST_LOCAL`, `VERIFIED_PRODUCTION`, `PARTIAL`, `MISSING`, `BLOCKED`, `UNKNOWN`.

## 1. HEAD და საწყისი commit

`VERIFIED_CODE`. 2026-10-21-ზე `git fetch origin main`-ის შემდეგ:

- `git rev-parse HEAD` = `61c6cd7dcb78bfd467b73be71bb519b3dca6ff2d`
- `git rev-parse origin/main` = იგივე SHA
- `git rev-list --left-right --count HEAD...origin/main` = `0 0`
- `git diff --stat 61c6cd7dcb78bfd467b73be71bb519b3dca6ff2d HEAD` ცარიელია
- `git log -1 --format='%ci %s'` = `2026-10-21 02:41:45 +0400` და სათაური „საჯარო საიტი ახალ geoapi.ge ლოგოსა და favicon-ს იყენებს, CSP კი გვერდის რესურსებს ზღუდავს.“

HEAD სპეციფიკაციის საწყის commit-ს ემთხვევა. ამ აუდიტს HEAD-თან შედარებით აპლიკაციის diff არ აქვს. განსხვავება, რომელიც ქვემოთაა, ისტორიულ commit-ებსა და ცოცხალ HTTP პასუხებს ეხება, არა `main`-ის წინსვლას `61c6cd7`-ის შემდეგ.

## 2. სტეკი

`VERIFIED_CODE`. `composer.json:8-11` ითხოვს PHP `^8.2`, `laravel/framework` `^12.0`, `electrik/electrik` `5.5.0`. Autoload: `App\` → `app/`, `Carapi\` → `legacy/src/` (`composer.json:27-29`). ფრონტი Blade, Livewire და `public/widget/v1/geoapi-filter.js`. ცალკე `packages/` ხე არ არის (`MISSING` SDK, MCP და CLI).

მოთხოვნის გზა `VERIFIED_CODE`:

1. `bootstrap/app.php:17-20` ტვირთავს `routes/web.php`-ს და ჯანმრთელობის მისამართს `/up`.
2. `app/Providers/AppServiceProvider.php:39-42` boot-ის შემდეგ ტვირთავს `routes/admin-api.php`, შემდეგ `routes/product.php`, შემდეგ `routes/legacy.php` `web` middleware-ით.
3. `routes/web.php:5-7` უცნობ მისამართს აბრუნებს `errors.404`-ით.

## 3. საჯარო API v1

`VERIFIED_CODE`. რეგისტრაცია `routes/product.php:49-52`: ჯერ OpenAPI, Postman და `/api/v1/docs`, შემდეგ `ANY /api/v1/{path?}` → `CatalogController`. მარშრუტის ცხრილია `app/Catalog/CatalogApi.php:263-315`. არსებული გზები:

- `GET /api/v1/brands`
- `GET /api/v1/brands/{slug}/models`
- `GET /api/v1/models/{id}/generations`
- `GET /api/v1/generations/{id}/modifications`
- `GET /api/v1/modifications`
- `GET /api/v1/modifications/{id}`
- `GET /api/v1/engines`, `/bodies`, `/fuel`, `/colors`, `/coverage`

`GET /api/v1/models/{id}/years`, `/modifications/{id}/specs`, `/modifications/{id}/equipment` და ფოტოების v1 სია ამ `route()`-ში არ არის (`MISSING` ამ კონტრაქტში). წელი და მოდიფიკაციის ბარათი picker/widget ნაკადშია, იხილეთ `docs/audits/geoapi-features.csv`.

OpenAPI `VERIFIED_CODE`: `app/Catalog/PublicSpec.php:15-18` — `openapi` `3.0.3`, `info.version` `1.1.0`.

ავტორიზაცია კატალოგზე საიდუმლო გასაღებით: `legacy/src/Platform/ApiKeys.php:19` აგებს `geoapi_sk_`, `:27` იღებს `geoapi_sk_` და ძველ `carapi_sk_` ფორმას, `:39` ადარებს SHA-256-ს `hash_equals`-ით. ვიჯეტის საჯარო გასაღები: `legacy/src/Platform/WidgetRepository.php:17` აგებს `geoapi_pk_`.

## 4. რა არ არის ამ ხეში

- `packages/sdk-js`, MCP სერვერი და CLI: `MISSING`. ძებნამ `packages/` არ დააბრუნა.
- VIN დეკოდერის მარშრუტი ან სერვისი: `MISSING` (განზრახ, scope-გარე). privacy ტექსტი ამბობს, რომ VIN არ იკითხება (`resources/views/public/privacy.blade.php:21`). ეს ფუნქციის არარსებობაა, არა დავალება.
- Laravel-ის `config/services.php`-ში `stripe` სექცია არ არის. ფაილი მთავრდება Slack-ის ბლოკით (`config/services.php:30-38`).
- დოკუმენტებში მოხსენიებული `migrations/001_platform_users.sql` და `migrations/002_projects_api_keys.sql` ამ ხეში არ დევს. `**/*.sql` ძებნამ 0 ფაილი დააბრუნა (`MISSING` როგორც ფაილი). სვეტები, რომლებსაც PHP კითხულობს, ჩამოწერილია `docs/audits/geoapi-db-schema.md`-ში.

## 5. ცოცხალი HTTP, 2026-10-21T19:12:07Z

მხოლოდ GET. POST, ვებჰუკის ჩაწერა და ავტორიზებული კატალოგის მოთხოვნა არ გაგზავნილა. User-Agent: `GeoAPI-WP00-audit`.

| მისამართი | HTTP | ტეგი | შენიშვნა |
|---|---|---|---|
| `GET /` | 200 `text/html` | `VERIFIED_PRODUCTION` | `lang="ka"`, სათაური „GeoAPI — ავტომობილების მონაცემები“. CSP და HSTS ჰედერებია. |
| `GET /up` | 200 | `VERIFIED_PRODUCTION` | Laravel-ის `health-up` გვერდი. CSP ამ პასუხზე არ ჩანდა. იხილეთ უსაფრთხოების ფაილი. |
| `GET /guide`, `/demo`, `/api/v1/docs` | 200 | `VERIFIED_PRODUCTION` | დოკუმენტაცია და დემო იხსნება ავტორიზაციის გარეშე. |
| `GET /api/v1/openapi.json` | 200 | `VERIFIED_PRODUCTION` | `openapi=3.0.3`, `info.version=1.1.0`, სერვერი `https://geoapi.ge`. paths ემთხვევა `CatalogApi::route`-ს. |
| `GET /api/v1/postman.json` | 200 | `VERIFIED_PRODUCTION` | collection v2.1.0, `baseUrl` `https://geoapi.ge`, ტოკენის მნიშვნელობა placeholder `geoapi_sk_…`. |
| `GET /api/v1/brands` და `/api/v1/coverage` | 401 | `VERIFIED_PRODUCTION` | `error.code=unauthorized`, `WWW-Authenticate: Bearer`, `Cache-Control: no-store`. 200 კატალოგის სხეული ამ აუდიტში არ წაკითხულა, რადგან საიდუმლო გასაღები არ გამოყენებულა. |
| `GET /widget/v1/geoapi-filter.js` | 200, 25259 ბაიტი | `VERIFIED_PRODUCTION` | SHA-256 პრეფიქსი `e30e758c17fac344` ემთხვევა HEAD-ის ფაილს. სხეული შეიცავს `/years` და `modification_id: null`. |
| `GET /assets/fonts/bpg-arial.woff2` | 404 | `VERIFIED_PRODUCTION` | ბრენდირებული 404 HTML. HEAD-ში ფაილი არ არის. ახლა გამოქვეყნებული `fonts.css`, `public-design.css`, `site.css` და `cabinet.css` ამ სახელს არ შეიცავს. მითითება მხოლოდ diverged commit `b3ec455`-ის `fonts.css:6`-შია. |
| `GET /sitemap.xml` | 200 | `VERIFIED_PRODUCTION` | `https://geoapi.ge`, `/guide`, `/demo`, `/pricing`, `/privacy`, `/terms`. |
| `GET /billing/provider-webhook` | 404 | `VERIFIED_PRODUCTION` | მარშრუტი კოდში მხოლოდ POST-ია (`routes/product.php:82`). GET 404 არ ამტკიცებს, რომ POST ცოცხალ სერვერზე რეგისტრირებულია. POST არ გაგზავნილა, რომ `payment_events` არ ჩაწერილიყო. |

ცოცხალი ბაზის რაოდენობები ამ აუდიტში `UNKNOWN`. PostgreSQL-თან SELECT არ გახსნილა. `docs/MEMORY.md`-ის ძველი „დაახლოებით 350k / 38M“ და სხვა ისტორიული რიცხვები ხელახლა არ დადასტურებულა და აქ ცოცხალ რაოდენობად არ გადმოაქვს.

## 6. პრიორიტეტული აღმოჩენები

1. `BLOCKED` — CI. ხუთივე Deploy run failure-ია, Deploy job skipped. მიზეზი `MissingAppKeyException`. ზუსტი, გამოუყენებელი შესწორებაა `docs/audits/geoapi-ci.md`-ში. ლოკალურად, ეფემერული გასაღებით, იგივე 48 ტესტი გავიდა (407 assertion, exit 0). ეს პროდაქშენის ტესტი არ არის.
2. `BLOCKED` — Stripe webhook. ხელმოწერა არ მოწმდება. დეტალი `docs/audits/geoapi-security.md`.
3. `PARTIAL` / `UNKNOWN` — deploy ბილიკი. HEAD-ის workflow შედის `/www/wwwroot/geoapi.ge`-ში. ძველი workflow შედიოდა `/www/wwwroot/car.socialsave.cc`-ში. ცოცხალი nginx-ის `root` ამ აუდიტს არ წაუკითხავს. სტრიქონი `/www/wwwroot/car.socialsave.cc/public` რეპოზიტორიის ისტორიაში არ მოიძებნა.
4. `VERIFIED_CODE` — სუსტი `.gitignore` და ჯერ კიდევ GitHub-ზე SHA-ით ხელმისაწვდომი commit `b3ec455`, სადაც დაემატა `.env.bak-2026-10-20-1633` და ორი session ფაილი. შიგთავსი არ წაკითხულა და არსად არ დაბეჭდილა.
5. `VERIFIED_PRODUCTION` — `bpg-arial.woff2` 404. მიმდინარე გამოქვეყნებული CSS მას არ ითხოვს. ფაილი HEAD-ში არ არის.
6. ღია draft PR-ებია #1 (კონფლიქტი `main`-თან) და #10 (`main`, MERGEABLE, შემოწმების გარეშე). #2 ღია draft-ია, მაგრამ ბაზა `main` არ არის. #3 შერწყმულია, ღია draft აღარ არის.

WP-01 არ დაწყებულა.
