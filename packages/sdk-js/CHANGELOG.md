# Changelog

## 0.1.0

- Typed client for the GeoAPI catalog REST routes. Types come from `openapi.json`, which is a snapshot of `PublicSpec`.
- Retries 429 and 503 at most twice. `Retry-After` is seconds, and the wait is capped at 2 seconds. A zero `Retry-After` does not wait; a missing one waits 250 ms × attempt. The final error carries `retryAfter`. Secret keys are refused in browser pages and web workers.
- Does not retry 400, 401, 403, 404, 405, or 422.
- `GeoAPIError` carries `status`, `code`, and `requestId`. `requestId` is always null because the API does not send one.
- Refuses a `geoapi_sk_` key when `window` is defined. The key is not written into the URL or the error message.
- This package is private to the repository. It is not on the public npm registry.
