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

- [x] `.github/workflows/deploy.yml` runs PHPUnit on push to `main` and on pull requests, then SSHes only for a `main` push with `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`, optional `DEPLOY_PORT`, and optional `DEPLOY_PATH`.
- [x] The remote script deploys `DEPLOY_PATH` (default `/www/wwwroot/geoapi.ge`) and runs `php artisan up` if a later command fails. It aborts when that directory or `.git` is missing. Concurrency group `production-deploy` does not cancel an in-progress deploy. The workflow does not edit `.env`.
- [ ] Owner: one-time setup in `docs/DEPLOY.md` (GitHub deploy key for the private repo, `authorized_keys`, secrets including `DEPLOY_PATH`, remote `git@github.com:shootX/GEoAPI.git`, `safe.directory`, owner `www`).

## 2026-10-21 — WP-00 read-only audit

- [x] Read-only audit only. No application code, schema, `.env`, server, or migration change. `composer update`, `npm update`, `migrate`, `seed`, and deploy were not run.
- [x] Reports: `docs/audits/geoapi-current-state.md`, `docs/audits/geoapi-features.csv`, `docs/audits/geoapi-db-schema.md`, `docs/audits/geoapi-security.md`, `docs/audits/geoapi-ci.md`.
- [x] HEAD equals `61c6cd7dcb78bfd467b73be71bb519b3dca6ff2d`. Diff against that commit is empty.
- [x] WP-01: the test job runs `php artisan key:generate --force` on the runner after `composer install`. The key is not committed. Deploy reads `DEPLOY_PATH` and does not create a checkout. `.gitignore` ignores `.env.*` (except `.env.example`), `*.bak*`, framework session/cache/view files, and `/storage/logs`.
- [ ] Owner: live PostgreSQL counts and `migrate:status` are still `UNKNOWN`. Do not publish historical row counts as current totals.
- [ ] Owner: commit `b3ec4558022778b0534d9f233df9294be14689d8` is not on `main` but is still reachable by SHA and added `.env.bak-2026-10-20-1633` plus two session files. Contents were not read here. Rotate anything that file exposed. `.gitignore` now ignores those names; the old commit is still on GitHub.
- [ ] Owner: confirm the live nginx document root. HEAD deploys with `cd /www/wwwroot/geoapi.ge`. The checkout path in `b746497` was `/www/wwwroot/car.socialsave.cc`. The absolute `.../public` root was not in git history and was not read from the server.

## 2026-10-21 — WP-02 Stripe webhook

- [x] `POST /billing/provider-webhook` checks the raw body against `Stripe-Signature` before `PaymentEvents::record`. The scheme is `v1` HMAC-SHA256 over `timestamp.payload`, with a 300 second tolerance, `hash_equals`, and any matching `v1`. Missing, forged, and expired signatures return 400. An empty `STRIPE_WEBHOOK_SECRET` returns 503. A valid event is stored once; the same event id returns 200 and does not change the row.
- [x] The handler does not write `api_entitlements` or `api_plans`. The stored summary is only the event type. Logs record a reason code and not the payload, header, or secret.
- [x] OpenAPI and `/guide` were left unchanged. This route is not documented there.
- [ ] Owner: set `STRIPE_WEBHOOK_SECRET` on the server only when a paid product is approved. Until then the route stays 503 and must not grant a plan.

## 2026-10-21 — WP-03 live catalog (BLOCKED)

- [ ] BLOCKED. This environment cannot open the live catalog or platform database. No `SELECT` was run. Counts, foreign keys, indexes, triggers, photo rights, and `platform.project_limits.per_day` stay `UNKNOWN`.
- [ ] Owner: run `docs/audits/wp03-owner-queries.sql` as the read-only catalog role and paste every result back, including errors. Do not run `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `migrate`, or `down`. Also paste `php artisan migrate:status` without printing `.env`.

## 2026-10-21 — WP-04 REST catalog

- [x] `CatalogApi`, `PublicCatalog`, `ListQuery`, and `PublicSpec` use one route list. List responses keep `data`, `meta`, and `collection`. `limit` stays 1–1000. Sort stays on an allowlist. `Authorization: Bearer geoapi_sk_…` is unchanged.
- [x] `GET /api/v1/models/{id}/years` expands stored `year_start`/`year_end` on generations and modifications. An open `year_end` stops at the current UTC year. `GET /api/v1/modifications/{id}/specs` returns stored engine, transmission, and L/100km consumption. `GET /api/v1/modifications/{id}/equipment` groups `included` / `option` / `not_available` and puts anything else in `unknown`.
- [x] MISSING: dimension and performance columns are not stored (`dimensions` and `performance` are null). Photo list routes are not added: `model_photos` is one row per model, and attribution for `photos` / `generations.photo_urls` is not established. The existing `/api/models/{id}/photo` route stays. A separate `/search` route was not added; `GET /api/v1/modifications` remains the search. VIN was not added. The widget still calculates years in its own SQL; WP-05 should use this endpoint.
- [ ] Owner: WP-03 results are still required before treating live counts or photo rights as known.
