# Rollback

This is the rollback checklist. A restore of the nightly dump into a scratch database was executed on 2026-10-22 (163 s, clean); see "Restore test — 2026-10-22" below.

## Application

1. Note the current git SHA on the server (`git rev-parse HEAD` in the checkout).
2. To return to the previous release, check out that SHA in `DEPLOY_PATH`. The default path is `/www/wwwroot/geoapi.ge`. Do not delete the checkout.
3. Run `composer install --no-dev --optimize-autoloader --no-interaction`.
4. Do not run `php artisan migrate:rollback` against the catalog. A non-reversible migration needs a forward fix. `down()` is not a plan for the large catalog tables.
5. Run `php artisan optimize:clear`, then `config:cache`, `route:cache`, and `view:cache`.
6. Run `npm ci --no-audit --no-fund --loglevel=error` and `npm run build`. `public/build` is not in git.
7. Run `php artisan queue:restart`.
8. If the site is still in maintenance mode, run `php artisan up`. The deploy script already runs `php artisan up` when a remote command fails.
9. Request `https://geoapi.ge/up` and `https://geoapi.ge/ready`. `/up` is the process check. `/ready` runs `SELECT 1` and returns `{"ok":true}` or `{"ok":false}`. It does not return SQL, a host, or an exception message.

The workflow does not edit `.env`, `APP_DEBUG`, `APP_KEY`, or `MAIL_*`. Do not regenerate the production application key as part of a rollback.

## Database and media

A nightly `pg_dump` of `carbase` runs at 02:30 Tbilisi time (`/etc/cron.d/geoapi-backup`, 7-day rotation in `/root/geoapi-backups/daily`). Uploaded media are not in that dump. This document has no connection string and no private key. A restore into production was not executed; a scratch-database restore was (see below).

No `EXPLAIN` was run against production for this change. No index was added from a guess. A remote p95 baseline was measured on 2026-10-22 (`docs/PERFORMANCE.md`). This repository does not ship a metrics collector or an alert destination.

## Billing column

`billing_orders.refunded_gel` is an additive nullable column. Rolling the application back to a release that does not read it leaves the column in place. Do not drop it on the production database as part of an application rollback.

## CSRF

Updated 2026-10-22. Only legacy platform paths (`login`, `register`, `logout`, `account`, `account/projects/*`, `admin`, `admin/users*`, `admin/projects*`, `email/verify`, `email/verification/resend`, `forgot-password`, `reset-password`) skip Laravel's check. Each of those POST handlers in `legacy/src/Platform/Http.php` verifies the session `_csrf` token and answers 419 without it; the legacy admin pages are GET-only (405 otherwise). Laravel routes under `/admin/*` and `/account/*` are checked by Laravel. `api/*`, `widget/*`, `billing/provider-webhook` and `billing/bog/callback` stay exempt (token or signature auth). Rollback: revert the PR that narrowed the list.

## Session cookie

When `SESSION_SECURE_COOKIE` is unset or empty, the session cookie is Secure only if `APP_ENV` is `production`. An explicit `true` or `false` is kept. Tests use `APP_ENV=testing`, so the cookie is not forced Secure there.

## Restore test — 2026-10-22

Executed on production host, into a scratch database. `carbase` was only read (row counts).

- Dump: `/root/geoapi-backups/daily/carbase-20261021T235314Z.dump` (348 MB, custom format, taken 2026-10-22 03:53 Tbilisi time).
- Command: `createdb carbase_restore_test` then `pg_restore --no-owner --no-acl -j 4`. No errors in the log.
- Restore time: **163 s** (about 2 min 43 s) for the whole database.
- Row counts, all 76 tables: 69 identical. The 7 that differ all grew in `carbase` after the dump was taken (ongoing catalog ingest and live traffic): `modifications` / `modification_specs` 395 480 → 408 863, `modification_equipment` 43 885 091 → 45 497 625, `platform.api_keys` 14 → 15, `platform.rate_windows` 58 → 63, `authentication_log` 24 → 25, `migrations` 44 → 45 (WP-12 migration applied after the dump).
- Core tables in the restore: brands 314, models 4 710, generations 22 367, model_photos 634, users 5.
- `carbase_restore_test` was dropped afterwards.

Conclusion: the nightly dump restores cleanly; expect about 3 minutes plus app checks. Data written after the last dump is lost on restore (RPO up to 24 h).

## Billing V2

Updated 2026-10-22. The feature flag `BILLING_V2` ships false. Setting it back to false restores the legacy per-project meter on the next request. Do not drop the new tables as part of an application rollback.

1. Set `BILLING_V2=false` and `BILLING_V2_BUSINESS_RPM=false`, then `php artisan optimize:clear`.
2. `php artisan billing:v2-backfill --rollback` deletes only `origin=backfill` workspaces that have no paid `v2:%` order. It does not update `platform.project_limits`, keys, invoices, or `billing_orders`.
3. Migration `database/migrations/2026_12_31_930000_billing_v2.php` is CREATE TABLE only. It does not ALTER `platform.*` or `billing_orders`. Run it with a role that has CREATE on the application schema. If the app role lacks CREATE, run that migration as the postgres owner. It does not need to own `platform.*` or `billing_orders`.
4. Migrations `2026_12_31_910000_billing_bog.php` and `2026_12_31_920000_billing_refunded_gel.php` still ALTER existing tables and need the postgres owner. Do not re-run them if they are already applied.
5. There is no automatic renewal to cancel. Mock orders stay `mock:<id>` with `TEST-` invoices.
