# Architecture

This repository is a PHP 8.2+ front controller (`public/index.php`) with PSR-4 `Carapi\` → `src/`. There is no Laravel application in git.

| Path | Role |
|---|---|
| `src/Catalog` | PostgreSQL catalog, `/api/v1`, `/api/picker` |
| `src/Platform` | Accounts, projects, keys, sessions, SMTP |
| `src/Widget` | `POST /widget/v1/{public-key}/...` and `/widget/v1/demo` |
| `src/Http/Failure.php` | Uncaught errors: generic 500, unique violation 409, no SQL or paths |
| `migrations/` | Platform schema. Catalog tables already exist in `carbase` |

Catalog and platform use separate PostgreSQL roles. The app reads environment variables. It does not read `.env`.

## Production note

https://car.socialsave.cc currently answers from Laravel Electrik at `/opt/carapi-electrik` (Livewire, Sanctum `/api/admin`, session cookie `carapi-session`). That tree is not this git repo. Electrik loads this code as `legacy/` (`SmtpMailer`, `Session`). Fixes here apply to Electrik only after that legacy copy is deployed, and they do not set Electrik's `APP_DEBUG`.

`generations_count` on the home page is the grouped coverage count (model + chassis code, or name when chassis is empty), not a raw `count(*)` of `generations`.
