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

## 2026-10-21 — WP-03 live catalog (partly done)

- [x] Row counts, pending migrations and the WP-04 indexes were measured read-only on production (see MEMORY.md, WP-03).
- [ ] Foreign keys, triggers, photo rights, and `platform.project_limits.per_day` are still `UNKNOWN`.
- [ ] Owner: run `docs/audits/wp03-owner-queries.sql` as the read-only catalog role and paste every result back, including errors. Do not run `INSERT`, `UPDATE`, `DELETE`, `ALTER`, `migrate`, or `down`. Also paste `php artisan migrate:status` without printing `.env`.

## 2026-10-21 — WP-04 REST catalog

- [x] `CatalogApi`, `PublicCatalog`, `ListQuery`, and `PublicSpec` use one route list. List responses keep `data`, `meta`, and `collection`. `limit` stays 1–1000. Sort stays on an allowlist. `Authorization: Bearer geoapi_sk_…` is unchanged.
- [x] `GET /api/v1/models/{id}/years` expands stored `year_start`/`year_end` on generations and modifications. An open `year_end` stops at the current UTC year. `GET /api/v1/modifications/{id}/specs` returns stored engine, transmission, and L/100km consumption. `GET /api/v1/modifications/{id}/equipment` groups `included` / `option` / `not_available` and puts anything else in `unknown`.
- [x] MISSING: dimension and performance columns are not stored (`dimensions` and `performance` are null). Photo list routes are not added: `model_photos` is one row per model, and attribution for `photos` / `generations.photo_urls` is not established. The existing `/api/models/{id}/photo` route stays. A separate `/search` route was not added; `GET /api/v1/modifications` remains the search. VIN was not added. Widget years were still a separate SQL query at the end of WP-04; WP-05 aligns them.
- [x] Colors stay backward compatible: unverified rows are still listed with `verified: false`.
- [ ] Photo rights (WP-03) are still unknown.

## 2026-10-21 — WP-05 widget parity

- [x] `<geoapi-filter>` keeps `brand_id`, `model_id`, `year`, `body_type`, and `generation_id`. `modification_id` is filled only after a modification is chosen and is null before that. The same event name, `bubbles`, and `composed` stay.
- [x] Widget, demo, and picker years use `PickerSelection`, the same bounds as `GET /api/v1/models/{id}/years`: open `year_end` stops at the current UTC year, a start after that year is skipped, and modification spans count. Lists are newest-first. Passing `year` filters generations and modifications. A sibling generation id is not a public id.
- [x] Picker equipment uses the API groups, including `unknown`, and drops duplicate category/name/status rows. The detail card does not show unverified spec JSON (`length_mm`, `source_url`, torque, dimensions). Those fields stay null.
- [x] 429 and 503 retry at most twice, capped at 2 seconds, then the error stays. There is no unbounded retry. `credentials` stay `omit`. The script does not contain `geoapi_sk_`.
- [x] PHPUnit no longer stops at `GET /admin`. The legacy front still exits in production. In tests it returns the status and body. The cached dashboard name is `action.as`.
- [ ] Photo rights are still unknown, so the widget still does not add a photo list.

## 2026-10-21 — WP-06 projects, keys, and quota

- [x] A project is visible only to its owner. Another user's id is not selected, revoked, or issued a key. The secret is the SHA-256 hash in `platform.api_keys`. The full `geoapi_sk_` value is rendered on the create response and is absent from the next cabinet page.
- [x] Guest, revoked, and expired keys are 401 with `WWW-Authenticate: Bearer` and no quota headers. A disabled project, suspended owner, or unverified email is 403. Sanctum `cars:read` or `*` plus `X-CARAPI-PROJECT` must name that user's active project. A session is not a catalog credential. `geoapi_pk_` is not a catalog bearer. A secret key ignores `X-CARAPI-PROJECT` and meters its own project.
- [x] Free limits stay 60/minute, 2,000 successful/day when `per_day` exists, and 10,000 successful/month. A new key does not reset the counter. UTC minute, day, and month windows are calendar boundaries. Success sends `X-RateLimit-*` and `X-Quota-*`. 429 sends `Retry-After`. A missing limit or a failed meter is 503 with no catalog body.
- [x] Widget and catalog share the project counter. A widget request with no matching `Origin` is 403 and spends nothing. Sending an allowed `Origin` is not proof of a browser; `docs/WIDGET_INTEGRATION.md` already says a non-browser client can spoof it.
- [x] Six overlapping requests with one unit left return one 200 and five `MONTHLY_QUOTA_EXCEEDED`. The stored count is 1. Stats are by UTC day and route. `platform.daily_usage` has no key column, so usage is not split by key. Error JSON stays `error.code` and `error.message`. Logs from this path do not include the key.
- [ ] Pro is not for sale. Photo rights are still unknown. VIN was not added.

## 2026-10-21 — WP-07 developer documentation

- [x] `PublicSpec` is the source for `/api/v1/openapi.json`, `/api/v1/postman.json`, and `/guide`. OpenAPI stays 3.0.3, `info.version` 1.2.0, server `https://geoapi.ge`. The official OpenAPI 3.0.3 schema reports 0 errors.
- [x] Every public route documents auth, parameters, errors 401/403/404/405/422/429/503, pagination, Free limits, null coverage, and the fact that SDK, MCP, and CLI packages are not published. Admin routes stay off the public document.
- [x] Colors are documented as every stored row, with `verified: false` when the photo was not confirmed. `/fuel` documents the same filters the code accepts, including `body`, `drive`, and `transmission`. Sort allowlists come from `CatalogApi`.
- [x] Guide samples are cURL, JavaScript, Node, Python, and PHP against `https://geoapi.ge`. The placeholder is `geoapi_sk_…`. Postman uses `{{token}}` and does not contain a real key. The published example for each route returns 200 in the test catalog and matches the response schema.
- [ ] Photo rights are still unknown. VIN was not added. Plan prices are in the WP-11 section.

## 2026-10-21 — WP-08 TypeScript SDK

- [x] `packages/sdk-js` is `@geoapi/sdk` 0.1.0, private, Node 20 or newer. It calls the REST API. Types are generated from `packages/sdk-js/openapi.json`, and that file matches `PublicSpec::document()`. The package is not on the public npm registry. MCP and CLI stay unpublished.
- [x] `GeoAPI` exposes every catalog route, including `brands.list`, `models.byBrand`, `generations.byModel`, `modifications.list`, and `modifications.get`. It retries 429 and 503 at most twice, caps `Retry-After` at 2 seconds, and does not retry 400, 401, 403, 404, 405, or 422. `GeoAPIError.requestId` is null. A `geoapi_sk_` key throws before a request when `window` is defined.
- [x] CI runs the SDK on Node 20 and Node 22. Deploy waits for PHPUnit and the SDK job. A pull request still skips Deploy. The root Vite `npm ci` is unchanged.
- [x] Authentication is documented before the method check: POST without an accepted key is 401, and 405 is returned only after the key is accepted. The 2 000/UTC day cap is documented as inactive in production. No new production query was run. `/guide` keeps the contents list open on a wide screen and collapses it behind "On this page" at 980px and below.
- [ ] Staging smoke was not run. There is no staging token, and this package was not published. Photo rights and VIN stay out. WP-09 was not started.

## 2026-10-21 — WP-09 MCP server

- [x] `packages/mcp-server` is `@geoapi/mcp-server` 0.1.0, private, Node 20 or newer. It is a local stdio process. It calls `@geoapi/sdk` and does not add a Laravel route or a second catalog client. It is not on the public npm registry. CLI stays unpublished.
- [x] Tools: `geoapi_list_brands`, `geoapi_list_models`, `geoapi_list_generations`, `geoapi_list_years`, `geoapi_list_modifications`, `geoapi_get_modification`, `geoapi_get_specs`, `geoapi_get_equipment`, `geoapi_get_coverage`. Years and equipment are included because those REST routes exist. There is no photo tool. `limit` defaults to 20 and cannot exceed 100. Schemas are strict and have no API key field.
- [x] The key is `GEOAPI_API_KEY` in the host environment. `cursor.example.json` uses the placeholder `geoapi_sk_…`. 401 is not retried. 429 and 503 follow the SDK retry, then the tool error has `status`, `code`, `message`, `retryAfter`, and `requestId` null. Tool text and logs do not contain the token. Two clients keep separate keys. The BMW models and one generation prompt is a mock `fetch` test. Staging and production were not called.
- [x] `packages/sdk-js/scripts/smoke.mjs` names the quickstart call that failed and stops. `npm test` checks that reporter and does not run the live script. The owner merge of PR #20 waits 250 ms times the attempt when `Retry-After` is missing, sets `GeoAPIError.retryAfter`, and refuses a secret key in a web worker.
- [x] CI builds the SDK, then tests the MCP package on Node 20 and Node 22. Deploy waits for PHPUnit, the SDK job, and the MCP job. A pull request still skips Deploy. The root Vite `npm ci` is unchanged.
- [ ] Photo rights are still unknown. VIN was not added. Plan prices are in the WP-11 section.

## 2026-10-21 — WP-10 CLI

- [x] `packages/cli` is `@geoapi/cli` 0.1.0, private, Node 20 or newer. It calls `@geoapi/sdk` and does not keep a second HTTP client. It is not published on the public npm registry. The command is `node /absolute/path/to/GEoAPI/packages/cli/dist/index.js`.
- [x] Examples: `geoapi brands list --limit 20 --json`, `geoapi models list --brand bmw`, `geoapi modifications list --model-id 314 --year-from 2020`, and `geoapi coverage`. `--json` is one compact JSON document on stdout. `--pretty` indents it. Rows stay in the API order. `--limit` and `--page` are sent through the SDK.
- [x] The key is `GEOAPI_API_KEY` only. `--api-key` is refused and the value is not printed. `geoapi_sk_` and `carapi_sk_` text is replaced with `[redacted]` on stdout and stderr. Exit 0 is success, 2 is invalid input, 3 is auth, 4 is rate limited, and 5 is network or service. 429 and 503 use the SDK retry, at most twice.
- [x] CI builds the SDK, then tests the CLI on Node 20 and Node 22. Deploy waits for PHPUnit, the SDK job, the MCP job, and the CLI job. A pull request still skips Deploy. The root Vite `npm ci` is unchanged. Staging and production were not called. CI ran on Linux. The program does not spawn a shell.
- [ ] Photo rights are still unknown. VIN was not added. WP-11 is the next section.

## 2026-10-21 — WP-11 BOG billing

- [x] Checkout uses the Bank of Georgia sandbox only (`oauth2-sandbox.bog.ge`, `api-sandbox.bog.ge`). `BOG_CLIENT_ID` and `BOG_CLIENT_SECRET` are empty in `.env.example` and forced empty in `phpunit.xml`. They are not committed. When either is empty, `/account/billing` shows "payments not configured", sends no HTTP request, and leaves the quota unchanged.
- [x] Free is 100 successful requests per UTC month. Basic is 2 000 per UTC month at 59 USD (5 900 cents) and 62 304 cents for a year. Premium is unlimited at 99 USD (9 900 cents) and 104 544 cents for a year. A year is `usd_cents * 12 * 88 / 100`. Prices include VAT. The minute cap stays 60. No new minute or day cap was added. The public OpenAPI document does not contain `59`. The guide says `59 USD` and does not contain `$59`.
- [x] GEL uses the cached National Bank of Georgia USD rate (`billing:nbg-rate`, daily at 09:00). A failed fetch keeps the last stored rate and its date. With no stored rate, a paid checkout does not open an order. Tests use `tests/fixtures/nbg-usd.json` and do not call the bank.
- [x] Registration accepts an individual or a legal entity. A legal entity stores company name, identification code, legal address, and a VAT-payer flag. The invoice PDF includes those fields and "Prices include VAT".
- [x] `POST /billing/bog/callback` checks the RSA signature on the raw body before JSON is read. A bad signature is 400 and stores nothing. `completed` grants once, and only when the BOG order id, GEL currency, and amount match the order. The same status again is 200 and does not grant again. `refunded` restores the quota and entitlement stored on the order. The success and fail redirect URLs do not grant. Stripe's webhook still does not grant. Partial refunds are WP-12.
- [x] Existing users stay at 10 000 per UTC month until that project chooses a plan. The migration adds `grandfathered` without updating `monthly_quota` or any catalog table. Platform DDL is skipped when the environment is `testing`. A missing plan column keeps the previous monthly number. A missing limits row is still 503. A past `plan_ends_at` meters at 100, and `billing:expire` then writes the free row and an audit record. Catalog access stays active.
- [x] The owner pre-applied the billing migration on production as the PostgreSQL table owner before that deploy. This checklist does not include a `migrate:status` transcript. Sandbox credentials were not committed.
- [ ] Photo rights are still unknown. VIN was not added.

## 2026-10-21 — WP-12 hardening

- [x] Invoice PDFs embed `resources/fonts/FiraGO-Regular.ttf` (SIL OFL, `FiraGO-OFL.txt`). A Georgian buyer name round-trips through the ToUnicode map. The VAT line stays `total * 18 / 118`.
- [x] `refunded_partially` keeps the paid plan and entitlement and adds the callback `refund_amount` to `billing_orders.refunded_gel`. The order status stays `paid`, so a later `completed` does not grant again. A second partial for the same amount is a duplicate. A different amount accumulates until the total would pass the order amount. A partial equal to the order amount is stored and still does not revoke the plan. Only `refunded` restores the previous quota. A `refunded` callback with no `refund_amount` records the order amount. A `refund_amount` that is not the order amount does not revoke.
- [x] `BOG_ENV=sandbox` is the default and is forced in `phpunit.xml`. The exact value `production` selects `oauth2.bog.ge` and `api.bog.ge` plus the published production callback key in `config/services.php`. Any other value stays on the sandbox hosts and the sandbox key. A non-empty `BOG_CALLBACK_PUBLIC_KEY` still overrides the selected key. Tests do not call the live bank.
- [x] `GET /ready` runs `SELECT 1` and returns `{"ok":true}` or `{"ok":false}` with no SQL and no exception text. `X-Request-Id` is accepted when it matches `^[A-Za-z0-9._-]{1,64}$` and is otherwise a new UUID. The log context stores that id and does not store the body. An unset `SESSION_SECURE_COOKIE` is Secure only when `APP_ENV` is `production`.
- [x] `docs/ROLLBACK.md` is the rollback checklist. A restore was not executed. No production `EXPLAIN` was run and no index was added. A p95 baseline was not measured. CSRF exemptions for login, register, and account were left in place. Webhook exemptions stay. `APP_DEBUG` was not changed.
- [ ] Photo rights are still unknown. VIN was not added. Production smoke and a proven restore were not run from this change.

## 2026-10-22 — Audit follow-up: CSRF

- [x] Laravel CSRF exemptions narrowed to the legacy platform paths, which check their own session `_csrf` and answer 419. `admin/*` and `account/*` were removed, so `/admin/blog`, `/admin/coming-soon` and `/account/*` now go through Laravel's check (their forms already send `@csrf`).
- [x] `tests/Feature/CsrfProtectionTest.php`: POST without a token gets 419, with a token passes.

## 2026-10-22 — Photo rights closed

- [x] Owner (Shota) confirmed on 2026-10-22 that the model photos are his own, generated by his bot. Photo rights are cleared; the open "photo rights unknown" items above are closed by this entry.
- [x] No photo route was gated by rights in code. `GET /api/models/{id}/photo?generation={id}` already serves verified photos (live check: model 297, generation 2059 → 200 `image/jpeg`, 121 KB). OpenAPI coverage text updated.
- [ ] A paid photo list route in `/api/v1` is not built; that is a new feature, not a re-enable.

## 2026-10-22 — Restore test

- [x] Latest daily dump restored into scratch `carbase_restore_test` in 163 s; row counts match except tables that grew after the dump. Scratch DB dropped. Details in `docs/ROLLBACK.md`.

## 2026-10-22 — Performance baseline

- [x] Light load test (20 concurrent, 60 s) on public endpoints: p95 about 2.0 s remote, ~13 rps ceiling, no 5xx. See `docs/PERFORMANCE.md`.
- [ ] Authenticated `/api/v1` load test, FPM tuning, monitoring.

## 2026-10-22 — Docs sync

- [x] `docs/audits/geoapi-current-state.md` marked as historical WP-00 snapshot. `docs/CATALOG_API.md` has a current-state section. `docs/ROLLBACK.md` intro reflects the restore test and the p95 baseline.

## 2026-10-22 — Public site redesign (designer handoff, Indigo Premium)

- [x] P1 (#30): tokens.css in `app.css` (FiraGO + JetBrains Mono; Noto removed), `resources/css/geoapi-design.css` (handoff ds/site CSS scoped to `.ga`), new header/footer, lockup/favicon/OG ka+en, home, 403/404.
- [x] P2 (#31): pricing (monthly/yearly −12%, GEL by stored NBG rate, „გადახდა“ → `/account/billing`, BOG sandbox unchanged), docs `/guide`, demo, Redoc theme.
- [x] P3: blog index/post, CMS/privacy/terms prose, auth split layout (login + 2FA step, register incl. legal entity, forgot, reset, verify). `public-design.css` no longer loaded by the public layout.
- [ ] Integrations page stays hidden (Proposed, phase 5). Contact form removed by design (hello@geoapi.ge).
- [ ] Docs per-endpoint pages and the right sticky code column from the handoff: kept as one long page with per-section code (deviation).
- [ ] User panel redesign (handoff `panel/`) — next stage.

## 2026-10-22 — User panel redesign (handoff panel/), stage 1

- [x] Cabinet shell: white 248px sidebar (project card, nav with icons, account group, user), 64px topbar with KA/EN; `public/assets/cabinet.css` rewritten to Indigo; shared `x-ga.panel-side`.
- [x] Overview: KPI tiles, 30-day chart + plan card (Free/Basic/Premium from project quota), plan screen no longer shows the stale "Pro — soon".
- [x] Billing (`/account/billing`) moved into the cabinet layout; plan cards with „გადახდა · თვე / წელი“ (BOG, sandbox unchanged), order status badges, Free switch asks for confirmation.
- [ ] Legacy project/playground/explorer pages (`Platform\Http` views), usage page (Proposed), settings (Electrik) and states screens are not yet ported to the new design.

## 2026-10-22 — P5 admin palette
- Legacy admin.css recolored to Indigo tokens + FiraGO (self-hosted /assets/fonts/firago). Full admin handoff layouts (14 screens) not yet rebuilt.

## 2026-10-22 — P6 JetBrains Mono
- jetbrains-mono-400.woff2 (subset of handoff jbm.ttf, same family) is in resources/fonts (Vite, since #30) and now also public/assets/fonts for legacy admin.css. Handoff ships only jbm.ttf, no woff2.
