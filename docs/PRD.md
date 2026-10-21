# GeoAPI product

Public catalog API and Georgian/English site at https://geoapi.ge. The catalog is the property of the Global auto industry. Customers call the catalog with a secret key (`geoapi_sk_…`) or embed the picker widget with a public key (`geoapi_pk_…`). Keys belong to projects on tariff plans.

## 2026-10-20

- Free plan stays 60 requests/minute and 10 000 successful requests per calendar month. Pro is shown as upcoming and is not sold.
- Human documentation is `/guide`. Machine-readable public paths are `GET /api/v1/openapi.json`. Admin OpenAPI stays at `/api/docs` for an admin session or a Sanctum token with ability `*`.
- The home generation total is `count(*)` of `public.generations` (the same number as the admin list). Chassis groups from `coverage()` are a second caption.
- Email verification stays required for the account area. A mail-transport failure must not return 500. SMTP host, credentials, and `APP_DEBUG` are owner settings and are not changed here.
- Model photos stay same-origin, watermarked, and uncacheable. `GET /getimage` no longer sends `Access-Control-Allow-Origin: *`. JSON clients still receive the image URL; byte responses are watermarked when GD and the font are available.

## 2026-10-21 — billing decision (WP-11)

The owner decided the commercial plans below. Build them in WP-11, after WP-10 and before WP-12. Nothing in this section is implemented. Until that work package, the running Free limits stay 60 requests per UTC minute and 10 000 successful requests per UTC month. The 2 000 successful requests per UTC day limit is not enforced in production today, because the daily check runs only when `platform.project_limits.per_day` exists. Pro is not sold.

- The payment provider is the Bank of Georgia Payments API. Authentication is OAuth client credentials. Only test/sandbox mode is allowed until the owner says otherwise.
- `BOG_CLIENT_ID` and `BOG_CLIENT_SECRET` will be supplied later through the environment. They are never committed.
- The payment callback verifies its signature and is idempotent. A repeated callback does not grant the plan twice.
- The existing Stripe signature check may stay in the tree and stays inactive. It does not grant a plan.
- Plans, priced in USD before display conversion: Free is 100 requests per month; Basic is 2 000 requests per month at 59 USD; Premium is unlimited at 99 USD. Minute and day caps for these three plans were not set in this decision.
- The price shown to the customer is in GEL. Convert the USD price with that day's official National Bank of Georgia rate. A scheduled job caches the bank's public JSON from nbg.gov.ge. If the fetch fails, use the last stored rate. Show the date of the rate.
- A yearly purchase is 12% below twelve times the monthly price.
- The prices include VAT. That assumption stands until the owner says otherwise.
- Registration accepts an individual or a legal entity. A legal entity has a company name, an identification code, a legal address, and a VAT-payer flag.
- Invoice PDFs carry the buyer's details. For a legal entity those are the company name, identification code, legal address, and VAT-payer flag.
- Every payment flow grants the matching entitlement. A refund or an expiry ends that entitlement. An audit log records these changes.
