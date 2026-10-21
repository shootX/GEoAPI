# @geoapi/sdk

Private TypeScript client for the GeoAPI catalog. Version 0.1.0 lives in this repository as `packages/sdk-js`. It is not on the public npm registry.

The client calls the REST API. It does not keep a second copy of the catalog rules. Node.js 20 or newer.

```ts
import { GeoAPI } from "@geoapi/sdk";

const api = new GeoAPI({ apiKey: process.env.GEOAPI_API_KEY! });
const brands = await api.brands.list({ limit: 20 });
const models = await api.models.byBrand("bmw");
const generations = await api.generations.byModel("314");
const modifications = await api.modifications.list({ model_id: "314", year_from: 2020 });
const detail = await api.modifications.get("12345");
```

`GEOAPI_API_KEY` stays on the server. The constructor throws if it runs in a browser page or web worker (`window`, `document`, or `importScripts` exists) and the key starts with `geoapi_sk_`. Never ship a secret key to a browser; call the API from your server, or use the widget with `geoapi_pk_`. The default `baseUrl` is `https://geoapi.ge`. Pass `fetch`, `timeout` (milliseconds, default 30000), and `retries` (default 2) when a caller needs them.

429 and 503 are retried `retries` times. The wait is the server's `Retry-After`, capped at 2 seconds, or 250 ms × attempt when the header is missing. A minute-limit 429 usually says `Retry-After` up to 60, so the final `GeoAPIError` carries `retryAfter` (seconds) for the caller to wait properly.

`GeoAPIError` has `status`, `code`, `retryAfter`, and `requestId`. `requestId` is null because the API response is only `error.code` and `error.message`.

`npm test` uses a mock `fetch` and a local `node:http` server. It does not call production and it does not run a staging smoke test.

`node scripts/smoke.mjs` runs the five quickstart calls against `GEOAPI_BASE_URL` or `https://geoapi.ge`. If a call throws, stderr says `smoke failed at <call>` with the status, code, and `retryAfter`. The process exits 1. The API key is not printed. `npm test` only checks that reporter; it does not run the live script.
