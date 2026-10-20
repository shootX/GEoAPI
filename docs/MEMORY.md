# Memory

## 2026-10-20 — Vehicle picker

The catalog tables do not use `year_from` / `year_to` or a 55-column spec table. Years are `year_start` and `year_end`. Generation code is `chassis_code`. Specs live in `modification_specs.specs` (jsonb). Equipment columns are `category`, `name`, `status` (`included`, `option`, `not_available`), and `value`. `model_photos` is one row per model (`model_id` primary key) with `image bytea`, `mime`, and `verified`.

Picker queries always filter by brand, model, generation, or modification id. The modifications list limits rows before joining specs, because one generation can have thousands of rows and `modifications` is about 350k. Equipment is read only for the chosen `modification_id` (`modification_equipment` is about 38M rows, at most a few hundred per modification).

`GET /api/models/{id}/photo` returns the bytea with its mime type only when `verified = true`. The picker JSON routes are same-origin and share the demo IP limit (`cache/demo-limit`, 120/minute). The photo route is GET without an origin check so an `<img>` can load it, but only when the browser says the destination is an image and the fetch is same-origin. The response is watermarked or it is not sent.

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
