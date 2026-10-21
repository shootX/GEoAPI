# GeoAPI — სქემის აუდიტი (WP-00)

ცოცხალი PostgreSQL არ გახსნილა. `SELECT`, `migrate:status` და რაოდენობები არ გაშვებულა. ყოველი ცოცხალი `COUNT(*)` არის `UNKNOWN`. რიცხვები, რომლებიც ძველ `docs/MEMORY.md`-ში წერია, ამ ფაილში ცოცხალ ფაქტად არ გადმოდის.

## 1. რა არის რეპოში და რა არა

`VERIFIED_CODE`. Laravel მიგრაციები `database/migrations/`-შია (12 PHP ფაილი). კატალოგისა და `platform` სქემის `CREATE TABLE` SQL ამ ხეში არ დევს: `**/*.sql` = 0 ფაილი. `docs/PLATFORM_AUTH.md:9` და `docs/PROJECTS_API_KEYS.md:38` ასახელებენ `migrations/001_platform_users.sql` და `migrations/002_projects_api_keys.sql`. ეს ფაილები `MISSING`.

ამიტომ ქვემოთ ორი ფენაა:

- Laravel ცხრილები, რომლებსაც მიგრაცია ქმნის — სვეტები ფაილიდანაა (`VERIFIED_CODE`). გაშვებულია თუ არა პროდაქშენში — `UNKNOWN`.
- `public.*` და `platform.*` — მხოლოდ ის სვეტები, რომლებსაც PHP უკვე კითხულობს ან მიგრაცია `ADD COLUMN IF NOT EXISTS`-ით ეხება. სრული ცხრილის DDL `UNKNOWN`.

## 2. Laravel მიგრაციები

| ფაილი | რას აკეთებს | ტეგი |
|---|---|---|
| `0001_01_01_000000_create_users_table.php` და cache/jobs | Laravel-ის users, sessions, cache, jobs | `VERIFIED_CODE` ფაილი არსებობს; ცოცხალი სტატუსი `UNKNOWN` |
| `2026_10_19_162233_create_permission_tables.php`, `teamwork_setup_tables.php` | Electrik permission და teams | `VERIFIED_CODE` |
| `2026_10_19_162235_create_passkeys_table.php`, `create_personal_access_tokens_table.php` | passkeys, Sanctum tokens | `VERIFIED_CODE` |
| `2026_10_20_000000_create_admin_content_tables.php:13-50` | `pages`, `contents`, `settings`, `media_items` | `VERIFIED_CODE` |
| `2026_10_20_160000_add_coming_soon.php:15-28` | `coming_soon_signups`; settings key `coming_soon` = false | `VERIFIED_CODE` |
| `2026_10_20_180000_create_blog_tables.php:13-47` | `blog_categories`, `blog_tags`, `blog_posts`, pivot | `VERIFIED_CODE` |
| `2026_10_20_210000_catalog_api_parity.php` | იხილეთ §3. ტესტში ცარიელია | `VERIFIED_CODE` |
| `2026_12_31_900000_create_api_products.php` | იხილეთ §4. თარიღი მომავალია | `VERIFIED_CODE` ფაილი; გაშვება `UNKNOWN` |

`2026_10_20_210000_catalog_api_parity.php:16-18` ტესტის გარემოში მაშინვე ბრუნდება. PHPUnit-ის მწვანე გაშვება ამ მიგრაციის PostgreSQL ნაწილს არ ამოწმებს (`VERIFIED_TEST_LOCAL` მხოლოდ იმაზე, რომ ტესტი მას არ აგდებს).

`2026_12_31_900000_create_api_products.php:14-18` ამატებს `users.legacy_platform_user_id` nullable unique. `:20-27` ქმნის `legacy_id_maps`. `:29-78` ქმნის `api_products`, `api_plans`, `api_entitlements`, `payment_events`. `payment_events`-ზე uniqueა `(provider, event_id)` (`:77`). Seed `:81-104`: პროდუქტები `cars` (published), `cities` და `parking` (draft); გეგმა `cars-included` 60/წუთში და 10000/თვე, `price_minor` null. მომავლის თარიღი არ ნიშნავს, რომ მიგრაცია არ გაშვებულა. `migrate:status` არ გაშვებულა, ამიტომ სტატუსი `UNKNOWN`.

## 3. კატალოგი `public` — სვეტები, რომლებსაც კოდი იყენებს

`VERIFIED_CODE` როგორც წაკითხული სვეტები. ცხრილის სრული სია და ტიპები ცოცხალ `information_schema`-ში `UNKNOWN`.

`brands` (`app/Catalog/PublicCatalog.php:43`): `id`, `slug`, `name`, `synonyms`, `is_lcv_or_commercial`, `logo_url_light`, `logo_url_dark`, არასავალდებულო `updated_at`.

`models` (`PublicCatalog.php:82`): `id`, `brand_id`, `slug`, `name`, `body_type`, არასავალდებულო `updated_at`.

`model_markets` (`PublicCatalog.php:781`): `model_id`, `market_slug`.

`generations` (`PublicCatalog.php:123` და `CatalogRepository.php:449-451`): `id`, `model_id`, `slug`, `name`, `year_start`, `year_end`, `body_type`, `chassis_code`, `steering_wheel`, `photo_urls` (jsonb, მხოლოდ pgsql coverage-ში), არასავალდებულო `updated_at`.

`modifications` (`PublicCatalog.php:210-212`): `id`, `name`, `engine_volume_cc`, `transmission`, `drive_type`, `body_type`, `year_start`, `year_end`, `generation_id`, `model_id`, არასავალდებულო `updated_at`.

`modification_specs`: კოდი კითხულობს `specs` jsonb-ს, მათ შორის `fuel_type`, `fuel_city_l`, `fuel_highway_l`, `fuel_combined_l`, `power_hp` (`PublicCatalog.php` fuel/engines select-ები და `VehiclePickerRepository.php:232`). სრული jsonb სქემა `UNKNOWN`.

`modification_equipment`: `docs/MEMORY.md:5` ასახელებს `category`, `name`, `status`, `value`. picker-ის `equipment()` ამ ცხრილს კითხულობს (`VehiclePickerRepository.php:431`). ამ აუდიტმა ცოცხალი სტრიქონი არ დაითვალა. რაოდენობა `UNKNOWN`.

`model_photos` (`PublicCatalog.php:394`): `model_id`, `color`, `verified`, არასავალდებულო `updated_at`. `docs/MEMORY.md:5` ამატებს `image bytea` და `mime`. bytea ამ აუდიტში არ წაკითხულა.

`photos`: coverage ითვლის `public.photos`-ს (`CatalogRepository.php:448`). სვეტები ამ ფაილში სრულად არ ჩამოთვლილა. `UNKNOWN` სვეტების სია.

ინდექსები და ტრიგერები, რომლებსაც `2026_10_20_210000_catalog_api_parity.php:60-109` დაამატებდა, თუ მიგრაცია გაეშვა: `updated_at` სვეტი და `carapi_*_updated_at` ტრიგერი `brands`, `models`, `generations`, `modifications`, `model_photos`-ზე; `carapi_specs_touch_modification` `modification_specs`-ზე; ჩამოთვლილი btree ინდექსები year/body/drive/transmission/volume/updated_at. გაშვებულია თუ არა — `UNKNOWN`. მფლობელი უნდა იყოს `CARAPI_CATALOG_ADMIN_USER`, არა `carapi_catalog_reader` (`:130`).

## 4. `platform` — სვეტები, რომლებსაც კოდი იყენებს

`VERIFIED_CODE` გამოყენებაზე. ცოცხალი რაოდენობა `UNKNOWN`.

| ცხრილი | სვეტები კოდიდან | წყარო |
|---|---|---|
| `platform.users` | `id`, `email`, `status`, `auth_version`, `email_verified_at`, `password_hash`, `first_name`, `last_name` | `ProvisionPlatformUser.php:44`, `docs/PLATFORM_AUTH.md:11`, `ApiKeys.php:47` |
| `platform.login_attempts` | `email`, `ip`, `created_at` | `UserRepository.php:100-130` |
| `platform.projects` | `id`, `user_id`, `name`, `website`, `status` | `Projects.php` SQL და `ApiKeys.php:49` |
| `platform.api_keys` | `id`, `project_id`, `name`, `key_selector`, `secret_hash`, `scope`, `expires_at`, `revoked_at`, `last_used_at` | `ApiKeys.php:46-73`, `ProjectRepository.php:109` |
| `platform.project_limits` | `project_id`, `per_minute`, `monthly_quota`, არასავალდებულო `per_day` | `ProjectRepository.php:148-154`, parity migration `:116` |
| `platform.widgets` | `project_id`, `public_key`, `enabled`, `allowed_origins`, `updated_at` | `WidgetRepository.php:26-125` |

`per_day` default 2000 მხოლოდ მაშინ იწერება, როცა სვეტი არსებობს (`ProjectRepository.php:148-154`). თუ სვეტი არ არის, `PostgresUsageStore.php:222-240` დღიურ ლიმიტს არ ამოწმებს და 503-ს არ აბრუნებს. სვეტი პროდაქშენში არსებობს თუ არა — `UNKNOWN`. სანამ ეს `UNKNOWN`-ია, დღიური კაპი 2000 პროდაქშენის ფაქტად არ ჩაიწეროს. 2026-10-21, მფლობელის დაზუსტება: დღიური ზღვარი პროდაქშენში ამჟამად არ მოქმედებს. ეს ახალი სქემის გაზომვა არ არის.

`docs/TASKS.md:58` მფლობელს სთხოვს `php artisan migrate --force`-ს `/www/wwwroot/geoapi.ge`-დან კატალოგის მფლობელით. ეს ბრძანება ამ აუდიტში არ გაშვებულა.

## 5. ორი იდენტობა

`VERIFIED_CODE`. `docs/ARCHITECTURE.md:3` და `users.legacy_platform_user_id` (`2026_12_31_900000_create_api_products.php:15-17`) Laravel `users`-ს აკავშირებს `platform.users`-თან. `AppServiceProvider.php:46-49` რეგისტრაციაზე, შესვლაზე, გასვლასა და verified ივენთზე იძახებს `ProvisionPlatformUser`-ს. ცხრილების შერწყმა ამ აუდიტში არ გაკეთებულა და არ უნდა გაკეთდეს ბრმად.

## 6. რაოდენობები

| ობიექტი | რაოდენობა | ტეგი |
|---|---|---|
| brands, models, generations, modifications, specs, equipment, photos, model_photos | არ დათვლილა | `UNKNOWN` |
| platform users, projects, keys | არ დათვლილა | `UNKNOWN` |
| Laravel `migrate:status` | არ გაშვებულა | `UNKNOWN` |
| ისტორიული რიცხვები MEMORY/TASKS-ში | ხელახლა არ შემოწმებულა | არ გამოიყენება როგორც `VERIFIED_PRODUCTION` |
