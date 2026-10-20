# GeoAPI product

Public catalog API and Georgian/English site at https://geoapi.ge. Data comes from drom.ru. Customers call the catalog with a secret key (`carapi_sk_…`) or embed the picker widget with a public key (`carapi_pk_…`). Keys belong to projects on tariff plans.

## 2026-10-20

- Free plan stays 60 requests/minute and 10 000 successful requests per calendar month. Pro is shown as upcoming and is not sold.
- Human documentation is `/guide`. Machine-readable public paths are `GET /api/v1/openapi.json`. Admin OpenAPI stays at `/api/docs` for an admin session or a Sanctum token with ability `*`.
- The home generation total is `count(*)` of `public.generations` (the same number as the admin list). Chassis groups from `coverage()` are a second caption.
- Email verification stays required for the account area. A mail-transport failure must not return 500. SMTP host, credentials, and `APP_DEBUG` are owner settings and are not changed here.
- Model photos stay same-origin, watermarked, and uncacheable. `GET /getimage` no longer sends `Access-Control-Allow-Origin: *`. JSON clients still receive the image URL; byte responses are watermarked when GD and the font are available.
