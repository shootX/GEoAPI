# GeoAPI

Public catalog API and the Georgian/English site at https://geoapi.ge.

## Local setup

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve
```

Requirements: PHP 8.4+ with `ext-bcmath` (the lockfile needs them) and PostgreSQL. `php artisan migrate` also changes the catalog and `platform` schemas, so `.env` needs the catalog connection (`CARAPI_CATALOG_HOST`, `CARAPI_CATALOG_DATABASE`) and the table owner in `CARAPI_CATALOG_ADMIN_USER` / `CARAPI_CATALOG_ADMIN_PASSWORD`; without them migrate stops with "Catalog migration needs CARAPI_CATALOG_ADMIN_USER".

`vendor/bin/phpunit` runs the test suite. Do not commit `.env` or payment secrets.

Deploy, rollback, and the PHP-FPM layout are in [docs/DEPLOY.md](docs/DEPLOY.md), [docs/ROLLBACK.md](docs/ROLLBACK.md), and [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

The SDK, MCP server, and CLI live under `packages/`. They are not published. Each package has its own lockfile and test command. The site deploy still builds this directory with `npm ci` and `npm run build`.
