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

`GEOAPI_API_KEY` stays on the server. The constructor throws if `window` is defined and the key starts with `geoapi_sk_`. The default `baseUrl` is `https://geoapi.ge`. Pass `fetch`, `timeout` (milliseconds, default 30000), and `retries` (default 2) when a caller needs them.

`GeoAPIError` has `status`, `code`, and `requestId`. `requestId` is null because the API response is only `error.code` and `error.message`.

`npm test` uses a mock `fetch` and a local `node:http` server. It does not call production and it does not run a staging smoke test.
