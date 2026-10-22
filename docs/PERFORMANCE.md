# Performance baseline

## 2026-10-22 — light load test (production)

Tool: async Python client (httpx), 20 concurrent connections, 60 s per endpoint, run from the Grok Bot box (remote client, over the public internet through the site's front proxy). No API key was used; only anonymous public endpoints were hit. Commit on prod: `fe9a259`.

| Endpoint | Requests | RPS | p50 | p95 | p99 | Errors |
|---|---|---|---|---|---|---|
| `GET /ready` | 842 | 14.0 | 1393 ms | 1990 ms | 2308 ms | 0.12 % (1 client timeout) |
| `GET /api/picker/brands` (widget picker, same-origin) | 716 | 11.9 | 1680 ms | 2134 ms | 3014 ms | 15.8 % — all `429` from the picker's per-IP limit, by design |
| `GET /api/v1/openapi.json` | 752 | 12.5 | 1606 ms | 1981 ms | 2199 ms | 0 % |

Single serial requests from the same client: `/ready` 0.87–1.20 s total. The same request made on the server itself (`--resolve geoapi.ge:443:127.0.0.1`): 0.30–0.47 s. So roughly 0.7 s of each remote request is network/proxy, and about 0.3–0.45 s is the app.

Reading:
- Throughput saturates at about 12–14 requests/s at 20 concurrency for even the cheapest route; latency rises with queueing, not errors. The PHP-FPM pool and the 4-core host are the likely ceiling.
- No 5xx at this load.
- `/api/v1/*` catalog routes with a secret key were not load-tested (no test key was minted); the picker route exercises the same catalog database.

Open: authenticated `/api/v1` baseline, server-side p95 from access logs, FPM pool tuning, and monitoring/alerting (no collector exists).
