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

The first redesign only recolored the old layout. The follow-up copies the mockup sections. Home styles are `public/assets/public-design.css` (scoped under `.mk`). The cabinet is `/account` with `public/assets/cabinet.css`. Noto Sans Georgian stays self-hosted. `config/electrik.php` still defaults the brand primary to `#285A48`.

The home demo still includes `public/partials/vehicle-picker.blade.php` and `/assets/vehicle-picker.js`. Choosing a modification fills `#vp-card` in place. A photo is requested only when `photo_matches_generation` is true (`model_photos.generation_id` equals the chosen generation). `DemoWatermark` draws one small semi-transparent `GEOAPI.GE` in the corner. The mockup lightbox was not copied. Picker list queries are cached for 120 seconds under `legacy/cache/picker`. Generation dropdown labels are deduped in the browser. Brand and model names are sorted there too, with a short popular-brand list first.

The blog link is omitted from the header and footer while `blog_posts` has no visible row. The route stays. Password reset and email verification still use the public layout. The legacy project desk and `admin.css` were not rebuilt.
