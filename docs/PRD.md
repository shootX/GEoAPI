# PRD

CarAPI is a car-catalog API for developers. A project gets a secret server key (`carapi_sk_`) and a public widget key (`carapi_pk_`). The free limit stated in the product is 60 requests per minute and 10 000 successful requests per calendar month per project. Paid prices are not defined in this repository.

## Users

- A developer registers, confirms email, creates a project, and calls `GET /api/v1`.
- A site embeds `/widget/v1/carapi-filter.js`. The widget selects brand, model, year, and body. `generation_id` and `modification_id` stay null. The full picker is `/demo`.
- An operator uses `/admin` in this app. The live host also runs a separate Laravel Electrik admin API that is not in this repository.

## Public surface

`/`, `/guide`, `/demo`, `/pricing`, `/privacy`, `/terms`, `/login`, `/register`, account and project pages, `/api/v1`, `/api/picker`, `/widget/v1`, `/getImage`.

## Out of scope here

Stripe prices, logo files, and CMS rows. Do not invent prices or overwrite catalog content.
