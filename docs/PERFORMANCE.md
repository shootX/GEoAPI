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

## 2026-10-22 — Business 240 rpm load test (decision: keep `BILLING_V2_BUSINESS_RPM=false`)

Setup: k6 0.54 on the server (to nginx on 127.0.0.1:443, real TLS + PHP-FPM 8.4 + PostgreSQL), constant arrival rate, secret key of test account 2 with temporarily raised workspace/project limits and `CARAPI_IP_PER_MINUTE` (all reverted). Mix: 15% `/api/v1/brands`, 25% `brands/{slug}/models`, 25% `models/{id}/generations`, 20% `generations/{id}/modifications`, 15% `modifications/{id}`. 4 vCPU, 7.9 GB RAM. Monitor every 5 s: CPU, RAM, php-fpm processes, PostgreSQL connections; neighbour site probed before/after each stage.

| Stage | Duration | Requests | Errors | p50 | p95 | p99 | CPU avg / max | PG conns max | Neighbour site |
|---|---|---|---|---|---|---|---|---|---|
| 2 rps (local) | 2 min | 270 | 0% | 562 ms | 813 ms | 1.05 s | 35% / 57% | 19 | 31 ms → 32 ms |
| 4 rps (local) | 2 min | 510 | 0% | 591 ms | 906 ms | 1.26 s | 60% / 83% | 19 | 55 ms → 33 ms |
| 6 rps (local) | 1 min | 390 | 0% | 641 ms | 1.60 s | 1.97 s | 70% / 95% | 20 | 49 ms → 35 ms |
| 4 rps, OPcache on (local) | 2 min | 510 | 0% | 599 ms | 1.01 s | 1.53 s | 61% / 84% | 19 | 29 ms → 43 ms |
| 1 rps (internet, from box) | 1 min | 90 | 0% | 726 ms | 946 ms | 1.22 s | — | — | — |

Stopped before 8/16/32 rps: CPU was already >85% at 6 rps and would harm other sites on this host.

Findings:
- Sustainable throughput is about **5 rps** total (~300 rpm) for the whole server. One Business workspace at 240 rpm = 4 rps would take ~80% of it, so the required 4× headroom and p95 < 1 s at 16 rps are not met.
- The bottleneck is PHP CPU (~0.3 CPU-seconds per request, php-fpm ~130% CPU at 4 rps). PostgreSQL is light (~6% CPU, ≤20 connections). Even `/up` (no DB) takes ~300 ms, so the cost is the per-request framework/bootstrap path, not catalog queries.
- OPcache (installed, disabled in `/www/server/php/84/etc/php.ini`) was enabled for one run and gave no measurable gain, so it was reverted (global for all php 8.4 sites). Config, routes and views are cached; events are not. `APP_ENV=local` (open question).

Cheapest next steps, in order:
1. Profile one `/up` request in php-fpm (SPX or Xdebug profiler on a copy) to find the ~300 ms; likely a middleware/service provider doing file or session work on every request.
2. `php artisan event:cache`; `APP_ENV=production` (needs approval); session/cache to Redis instead of files.
3. Long-running workers (Laravel Octane on FrankenPHP/Swoole) remove bootstrap cost per request; expected multi-× throughput.
4. Re-run this test; enable Business only at ≥16 rps with p95 < 1 s.
