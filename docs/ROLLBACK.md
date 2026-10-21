# Rollback

This is the rollback checklist. A database restore was not executed while writing it. This file is not a record of a successful production restore.

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

Backups of the database and of uploaded media are an owner task. This document has no connection string and no private key. A restore into production was not executed. Practice a restore on a copy the owner provides before relying on a backup.

No `EXPLAIN` was run against production for this change. No index was added from a guess. A p95 latency baseline was not measured. This repository does not ship a metrics collector or an alert destination.

## Billing column

`billing_orders.refunded_gel` is an additive nullable column. Rolling the application back to a release that does not read it leaves the column in place. Do not drop it on the production database as part of an application rollback.

## CSRF

`login`, `register`, `account`, and `admin` stay outside the framework CSRF check. Those posts were not retested in a browser for this change, and removing the exemption could lock the account pages. `billing/provider-webhook` and `billing/bog/callback` stay exempt because Stripe and the bank do not send a session token. No further path was added to the exemption list.

## Session cookie

When `SESSION_SECURE_COOKIE` is unset or empty, the session cookie is Secure only if `APP_ENV` is `production`. An explicit `true` or `false` is kept. Tests use `APP_ENV=testing`, so the cookie is not forced Secure there.
