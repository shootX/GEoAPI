# GeoAPI product

Public catalog API and Georgian/English site at https://geoapi.ge. The catalog is the property of the Global auto industry. Customers call the catalog with a secret key (`geoapi_sk_…`) or embed the picker widget with a public key (`geoapi_pk_…`). Keys belong to projects on tariff plans.

## 2026-10-22 — Billing V2 live (`BILLING_V2=true`)

Free 0 ₾ / 1 000 requests / 1 project / 15 rpm; Starter 51 ₾ / 25 000 / 3 / 60; Growth 159 ₾ / 150 000 / 10 / 120; Business 402 ₾ / 500 000 / 25 / 240 (not sold while `BILLING_V2_BUSINESS_RPM=false`); Enterprise custom via hello@geoapi.ge. One workspace subscription, shared monthly quota, yearly −15% with monthly reset, VAT included, priced in GEL only (no other currency, no exchange rate), no automatic renewal, no unlimited plan. Packs: Starter +10 000 24 ₾, Growth +50 000 54 ₾, Business +100 000 95 ₾ (90 days, after quota). Extra project 14 ₾/month. The Free 100 / Basic / Premium / −12% text below applies only with the flag off or to preserved legacy terms.

## 2026-10-20

- Free plan stays 60 requests/minute and 10 000 successful requests per calendar month. Pro is shown as upcoming and is not sold.
- Human documentation is `/guide`. Machine-readable public paths are `GET /api/v1/openapi.json`. Admin OpenAPI stays at `/api/docs` for an admin session or a Sanctum token with ability `*`.
- The home generation total is `count(*)` of `public.generations` (the same number as the admin list). Chassis groups from `coverage()` are a second caption.
- Email verification stays required for the account area. A mail-transport failure must not return 500. SMTP host, credentials, and `APP_DEBUG` are owner settings and are not changed here.
- Model photos stay same-origin, watermarked, and uncacheable. `GET /getimage` no longer sends `Access-Control-Allow-Origin: *`. JSON clients still receive the image URL; byte responses are watermarked when GD and the font are available.

## 2026-10-21 — billing decision (WP-11)

The owner decided the commercial plans below. WP-11 implements that decision. Bank of Georgia defaults to the sandbox hosts `oauth2-sandbox.bog.ge` and `api-sandbox.bog.ge`. `BOG_ENV=production` selects `oauth2.bog.ge` and `api.bog.ge` and the matching published callback key. Any other value stays on the sandbox. `BOG_CLIENT_ID` and `BOG_CLIENT_SECRET` come from the environment and are not committed. When either is empty, checkout shows "payments not configured", does not call the bank, and does not change a quota. The Stripe signature check stays and still does not grant a plan.

Existing users keep 10 000 successful requests per UTC month until that project chooses a plan. The migration adds `platform.users.grandfathered` as true for rows that already exist and false for later inserts. It does not update `monthly_quota` and it does not rewrite catalog tables. A new user gets the free plan at 100 successful requests per UTC month. Choosing Free on a grandfathered project sets that project to 100. A full refund restores the quota stored on the order. A partial refund records `refunded_gel` and leaves the paid plan in place. A paid `plan_ends_at` in the past is metered at 100 before the hourly expiry job writes the free row. Catalog access stays on; the entitlement `ends_at` is not set in the past. Premium sets `unlimited_month`, and a success then sends `X-Quota-Limit: unlimited`. The minute cap stays 60. Minute and day caps for these three plans were not decided and were not added. The 2 000 successful requests per UTC day limit is still not enforced in production, because the daily check runs only when `platform.project_limits.per_day` exists. A missing limits row is still 503. If the new plan columns are absent, the meter keeps the previous monthly number. No new production measurement was taken. Production was not migrated from this work.

- The payment provider is the Bank of Georgia Payments API. Authentication is OAuth client credentials. The default environment is sandbox. `BOG_ENV=production` is the only value that selects the production hosts and the production callback key.
- `BOG_CLIENT_ID` and `BOG_CLIENT_SECRET` will be supplied later through the environment. They are never committed.
- The payment callback verifies its signature and is idempotent. A repeated callback does not grant the plan twice.
- The existing Stripe signature check may stay in the tree and stays inactive. It does not grant a plan.
- Plans (legacy, flag off): Free is 100 requests per month; Basic is 2 000 requests per month at 159 ₾; Premium is unlimited at 267 ₾. Minute and day caps for these three plans were not set in this decision.
- 2026-10-22: prices are set in GEL only. The National Bank USD rate and its daily job were removed; the bank is charged the listed lari amount.
- A yearly purchase is 12% below twelve times the monthly price.
- The prices include VAT. That assumption stands until the owner says otherwise.
- Registration accepts an individual or a legal entity. A legal entity has a company name, an identification code, a legal address, and a VAT-payer flag.
- Invoice PDFs carry the buyer's details. For a legal entity those are the company name, identification code, legal address, and VAT-payer flag. The PDF embeds FiraGO (SIL OFL) so a Georgian name is drawn.
- Every payment flow grants the matching entitlement. A full refund or an expiry ends that entitlement. A partial refund does not. An audit log records these changes, including the refunded amount.
