# Memory

## 2026-10-20 — QA audit, critical

The live host is Laravel Electrik (`/opt/carapi-electrik`), not this repository. Electrik's `APP_DEBUG` still prints stack traces until that server sets `APP_DEBUG=false`. This repo only controls its own front controller and the legacy `Session` / `SmtpMailer` files Electrik includes.

`.env.example` had a non-empty `CARAPI_MAIL_PASSWORD`. It is cleared in git. The mailbox password should be rotated because the old value remains in git history. The real value belongs only in `/www/server/carapi/mail.env`.

Verification mail failure no longer aborts registration. The account page asks the user to resend. Secret keys still require a verified email.

## 2026-10-20 — Vehicle picker

The catalog tables do not use `year_from` / `year_to` or a 55-column spec table. Years are `year_start` and `year_end`. Generation code is `chassis_code`. Specs live in `modification_specs.specs` (jsonb). Equipment columns are `category`, `name`, `status` (`included`, `option`, `not_available`), and `value`. `model_photos` is one row per model (`model_id` primary key) with `image bytea`, `mime`, and `verified`.

Picker queries always filter by brand, model, generation, or modification id. The modifications list limits rows before joining specs, because one generation can have thousands of rows and `modifications` is about 350k. Equipment is read only for the chosen `modification_id` (`modification_equipment` is about 38M rows, at most a few hundred per modification).

`GET /api/models/{id}/photo` returns the bytea with its mime type only when `verified = true`. The picker JSON routes are same-origin and share the demo IP limit (`cache/demo-limit`, 120/minute). The photo route is GET without an origin check so an `<img>` can load it.

`carapi_catalog_reader` previously could not read `modification_specs`, `modification_equipment`, or `model_photos`. `SELECT` on those three tables was granted on 2026-10-20. No write privilege was added.
