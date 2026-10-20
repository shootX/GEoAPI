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
- [x] Widget `carapi:change` includes the chosen `generation_id`. `modification_id` stays null because the widget has no modifications route.
- [x] English privacy and terms, Georgian password-reset status, home row count plus chassis groups.
- [ ] Owner: set `APP_DEBUG=false` in production and configure SMTP, including installing PHPMailer if `CARAPI_MAIL_*` is used.
- [ ] Owner: rotate any admin token that was pasted into the audit chat. It is not in this repository.
- [ ] Logos and model photos are still empty until ingest supplies files. No import was added.
