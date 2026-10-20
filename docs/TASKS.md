# Tasks

## 2026-10-20 — Vehicle picker

- [x] Public demo page `/demo` with cascading brand, model, year, generation, and modification dropdowns.
- [x] Read-only English card: photo or “No photo yet”, specs, and equipment grouped as standard / optional / not available.
- [x] GET endpoints under `/api/picker` filter by id and page results. `GET /api/models/{id}/photo` returns only `model_photos` rows with `verified = true`.
- [x] `carapi_catalog_reader` granted `SELECT` on `modification_specs`, `modification_equipment`, and `model_photos`.

## 2026-10-20 — Audit and design review

- [x] Stop the project-page session crash when the platform cookie is Laravel-encrypted.
- [x] Stop registration and verification from returning 500 when mail cannot be sent. SMTP settings stay with the owner.
- [x] Keep email verification, and sync `users.email_verified_at` with `platform.users.email_verified_at`.
- [x] Admin unique conflicts return 409. Catalog creates accept an empty `source_url`. Equipment list requires `modification_id`. Generation list accepts `model_id` and `brand_id`.
- [x] Public OpenAPI, sitemap, security headers, and a CORS allow-list with no wildcard.
- [x] Widget `geoapi:change` includes the chosen `generation_id`. `modification_id` stays null because the widget has no modifications route.
- [x] English privacy and terms, Georgian password-reset status, home row count plus chassis groups.
- [ ] Owner: set `APP_DEBUG=false` in production and configure SMTP, including installing PHPMailer if `CARAPI_MAIL_*` is used.
- [ ] Owner: rotate any admin token that was pasted into the audit chat. It is not in this repository.
- [ ] Logos and model photos are still empty until ingest supplies files. No import was added.

## 2026-10-20 — Blog

- [x] Public `/blog` list with pagination, category and tag filters, and `/blog/{slug}`.
- [x] Georgian and English translations, RSS, sitemap entries, reading time, related posts, and per-post SEO.
- [x] Admin panel at `/admin/blog` and Sanctum CRUD at `/api/admin/blog`.
- [ ] Owner: run `php artisan migrate` and `php artisan storage:link` so covers are reachable. The panel needs a Laravel admin or operator session.

## 2026-10-20 — Public redesign

- [x] Apply the approved ink / pine / leaf / mint palette and Noto Sans Georgian to the public layout, home, pricing, guide, legal pages, blog, auth, coming-soon, and error pages.
- [x] Keep the vehicle picker photo inside the demo card. Do not add a lightbox.
- [x] Recolor the Electrik cabinet, the legacy project desk, and the admin stylesheet without changing their behavior.
- [ ] Owner: if production sets `ELECTRIK_BRAND_PRIMARY` to the old orange, remove it so the default `#285A48` is used.

## 2026-10-20 — Match the approved mockups

- [x] Rebuild the home page to the mockup section order, including the hero illustration. The demo photo stays inside the card.
- [x] Rebuild `/account` to the dashboard mockup and keep project, key, usage, and plan data on the existing platform tables.
- [x] Picker skeletons, 120-second list cache, generation-matched photos, corner watermark, KA/EN value labels, deduped generations, sorted brands, collapsed API response, and the listed copy fixes.
- [x] Pixel pass: home header, four demo selects (no year), testimonials, pricing and footer copy match `docs/mockups/home.html`. The photo stays in the card.
- [x] `/dashboard` and `/account` render that cabinet. `CARAPI_CABINET_DEMO=1` fills the local screenshot with the mockup's project, usage, keys, and catalog cards.

## 2026-10-20 — Full-screen photo watermark

- [x] `DemoWatermark` again tiles a large diagonal `GEOAPI.GE` across the whole image. Demo, widget, and `/getimage` responses are marked in the file, not with a CSS overlay.
- [x] The mark stays semi-transparent so the car remains visible. A download of the JPEG still contains the tiles.

## 2026-10-20 — Public API v1 parity

- [x] List responses keep `data` and `meta` and add `collection` (`count`, `total`, `pages`, `next`, `prev`, `first`, `last`). `limit` defaults to 100 and stops at 1000. `per_page` is the alias when `limit` is absent. `sort` and `direction` are whitelisted.
- [x] Filters: `year_from`, `year_to`, `body`, `fuel`, `drive`, `transmission`, `brand_id`, `model_id`, and `q`. `GET /api/v1/modifications` searches across the chain. Invalid values return 422.
- [x] Flat resources from stored columns only: `/api/v1/engines`, `/api/v1/bodies`, `/api/v1/fuel`, `/api/v1/colors`.
- [x] Free plan quotas: 60 per UTC minute, 2 000 successful requests per UTC day, 10 000 per UTC month. Success responses send `X-RateLimit-*` and the existing `X-Quota-*` headers. 429 is JSON with `Retry-After`.
- [x] Resources include `modified` (`updated_at`). `modified_after` filters newer rows.
- [x] `/api/v1/docs` renders Redoc from `/api/v1/openapi.json`. `/api/v1/postman.json` is the collection. `/guide` has cURL, JavaScript, Node, Python, and PHP samples.
- [ ] Owner: from `/www/wwwroot/geoapi.ge`, run `php artisan migrate --force` as the catalog table owner (`CARAPI_CATALOG_ADMIN_USER`) with `CARAPI_PLATFORM_*` set. Do not use `carapi_catalog_reader` for that command. No new PHP-FPM variable is required. Until `platform.project_limits.per_day` exists, the daily cap is not enforced.

## 2026-10-20 — Auto-deploy

- [x] `.github/workflows/deploy.yml` runs PHPUnit on push to `main`, then SSHes with `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, and optional `DEPLOY_PORT`.
- [x] The remote script deploys `/www/wwwroot/geoapi.ge` and runs `php artisan up` if a later command fails. Concurrency group `production-deploy` does not cancel an in-progress deploy. The workflow does not edit `.env`.
- [ ] Owner: one-time setup in `docs/DEPLOY.md` (deploy key, `authorized_keys`, the four secrets, remote `shootX/GEoAPI`, `safe.directory`, owner `www`).
