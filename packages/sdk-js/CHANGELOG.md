# Changelog

## 0.1.0

- Typed client for the GeoAPI catalog REST routes. Types come from `openapi.json`, which is a snapshot of `PublicSpec`.
- Retries 429 and 503 at most twice. `Retry-After` is seconds, and the wait is capped at 2 seconds. A zero `Retry-After` does not wait; a missing one waits 250 ms × attempt. The final error carries `retryAfter`. Secret keys are refused in browser pages and web workers.
- Does not retry 400, 401, 403, 404, 405, or 422.
- `GeoAPIError` carries `status`, `code`, `retryAfter`, and `requestId`. `requestId` is always null because the API does not send one. `retryAfter` is the header seconds on 429 and 503, otherwise null.
- Refuses a `geoapi_sk_` key in a browser page or web worker (`window`, `document`, or `importScripts`). The key is not written into the URL or the error message.
- This package is private to the repository. It is not on the public npm registry.
- `scripts/smoke.mjs` names the call that failed and then stops. `npm test` checks that reporter only. The live script does not run unless you start it.
