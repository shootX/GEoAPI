# Rules

- `declare(strict_types=1);` and the existing PHP style. No new framework.
- PostgreSQL-compatible SQL. Do not assume SQLite for catalog queries.
- Do not commit secrets. `.env` stays gitignored. `.env.example` stays empty of passwords.
- Public error bodies do not include exception class, file, line, SQL, or database name.
- Mail is `src/Platform/SmtpMailer.php`. Do not add PHPMailer back.
- Keep `/api/admin` behavior of the separate Electrik app unchanged. This repo's admin UI is `/admin`.
- Do not invent prices, logos, or catalog rows.
- A bug fix gets a regression test that can run without the production server when the bug is in pure PHP.
