# პლატფორმის ავტორიზაცია

production-ში `CARAPI_PLATFORM_ENABLED=1`. საჯარო რეგისტრაცია PostgreSQL-ის ნაკადია. ძველი SQLite ფორმა საჯაროდ აღარ იხსნება. ისტორიულად, ჩართვამდე, საიტი ამ ნაკადს არ იყენებდა.

`CARAPI_CATALOG_ENABLED` production-ში 1-ია.

## სქემა

მიგრაცია: `migrations/001_platform_users.sql`. აღრიცხვა: `platform.schema_migrations`.

`platform.users`: `id`, `first_name`, `last_name`, `email`, `password_hash`, `status` (`active` ან `suspended`), `email_verified_at`, `created_at`, `updated_at`, `last_login_at`. დროები `timestamptz`-ია. ელფოსტის უნიკალურობაა `lower(email)` ინდექსით. `public` კატალოგი არ იცვლება.

`platform.login_attempts` ინახავს წარუმატებელ შესვლას ელფოსტით და IP-ით, რომ ახალი cookie ლიმიტს არ გვერდს უვლიდეს.

გაშვება ადმინისტრაციული კავშირით, არა runtime როლით:

```bash
export CARAPI_PLATFORM_ADMIN_HOST=127.0.0.1
export CARAPI_PLATFORM_ADMIN_PORT=5432
export CARAPI_PLATFORM_ADMIN_DATABASE=carbase
export CARAPI_PLATFORM_ADMIN_USER=...
export CARAPI_PLATFORM_ADMIN_PASSWORD=...
php bin/migrate.php
```

განმეორებითი გაშვება უსაფრთხოა. ცხრილები გვერდის გახსნისას არ იქმნება.

სამუშაო `carbase`-ზე მიგრაცია გაშვებულია. `platform.users` ცარიელია. `data/carapi.sqlite` არ არსებობს, გადასატანი მომხმარებელი არ არის.

## კონფიგურაცია

აპლიკაცია `.env`-ს არ კითხულობს.

| ცვლადი | აზრი |
|---|---|
| `CARAPI_PLATFORM_ENABLED` | `0` ნაგულისხმევი |
| `CARAPI_PLATFORM_HOST` | ჰოსტი |
| `CARAPI_PLATFORM_PORT` | პორტი |
| `CARAPI_PLATFORM_DATABASE` | ბაზა |
| `CARAPI_PLATFORM_USER` | runtime როლი |
| `CARAPI_PLATFORM_PASSWORD` | პაროლი |
| `CARAPI_PLATFORM_SESSION_PATH` | სესიის ცალკე დირექტორია |
| `CARAPI_PLATFORM_IDLE_SECONDS` | უმოქმედობა, ნაგულისხმევი 1800 |
| `CARAPI_PLATFORM_ABSOLUTE_SECONDS` | სრული ვადა, ნაგულისხმევი 43200 |
| `CARAPI_PLATFORM_ATTEMPT_LIMIT` | ელფოსტაზე, ნაგულისხმევი 8 / 900 წამი |
| `CARAPI_PLATFORM_IP_ATTEMPT_LIMIT` | IP-ზე, ნაგულისხმევი 30 |

ადმინისტრაციული მიგრაციის ცვლადებია `CARAPI_PLATFORM_ADMIN_*`. ისინი runtime პულში არ არის. საიდუმლოები GeoAPI-ის ცალკე FPM გარემოშია (`docs/DEPLOYMENT.md`), საერთო პულში არა.

runtime როლია `carapi_platform_app`: `LOGIN`, არა სუპერმომხმარებელი და არა მფლობელი, DDL არ შეუძლია. აქვს `SELECT`, `INSERT`, `UPDATE` `platform.users`-ზე, `SELECT`, `INSERT`, `DELETE` `platform.login_attempts`-ზე და შესაბამისი sequence-ის `USAGE`. კატალოგზე `INSERT` არ აქვს. `carapi_catalog_reader`-ს `platform.users` არ ხსნის.

## მარშრუტები და სესია

ჩართულ რეჟიმში:

- `GET/POST /register`
- `GET/POST /login`
- `POST /logout`
- `GET /account`
- `GET /auth/me`

ველები: სახელი, გვარი, ელფოსტა, პაროლი, გამეორება. პირადი ნომერი, ტელეფონი და კომპანიის ველები ამ ნაკადში არ არის. ელფოსტა იჭრება და `lowercase` ხდება. პაროლი არ იჭრება: 15–128 სიმბოლო, Unicode და ჰარები დაშვებულია. ჰეში არის `PASSWORD_ARGON2ID`. რეგისტრაციისას `email_verified_at` ცარიელი რჩება, სანამ მომხმარებელი ბმულს არ დაადასტურებს. წერილის გაგზავნა: `docs/EMAIL_AND_PASSWORD_RESET.md`. Production-ში SMTP მიერთებულია (`docs/EMAIL_AND_PASSWORD_RESET.md`). სამი ფუნქციური დროშა production-ში 1-ია.

სესიის cookie-ის სახელია `carapi_platform`. `HttpOnly`, `SameSite=Lax`, HTTPS-ზე `Secure`. სესიაში მხოლოდ მომხმარებლის id და ვადის ნიშნულებია. შესვლისას session id ახლდება. უმოქმედობა 30 წუთი, სრული ვადა 12 საათი. `suspended` მომხმარებელი არსებული სესიითაც ვერ შედის. `GET /logout` არ გამოდის. უცნობი ელფოსტა და არასწორი პაროლი ერთნაირ შეტყობინებას იძლევა. ბაზის შეცდომა არის 503, SQL-ის გარეშე, SQLite-ზე გადართვის გარეშე.

კაბინეტი აჩვენებს სახელს, ელფოსტას, პროექტებს, სერვერულ გასაღებებს, ჩასასმელ ფილტრს და რეალურ მოხმარებას.

## ტესტები

`php tests/platform_auth_test.php` იზოლირებულ ბაზაზე გავიდა: რეგისტრაცია, Unicode პაროლი, დუბლიკატი რეგისტრის სხვაობით, CSRF, session id-ის ცვლილება, logout, suspended სესია, ლიმიტი ახალი cookie-ით, უმოქმედობის ვადა, ბაზის 503. SQLite არ შექმნილა. სამუშაო `carbase`-ში სატესტო მომხმარებელი არ ჩაწერილა.

`tests/catalog_api_test.php` ამის შემდეგაც გავიდა.

## საჯარო ჩართვამდე

ცალკე PHP-FPM უკვე დგას (`docs/DEPLOYMENT.md`). ელფოსტის დადასტურება და პაროლის აღდგენა აგებულია. საჯარო ჩართვამდე რჩება რეალური SMTP და სამი ფუნქციური დროშის ჩართვა.

პროექტები და სერვერული გასაღებები: `docs/PROJECTS_API_KEYS.md`. ელფოსტის დადასტურება და პაროლის აღდგენა: `docs/EMAIL_AND_PASSWORD_RESET.md`. ჩასასმელი ფილტრი: `docs/WIDGET_INTEGRATION.md`.
