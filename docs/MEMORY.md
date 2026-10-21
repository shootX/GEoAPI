# Memory

## 2026-10-20 — Vehicle picker

The catalog tables do not use `year_from` / `year_to` or a 55-column spec table. Years are `year_start` and `year_end`. Generation code is `chassis_code`. Specs live in `modification_specs.specs` (jsonb). Equipment columns are `category`, `name`, `status` (`included`, `option`, `not_available`), and `value`. `model_photos` is one row per model (`model_id` primary key) with `image bytea`, `mime`, and `verified`.

Picker queries always filter by brand, model, generation, or modification id. The modifications list limits rows before joining specs, because one generation can have thousands of rows and `modifications` is about 350k. Equipment is read only for the chosen `modification_id` (`modification_equipment` is about 38M rows, at most a few hundred per modification).

`GET /api/models/{id}/photo` returns the bytea with its mime type only when `verified = true`. The picker JSON routes are same-origin and share the demo IP limit (`cache/demo-limit`, 120/minute). The photo route is GET without an origin check so an `<img>` can load it, but only when the browser says the destination is an image and the fetch is same-origin. The response is watermarked or it is not sent.

`DemoWatermark` burns `GEOAPI.GE` into the JPEG. The text is large, rotated -28 degrees, and repeated on a staggered grid that covers the frame, including past the edges. Ink is white at GD alpha 62 and the shadow is darker at alpha 78, so the car stays visible. The same function marks the demo and widget photo route and `/getimage`. PR #6 had reduced this to one small corner label; that corner is not the protection. The CSS `GEOAPI.GE` on the home and cabinet illustrations is only the mockup placeholder, not the photo file.

`carapi_catalog_reader` previously could not read `modification_specs`, `modification_equipment`, or `model_photos`. `SELECT` on those three tables was granted on 2026-10-20. No write privilege was added.

## 2026-10-20 — Audit fixes

- `APP_DEBUG` and SMTP environment values were intentionally left unchanged.
- Default locale is `APP_LOCALE` (ka on this site). Electrik's locale list does not include ka, so `ApplyChosenLocale` restores `config('carapi.locale')` captured at boot. A session choice of ka or en still wins.
- PHPMailer is not a Composer dependency. `SmtpMailer` throws `UnavailableException` when the class or the configuration is missing, so registration does not 500. Mail is not delivered until the owner installs the class and sets `CARAPI_MAIL_*`.
- Admin catalog integers are cast in `WritableCatalog::present`. Public picker ids stay strings because the guide and the widget already consume strings.
- Home `22 367` is production row count, not a constant in the view. Tests seed `legacy/cache/public-counts.json`.
- No API keys, passwords, or `.env` secrets were found in the tree. `.env` is gitignored.

## 2026-10-20 — Blog

Posts live in `blog_posts.translations` as `{ka, en}` objects with `title`, `excerpt`, `body` (Markdown), `meta_title`, and `meta_description`. One slug is shared by both languages. Public URLs are `/ka/blog/{slug}` and `/en/blog/{slug}`; `/blog/{slug}` follows the session locale. `published` is visible immediately. `scheduled` is visible when `publish_at` is in the past. Drafts are 404 for visitors and `noindex` for an admin. Covers are stored on the `public` disk under `blog/covers/`. The panel reuses `/assets/admin.css` and expects a Laravel admin or operator session; a platform-only cookie does not open `/admin/blog`. The public pages reuse `components.layouts.public` and do not add a new stylesheet.

## 2026-10-20 — Public redesign

The first redesign only recolored the old layout. Home now follows `docs/mockups/home.html`: the mockup header and footer are inside `.mk`, and the demo is four selects (brand, model, generation, modification) with no year step. The photo stays inside the result card. There is no lightbox. `/demo` still uses `vehicle-picker.js`. Styles are `public/assets/public-design.css`. Noto Sans Georgian is self-hosted, including the Latin and Latin-ext subsets, so the mockup's Latin words use the same face.

`/dashboard` and `/account` both render `App\Livewire\Account\Projects` with `public/assets/cabinet.css`. The `/dashboard` route is registered before Electrik's shell. When `CARAPI_CABINET_DEMO=1`, the cabinet shows the mockup sample (დემო პროექტი, 2 140 used, the 30-day bars, ვიჯეტი / სერვერი, and the six catalog cards) instead of the platform database. Leave that flag unset in production. PHPUnit forces it off.

The blog link is omitted from the header and footer of pages that still use the public chrome while `blog_posts` has no visible row. The route stays. The legacy project desk at `/account/projects/{id}` was not rebuilt.

## 2026-10-20 — Public API v1

The public catalog is `App\Catalog\CatalogApi` on `ANY /api/v1/{path?}`. `/api/v1/openapi.json`, `/api/v1/postman.json`, and `/api/v1/docs` are registered first, so they are not caught by that route. The widget still uses `Http::readForProject()` and its own `per_page` (default 50, max 100).

`meta.page`, `meta.per_page`, and `meta.total` stay. `collection` is added. `per_page` applies only when `limit` is missing. The default page size for `/api/v1` is 100, maximum 1000. Years are still `year_start` and `year_end`; `year_from` / `year_to` are query filters for overlap, not columns. There is no engines, bodies, or colors table. `/engines` and `/fuel` read `modifications` plus `modification_specs.specs` (`fuel_type`, `fuel_city_l`, `fuel_highway_l`, `fuel_combined_l`, `power_hp`). `/bodies` is the distinct `body_type` values. `/colors` is `model_photos.color` and does not return the image. `modified` is `updated_at` once the owner migration adds that column.

Free limits remain 60/minute and 10 000/month. The new daily cap is 2 000 successful requests per UTC day, stored as `platform.project_limits.per_day` (default 2000). If that column is missing, the daily check is skipped so an undeployed migration does not turn the API into 503. `X-Quota-*` stays the monthly quota. `X-RateLimit-*` is the minute window on success, and the limit that was hit on 429. New projects insert `per_day` when the column exists. `APP_DEBUG` and mail settings were not changed.

## 2026-10-20 — Auto-deploy

Push to `main` is `.github/workflows/deploy.yml`. PHPUnit runs first on the GitHub runner. The server step uses `appleboy/ssh-action` and the secrets `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, and `DEPLOY_PORT` (22 when unset). Commands run in `/www/wwwroot/geoapi.ge`. The checkout owner is `www`. PHP-FPM stays `carapi`. The workflow never writes `.env`. One-time setup is `docs/DEPLOY.md`. The public remote is `https://github.com/shootX/GEoAPI.git`.

## 2026-10-21 — WP-00

HEAD and `origin/main` are `61c6cd7dcb78bfd467b73be71bb519b3dca6ff2d`. There is no diff against that commit. The audit did not open PostgreSQL, so every live `COUNT(*)` stays unknown. Older figures in this file (about 350k modifications, about 38M equipment rows, home `22 367`) were not measured again.

CI on `61c6cd7` is run 39509993059: PHPUnit failed with `MissingAppKeyException`, Deploy was skipped. All five Deploy runs failed the same way. `.github/workflows/deploy.yml` copies `.env.example` (`APP_KEY=` empty) and runs `vendor/bin/phpunit`. `phpunit.xml` does not set `APP_KEY`. A local-only rerun on PHP 8.4.27 reproduced exit 2 with 48 tests, 94 assertions, 30 errors. After `php artisan key:generate --force` in that ephemeral copy, PHPUnit exited 0: 48 tests, 407 assertions. That `.env` and `vendor/` are not part of the audit commit. The workflow was not changed.

`POST /billing/provider-webhook` records a Stripe event id without reading `Stripe-Signature`. `config/services.php` has no `stripe` key. `PaymentEvents::record` only inserts `payment_events`. It does not grant a plan.

Commit `b3ec4558022778b0534d9f233df9294be14689d8` is diverged from `main` (parent `b746497`) and still readable by SHA. It added `.env.bak-2026-10-20-1633`, two `storage/framework/carapi-sessions/sess_*` files, and `public/assets/fonts/bpg-arial.woff2`. The file contents were not opened. `.gitignore` does not ignore `.env.*`, `*.bak`, or `storage/framework/carapi-sessions/`. On 2026-10-21T19:12:07Z, `GET /assets/fonts/bpg-arial.woff2` returned 404. The CSS served at that time does not reference that file. HEAD does not contain it.

`deploy.yml` at HEAD uses `/www/wwwroot/geoapi.ge`. At `b746497` it used `/www/wwwroot/car.socialsave.cc`. Because every Deploy job was skipped, Actions has not shown which directory the server uses. The string `/www/wwwroot/car.socialsave.cc/public` is not in this repository's history. The live nginx root was not read.

Open pull requests on 2026-10-21: #1 draft, base `main`, conflicting; #2 draft, base `cursor/critical-audit-fixes-8fe2`, not `main`; #10 draft, base `main`, mergeable, no checks. #3 is merged (2026-10-20T10:39:01Z), not an open draft.

Public `GET /api/v1/brands` and `/api/v1/coverage` returned 401. OpenAPI 3.0.3 version 1.1.0 at `https://geoapi.ge` matched the paths in `CatalogApi`. The published widget file hash matched HEAD, including `modification_id: null`. VIN was not added. WP-01 was not started.

## 2026-10-21 — WP-01

The test job copies `.env.example`, installs dependencies, runs `php artisan key:generate --force`, then `vendor/bin/phpunit`. Pull requests run that job only. The deploy job runs only on a push to `main`. Its path is the secret or variable `DEPLOY_PATH`, default `/www/wwwroot/geoapi.ge`. A missing directory or missing `.git` aborts the script with no `mkdir` and no clone. After `migrate --force`, the remote script still builds the config, route, and view caches, then `queue:restart`. The runner fails the deploy job when `https://geoapi.ge/up` is not 200. `docs/DEPLOY.md` separates the Actions login key from the read-only GitHub deploy key used for `git pull`.

`.gitignore` now has `.env.*` with `!.env.example`, `*.bak*`, and contents of `storage/framework/{sessions,carapi-sessions,cache,views}` plus `/storage/logs`, each with a tracked `.gitignore` keeper. No previously tracked generated file matched those patterns. `.env.example` and the keeper files stay tracked. Production `.env`, secrets, and `APP_DEBUG` were not changed.
