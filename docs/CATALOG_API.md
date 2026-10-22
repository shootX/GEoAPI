# კატალოგის შიდა API

production-ში `CARAPI_CATALOG_ENABLED=1`. მარშრუტები ითხოვს `Authorization: Bearer`-ს და პროექტის ლიმიტს. გასაღების გარეშე პასუხია 401. დეტალები: `docs/PROJECTS_API_KEYS.md`, `docs/USAGE_AND_LIMITS.md`.

`GET /getImage` ამ კონტრაქტში არ შედის. ის ძველი გარე წყაროს მექანიზმია (`src/DromResolver.php`, `cache/`).

## მიმდინარე მდგომარეობა (2026-10-22)

- კოდი: Laravel 12. მარშრუტი `routes/product.php` → `App\Http\Controllers\Api\V1\CatalogController` → `App\Catalog\CatalogApi` / `PublicCatalog`. ძველი `src/` ახლა `legacy/src/`-შია (ვიჯეტის picker და კაბინეტი).
- მარშრუტები: `/api/v1/brands`, `/brands/{slug}/models`, `/models/{id}/years`, `/models/{id}/generations`, `/generations/{id}/modifications`, `/modifications`, `/modifications/{id}`, `/modifications/{id}/specs`, `/modifications/{id}/equipment`, `/engines`, `/bodies`, `/fuel`, `/colors`, `/coverage`. სრული კონტრაქტი: `GET /api/v1/openapi.json`, Postman: `/api/v1/postman.json`, დოკუმენტაცია: `/api/v1/docs`.
- ავტორიზაცია: `Authorization: Bearer geoapi_sk_…`. ლიმიტები და პლანები: `docs/USAGE_AND_LIMITS.md`, ბილინგი WP-11/WP-12 (`docs/TASKS.md`).
- ფოტო: `GET /api/models/{id}/photo?generation={id}` (verified ფოტოები; უფლებები დადასტურებულია 2026-10-22).
- ტესტები: `tests/Feature/PublicApiV1Test.php`, `PublicApiContractTest.php` (SQLite, `SeedsPublicCatalog`); CI: PHPUnit + SDK/MCP/CLI Node 20/22.
- გარემო: Laravel `.env` იკითხება `config/`-ის გავლით; ქვემოთ მოცემული `CARAPI_*` ცვლადები ისევ მოქმედებს კატალოგის კავშირზე.

ქვემოთ დარჩენილი ტექსტი წინა (pre-Laravel) ეტაპის აღწერაა და ისტორიისთვის რჩება.

## კონფიგურაცია

აპლიკაცია `.env` ფაილს არ კითხულობს. კავშირი მხოლოდ პროცესის გარემოდან მოდის (`src/Catalog/Connection.php`). `.env.example` საილუსტრაციო ცარიელი მნიშვნელობებია.

| ცვლადი | აზრი |
|---|---|
| `CARAPI_CATALOG_ENABLED` | `0` ნაგულისხმევი. `1` ჩართავს მარშრუტს |
| `CARAPI_MODIFICATIONS_ENABLED` | ჩართული უნდა იყოს, რომ `GET /api/v1/generations/{id}/modifications` იარსებოს. გამორთულზე მისამართი 404-ია ავტორიზაციამდე |
| `CARAPI_MODIFICATION_DETAILS_ENABLED` | ჩართული უნდა იყოს, რომ `GET /api/v1/modifications/{id}` იარსებოს. გამორთულზე მისამართი 404-ია ავტორიზაციამდე |
| `CARAPI_CATALOG_HOST` | ჰოსტი |
| `CARAPI_CATALOG_PORT` | პორტი, ნაგულისხმევი 5432 |
| `CARAPI_CATALOG_DATABASE` | `carbase` |
| `CARAPI_CATALOG_USER` | ბაზის ანგარიში |
| `CARAPI_CATALOG_PASSWORD` | პაროლი, თუ სერვერი ითხოვს |
| `CARAPI_CATALOG_CONNECT_TIMEOUT` | წამი, 1–10, ნაგულისხმევი 3 |
| `CARAPI_CATALOG_STATEMENT_TIMEOUT_MS` | მილიწამი, ნაგულისხმევი 3000 |

სესიაზე ედება `default_transaction_read_only=on`, `statement_timeout` და `lock_timeout=1s`. მოთხოვნები პარამეტრიზებულია და ცხრილები `public.` პრეფიქსით იწერება.

CLI:

```bash
export CARAPI_CATALOG_ENABLED=1
export CARAPI_CATALOG_HOST=127.0.0.1
export CARAPI_CATALOG_PORT=5432
export CARAPI_CATALOG_DATABASE=carbase
export CARAPI_CATALOG_USER='მხოლოდ-SELECT ანგარიში'
export CARAPI_CATALOG_PASSWORD='...'
php -S 127.0.0.1:8080 -t public public/index.php
```

PHP-FPM-ის პულში `env[CARAPI_CATALOG_ENABLED] = 1`. გასაღების გარეშე https://geoapi.ge/api/v1/brands აბრუნებს 401-ს. ისტორიულად, ჩართვამდე, პასუხი 404 იყო.

შეიქმნა როლი `carapi_catalog_reader`: `LOGIN`, არა `SUPERUSER`, `CREATEDB`, `CREATEROLE`, `REPLICATION`, `BYPASSRLS`. ცხრილების მფლობელი არ არის, სხვა როლის წევრი არ არის. უფლებებია `CONNECT` ბაზაზე `carbase`, `USAGE` სქემაზე `public` და `SELECT` ექვს ცხრილზე, რომლებსაც `src/Catalog/CatalogRepository.php` კითხულობს. სხვა ცხრილი ამ მოთხოვნებს არ სჭირდება. `GRANT ALL`, მომავალი ცხრილების ავტომატური `SELECT` და sequence-ები არ მინიჭებია.

`has_table_privilege` ამ როლზე: `SELECT` დიახ; `INSERT`, `UPDATE`, `DELETE`, `TRUNCATE`, `REFERENCES`, `TRIGGER`, `MAINTAIN` არა. `public`-ში `CREATE` არ აქვს. `SECURITY DEFINER` ფუნქცია `public`-ში არ არის. სესიის `default_transaction_read_only` დამატებითი ზღვარია, უფლების მტკიცებულება არა.

`PUBLIC`-ს `carbase`-ზე აქვს `CONNECT` და `TEMP`, ამიტომ როლსაც აქვს `TEMP`. ეს ცხრილის ჩაწერა არ არის. `PUBLIC` არც ერთ მომხმარებლის ცხრილს არ კითხულობს. საერთო უფლება არ გაუქმებულა, რომ სხვა ბაზების კავშირი არ გაწყდეს. როლს სხვა ბაზის ცხრილის `SELECT` არა აქვს.

ისტორიულად პაროლი საერთო PHP-FPM პულში არ ჩაწერილა. ახლა GeoAPI-ს ცალკე პული აქვს (`docs/DEPLOYMENT.md`). პაროლი კვლავ public/-ისა და Git-ის გარეთაა. production-ში API ჩართულია და გასაღებს ითხოვს.

შემოწმება ახალი როლით: `tests/catalog_api_test.php` გავიდა, `current_user` იყო `carapi_catalog_reader`, შედეგი დაემთხვა SQL-ს, SQLite არ შეიქმნა, `DromResolver` არ ჩაიტვირთა. ჩაწერის პრაქტიკული უარყოფა შემოწმდა ცალკე სატესტო ბაზაზე, `carbase` არ შეცვლილა.

## მარშრუტები

- `GET /api/v1/brands`
- `GET /api/v1/brands/{slug}/models`
- `GET /api/v1/models/{id}/generations`
- `GET /api/v1/generations/{id}/modifications`
- `GET /api/v1/modifications/{id}`
- `GET /api/v1/coverage`

slug ზუსტად ემთხვევა `brands.slug`-ს. `id` დადებითი მთელი რიცხვია და JSON-ში სტრიქონია.

სიის პასუხი:

```json
{
  "data": [],
  "meta": { "page": 1, "per_page": 100, "total": 0 },
  "collection": { "count": 0, "total": 0, "pages": 0, "next": null, "prev": null, "first": null, "last": null }
}
```

`page` ნაგულისხმევი 1, მაქსიმუმ 1000000. `limit` ნაგულისხმევი 100, მაქსიმუმ 1000. `per_page` იგივე ზომაა, როცა `limit` არ არის მოცემული; ორივეს გაგზავნისას მოქმედებს `limit`. `meta.per_page` ამ ზომას იღებს. `sort` და `direction` (`asc` ან `desc`) მხოლოდ თეთრ სიაზე მუშაობს. `q` სახელში ეძებს. `modified` არის `updated_at`. `modified_after` UTC დროა.

დამატებითი მისამართები, მხოლოდ შენახულ ველებზე: `GET /api/v1/modifications` (ჯაჭვზე ძებნა), `GET /api/v1/engines`, `GET /api/v1/bodies`, `GET /api/v1/fuel`, `GET /api/v1/colors`. ფილტრები: `year_from`, `year_to`, `body`, `fuel`, `drive`, `transmission`, `brand_id`, `model_id`. წლები კვლავ `year_start` / `year_end` სვეტებია. ცნობარი: `/api/v1/docs`. Postman: `/api/v1/postman.json`.

ბრენდი: `id`, `slug`, `name`, `synonyms`, `is_lcv_or_commercial`, `logo_url_light`, `logo_url_dark`. ცარიელი ლოგო არის `null`. სტატიკური SVG არ ერთვის. `synonyms` არარსებობისას `[]`.

მოდელი: `id`, `brand_id`, `slug`, `name`, `markets`, `has_generations`, `generations_count`. `markets` მხოლოდ `model_markets.market_slug`-ია. თაობის არმქონე მოდელიც ჩანს.

თაობა: `id`, `model_id`, `slug`, `name`, `year_start`, `year_end`, `period_text`, `body_type`, `chassis_code`, `steering_wheel`, `market`, `display_label`. `generations.market` ცარიელია, ამიტომ `market` არის `null`. მოდელის ბაზარი თაობას არ ეწერება. `display_label` არის სახელი, პერიოდი, ძარა და შასი, გამყოფით ` · `; ცარიელი ნაწილი გამოტოვებულია. ერთნაირი label-ისას ბოლოს დაემატა `id`. სახელები არ თარგმნილა.

არ ბრუნდება `source_url`, `drom_*`, კავშირის მონაცემი, `sync_state`, `photo_urls`. ფოტო არ იტვირთება.

`GET /api/v1/generations/{id}/modifications` სიაა. `id` თაობების სიის საჯარო იდენტიფიკატორია: ერთი მოდელის ჯგუფში უმცირესი `generations.id`, სადაც ჯგუფი შასის კოდით ან, მისი არარსებობისას, სახელით იკვრება. სხვა შიდა ნომერი 404-ია. `page` და `per_page` იგივეა, რაც სხვა სიებზე. პასუხი `{data, meta}`. ელემენტი: `id`, `generation_id`, `name`, `engine_volume_cc`, `transmission`, `year_start`, `year_end`. `generation_id` საჯარო თაობის `id`-ს უდრის. ცარიელი ტექსტი `null`-ია. სახელები არ ერთიანდება.

`GET /api/v1/modifications/{id}` ერთი ობიექტია, `{data: object}`. `page` არ გამოიყენება. ველები: `id`, `name`, `engine_volume_cc`, `transmission`, `drive_type`, `body_type`, `year_start`, `year_end`, `brand`, `model`, `generation`. `brand` და `model` შეიცავს `id`, `slug`, `name`. `generation` არის `{id, name}` ან `null`. `generation.id`, როცა არის, საჯარო თაობის იდენტიფიკატორია. არასწორი `id` 422-ია შეტყობინებით „მოდიფიკაციის id არასწორია…“. არმყოფი მოდიფიკაცია 404-ია შეტყობინებით „მითითებული მოდიფიკაცია ვერ მოიძებნა.“ ტექნიკური მახასიათებლების ცალკე მოთხოვნა ამ კონტრაქტში არ არის.

ორივე მარშრუტი იმავე Bearer გასაღებს და `catalog:read` სკოპს იყენებს. წარმატებული 200 თვიურ კვოტას ხარჯავს. 404 და 422 თვიურ კვოტას არ ხარჯავს და კვოტის სათაურებს არ აბრუნებს.

`/api/v1/coverage` სია არ არის. ველები ბაზიდან ითვლება, კოდში ჩაწერილი რიცხვი არ დგას: `brands_count`, `models_count`, `generations_count`, `models_with_generations_count`, `models_without_generations_count`, `modifications_count`, `photos_count`, `generations_with_photo_urls_count`, `measured_at`. `photos_count` ცხრილი `photos`-ია. `generations_with_photo_urls_count` არაცარიელი `generations.photo_urls` მასივია. ეს ფოტოები გამოქვეყნებულად არ ითვლება.

## შემოწმებული მაგალითები

2026-09-25, ლოკალური სერვერი, ჩართული დროშით. რიცხვები მაშინდელი `count(*)`-ია, კონტრაქტის ნაწილი არ არის.

`GET /api/v1/coverage`

```json
{
  "brands_count": 313,
  "models_count": 4704,
  "generations_count": 2158,
  "models_with_generations_count": 360,
  "models_without_generations_count": 4344,
  "modifications_count": 0,
  "photos_count": 0,
  "generations_with_photo_urls_count": 2097,
  "measured_at": "2026-09-25T17:31:24Z"
}
```

`GET /api/v1/brands?per_page=1` — პირველი ელემენტი (`total` 313):

```json
{
  "id": "1",
  "slug": "212",
  "name": "212",
  "synonyms": ["baw", "бав", "бау"],
  "is_lcv_or_commercial": false,
  "logo_url_light": null,
  "logo_url_dark": null
}
```

`GET /api/v1/models/314/generations?per_page=1` — BMW, `total` 119:

```json
{
  "id": "1578",
  "model_id": "314",
  "slug": "g_1975_8069",
  "name": "1 поколение",
  "year_start": 1975,
  "year_end": 1982,
  "period_text": "08.1975 - 12.1982",
  "body_type": "купе",
  "chassis_code": "E21",
  "steering_wheel": null,
  "market": null,
  "display_label": "1 поколение · 08.1975 - 12.1982 · купе · E21"
}
```

თაობის არმქონე მოდელზე პასუხი იყო `200` და `"data": []`.

## შეცდომები

| სტატუსი | როდის |
|---|---|
| 200 | მშობელი არსებობს, სია ცარიელიც რომ იყოს |
| 404 | გამორთული API, უცნობი გზა, უცნობი ბრენდი ან მოდელი |
| 422 | ცუდი `page`, `per_page` ან მოდელის `id` |
| 405 | არა-GET. სათაური `Allow: GET` |
| 503 | ბაზა მიუწვდომელია. ტექსტი: „კატალოგი დროებით მიუწვდომელია“. SQL და პაროლი არ ბრუნდება |

ყველა პასუხის ტიპია `application/json`.

## დაფარვის შეზღუდვა

მოდელების უმეტესობას თაობა არ აქვს: შემოწმებისას 360 / 4704. განმეორებული თაობის სახელი 184 მოდელშია, ასეთი ჯგუფი 482. ჩანაწერები არ გაერთიანებულა; გარჩევა `display_label`-ის `id`-ით ხდება. ლოგოს ველები ცარიელია. მოდიფიკაცია და `photos` ცარიელია. `photo_urls`-ში 2097 თაობას აქვს URL, ეს პლატფორმის ფოტო არ არის.

შემოწმება: `php tests/catalog_api_test.php` გარემოს ცვლადებით. ბაზაში ტესტის ჩანაწერი არ ჩაწერილა. `data/carapi.sqlite` არ შექმნილა.
