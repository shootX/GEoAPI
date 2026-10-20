# Tasks

## 2026-10-20 — QA audit, high and medium

- [x] Locale cookie, `/locale/{ka|en}`, `/en` and `/ka` redirects, English title, nav, and home picker.
- [x] Home generation count explains the grouped definition.
- [x] `/pricing` shows the existing free limit only.
- [x] Public `/api/docs` and OpenAPI without a stack trace. Guide example uses `demo` and the widget POST path.
- [x] Picker generations collapse the same chassis and keep market or steering in the label.
- [x] `q` filters picker brands.
- [x] Sitemap, robots, canonical, hreflang, Open Graph.
- [x] Security headers on PHP responses.
- [x] Catalog error text follows `Accept-Language` (Georgian for `ka`, English otherwise).

## 2026-10-20 — QA audit, critical

- [x] Stop uncaught errors from returning exception class, path, SQL, or trace. Unique violation is HTTP 409.
- [x] Send mail without PHPMailer so registration and resend do not 500 when `vendor/phpmailer` is missing.
- [x] If verification mail fails, keep the account and say so. Resend and reset catch every mail failure.
- [x] Ignore an illegal or oversized session cookie before `session_start` (Laravel `carapi-session` vs `carapi_platform`).
- [x] Remove the committed mailbox password from `.env.example`.

## 2026-10-20 — Vehicle picker

- [x] Public demo page `/demo` with cascading brand, model, year, generation, and modification dropdowns.
- [x] Read-only English card: photo or “No photo yet”, specs, and equipment grouped as standard / optional / not available.
- [x] GET endpoints under `/api/picker` filter by id and page results. `GET /api/models/{id}/photo` returns only `model_photos` rows with `verified = true`.
- [x] `carapi_catalog_reader` granted `SELECT` on `modification_specs`, `modification_equipment`, and `model_photos`.
