# Rules

## 2026-10-20

- Do not change `APP_DEBUG` or mail/SMTP environment values in this repository. The owner sets those.
- If PHPMailer is missing or SMTP rejects the message, registration and verification must continue. The exception is `Geoapi\Platform\UnavailableException`, not an uncaught error.
- Keep `/api/admin` backward-compatible: same paths, Sanctum bearer tokens, integer ids on admin catalog rows. Public picker ids stay strings.
- Keep `/api/docs` working for an operator session and for an admin token. Everyone else gets JSON 404 with no stack trace.
- Do not publish a secret, a token, or a password. Settings keys matching `password`, `secret`, `token`, or `credential` are rejected.
- Do not seed published CMS pages over the Blade privacy, terms, home, or pricing pages.
- `source_url` may be an empty string. Do not require it on brand, model, generation, or modification create.
- `GET /api/admin/equipment` requires `modification_id`. The nested `GET /api/admin/cars/{id}/equipment` route is unchanged.
- New Georgian copy must not use the typo „ამრჩევი“.
