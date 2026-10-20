# Memory

## 2026-10-20 — Vehicle picker

The catalog tables do not use `year_from` / `year_to` or a 55-column spec table. Years are `year_start` and `year_end`. Generation code is `chassis_code`. Specs live in `modification_specs.specs` (jsonb). Equipment columns are `category`, `name`, `status` (`included`, `option`, `not_available`), and `value`. `model_photos` is one row per model (`model_id` primary key) with `image bytea`, `mime`, and `verified`.

Picker queries always filter by brand, model, generation, or modification id. The modifications list limits rows before joining specs, because one generation can have thousands of rows and `modifications` is about 350k. Equipment is read only for the chosen `modification_id` (`modification_equipment` is about 38M rows, at most a few hundred per modification).

`GET /api/models/{id}/photo` returns the bytea with its mime type only when `verified = true`. The picker JSON routes are same-origin and share the demo IP limit (`cache/demo-limit`, 120/minute). The photo route is GET without an origin check so an `<img>` can load it.

`carapi_catalog_reader` previously could not read `modification_specs`, `modification_equipment`, or `model_photos`. `SELECT` on those three tables was granted on 2026-10-20. No write privilege was added.
