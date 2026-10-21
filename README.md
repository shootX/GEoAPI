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

`vendor/bin/phpunit` runs the test suite. Do not commit `.env` or payment secrets.

Deploy, rollback, and the PHP-FPM layout are in [docs/DEPLOY.md](docs/DEPLOY.md), [docs/ROLLBACK.md](docs/ROLLBACK.md), and [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md).

The SDK, MCP server, and CLI live under `packages/`. They are not published. Each package has its own lockfile and test command. The site deploy still builds this directory with `npm ci` and `npm run build`.
