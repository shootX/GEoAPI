# Architecture

Laravel 12 application. PostgreSQL holds the catalog. A second identity lives in `platform.users` and is linked by `users.legacy_platform_user_id`.

## 2026-10-20

Request path:

1. `routes/web.php` is the Laravel web entry, including the branded 404 fallback.
2. After boot, `AppServiceProvider` loads `routes/admin-api.php`, then `routes/product.php`, then the legacy front under the `web` middleware.
3. Admin JSON is Sanctum at `/api/admin`. Shapes stay `{data, meta}` and `{error: {code, message}}`. Duplicate unique keys return 409, not a SQL error.
4. Public pages are Blade. A published `pages` row for the same slug replaces the Blade body. An empty CMS table leaves the Blade page in place.
5. Catalog reads and the widget still run through `legacy/`. The platform session cookie is `geoapi_platform`. It is excluded from Laravel cookie encryption and is accepted only as 32 hex characters.
6. Public counts are cached at `legacy/cache/public-counts.json` for 120 seconds. The file is gitignored.
7. `config/cors.php` allows origins from `CORS_ALLOWED_ORIGINS`. An empty value sends no `Access-Control-Allow-Origin` on `/api/*`. The widget still reflects only an origin stored on the project.

Image copies:

- `GET /api/models/{id}/photo` requires a same-origin image fetch (`Sec-Fetch-Dest: image`), draws the `GEOAPI.GE` watermark, and sets `Cross-Origin-Resource-Policy: same-origin`, `no-store`, and `nosniff`.
- `GET /getimage` does not allow cross-origin canvas reads. A CDN `image_url` is still a redirect so existing API clients keep working. Hotlinking with an `<img>` tag still works; blocking that would break embeds.
