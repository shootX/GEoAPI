# GeoAPI — CI აუდიტი (WP-00)

შესწორება არ შეტანილა. `.github/workflows/deploy.yml` და `phpunit.xml` ამ PR-ში უცვლელია.

## 1. GitHub Actions

Workflow ფაილი ერთია: `.github/workflows/deploy.yml`. სახელი `Deploy`. ტრიგერი: push `main`-ზე (`deploy.yml:6-9`). Job `test` (PHPUnit) სავალდებულოა job `deploy`-ისთვის (`:42`).

`VERIFIED_PRODUCTION` Actions-ის ისტორიაზე (GitHub, არა აპლიკაციის სერვერი). `gh run list --workflow=Deploy` აბრუნებს ხუთ run-ს, ხუთივე `conclusion=failure`:

| run | SHA | შედეგი |
|---|---|---|
| [39509993059](https://github.com/shootX/GEoAPI/actions/runs/39509993059) | `61c6cd7dcb78bfd467b73be71bb519b3dca6ff2d` | failure, 2026-10-20T22:42:00Z |
| 39505259307 | `07778d4b7e3ac2d33a97d76e807d8deb88cac6b3` | failure |
| 39503043985 | `12988951cd92d67cd8f47bdc1343e70769e97b5a` | failure |
| 39499596697 | `3bc911c9bbb3b5a327151ece2eb7cd24c4005c63` | failure |
| 39456737073 | `b746497f7e9c5a128e10f754c8de39d1a1377639` | failure |

უახლესი run-ის job-ები: PHPUnit `failure` (database id 118928346248), Deploy `skipped` (118928508625). ლოგის ბოლო სტრიქონი: `Tests: 48, Assertions: 94, Errors: 30.` გამონაკლისი თითოეულ 30 შემთხვევაში: `Illuminate\Encryption\MissingAppKeyException: No application encryption key has been specified.`

30 error არის Feature ტესტები, რომლებიც HTTP-ს ბუტავენ. 18 ტესტი (94 assertion) ამ შეცდომამდე გავიდა: Unit ტესტები და Feature ტესტები, რომლებიც აპლიკაციის დაშიფვრას არ ითხოვენ. ეს 30 დამოუკიდებელი ფუნქციის გაფუჭება არ არის.

ძველი workflow სახელით `CI` (runs 39383326766, 39383625057, 39384028212, 39386916173, 39387807729) `success` იყო 2026-10-20-ის დილას, სანამ Deploy workflow `main`-ზე გაჩნდებოდა. ის მწვანე სტატუსი მიმდინარე `deploy.yml`-ს არ ეხება. PR #1 და #2 იმ ძველ `lint` check-ს აჩვენებს, Deploy PHPUnit-ს კი არა.

## 2. მიზეზი

`VERIFIED_CODE`.

1. `.github/workflows/deploy.yml:34-39` აკეთებს `cp .env.example .env`, `composer install`, შემდეგ პირდაპირ `vendor/bin/phpunit`. `php artisan key:generate` არ არის.
2. `.env.example:3` არის `APP_KEY=` (ცარიელი).
3. `phpunit.xml:20-43` აყენებს `APP_ENV=testing`, sqlite `:memory:` და სხვა env-ს. `APP_KEY` იქ არ წერია.
4. Laravel-ის `EncryptionServiceProvider` ცარიელ გასაღებზე აგდებს `MissingAppKeyException` პირველ HTTP ტესტზე, სადაც cookie/სესიის დაშიფვრა იხსნება. CI ლოგის ჩარჩო და ლოკალური ჩარჩო ერთი და იგივე კლასია.

ეს სატესტო გაშვების კონფიგურაციაა. Production `APP_KEY` რეპოში არ უნდა ჩაიწეროს.

## 3. ლოკალური გამეორება — მხოლოდ ამ VM-ში

`VERIFIED_TEST_LOCAL`. არ არის production და არ არის GitHub runner. `vendor/`, `.env` და გენერირებული გასაღები არ კომიტდება (`.gitignore:3` და `:21`). `composer update`, `migrate`, `seed` და deploy არ გაშვებულა.

გარემო: PHP `8.4.27` (CLI), `composer install --no-interaction --prefer-dist --no-progress` lock ფაილიდან. Lock ითხოვს PHP `>=8.4.1` (`spatie/laravel-activitylog` 5.1.3 და Symfony 8.1). CI იყენებს PHP 8.4 (`deploy.yml:29`).

ბრძანება A, CI-ის ნაბიჯების გამეორება ცარიელი `APP_KEY`-ით:

```text
cp .env.example .env
composer install --no-interaction --prefer-dist --no-progress
vendor/bin/phpunit --do-not-cache-result
```

შედეგი: exit `2`. `ERRORS!` `Tests: 48, Assertions: 94, Errors: 30.` იგივე `MissingAppKeyException`.

ბრძანება B, მხოლოდ ამ სამუშაო ასლში:

```text
php artisan key:generate --force
vendor/bin/phpunit --do-not-cache-result
```

`key:generate` exit `0` (`Application key set successfully`). გასაღების მნიშვნელობა აქ არ იბეჭდება. PHPUnit exit `0`:

```text
PHPUnit 11.5.57
Runtime: PHP 8.4.27
Configuration: /workspace/phpunit.xml
OK (48 tests, 407 assertions)
Time: 00:02.173, Memory: 70.50 MB
```

48 ტესტი, 0 error, 0 failure. Assertion-ების რიცხვი 94-დან 407-მდე გაიზარდა, რადგან Feature ტესტებმა სხეული რეალურად შეამოწმა და აღარ გაჩერდა boot-ზე. ეს ადგილობრივი sqlite `:memory:` შედეგია (`phpunit.xml:28-29`). პროდაქშენის მონაცემს არ ამოწმებს.

## 4. შემოთავაზებული შესწორება — არ არის გამოყენებული

WP-01-ისთვის, ამ PR-ის გარეთ. ზუსტი ცვლილება `.github/workflows/deploy.yml`-ის PHPUnit ნაბიჯში, `composer install`-ის შემდეგ და `vendor/bin/phpunit`-მდე:

```yaml
      - name: Run PHPUnit
        run: |
          php artisan key:generate --force
          vendor/bin/phpunit
```

`key:generate --force` წერს მხოლოდ runner-ის სამუშაო `.env`-ში. runner ქრება run-ის ბოლოს. Production `APP_KEY`, GitHub Secret-ის რეალური მნიშვნელობა და სერვერის `.env` ამ ნაბიჯში არ შედის. Workflow-ის კომენტარი (`deploy.yml:2`) რომ ფაილი სერვერის `.env`-ს არ წერს, ძალაში რჩება: ეს ბრძანება SSH deploy job-ში არ დგას.

ეკვივალენტური, ასევე გამოუყენებელი ვარიანტი: `phpunit.xml`-ში სატესტო `APP_KEY`, რომელიც production გასაღები არ არის. სპეციფიკაციის WP-01 ირჩევს `key:generate --force`-ს. ერთი საკმარისია. ორივეს ერთად ჩასმა საჭირო არ არის.

ამის შემდეგ თუ ახალი წითელი ტესტი გამოჩნდება, ის ცალკე შესწორდეს. ამ ლოკალურ გაშვებაში სხვა წითელი ტესტი არ გამოჩენილა. GitHub runner-ზე ეს ჯერ არ დადასტურებულა, რადგან workflow არ შეცვლილა (`UNKNOWN` CI-ის მომავალ მწვანე სტატუსზე).

Deploy job მწვანე ტესტის შემდეგაც ცალკე რისკია: `deploy.yml:58-68` შედის `/www/wwwroot/geoapi.ge`-ში და უშვებს `php artisan migrate --force`. გზის შეუსაბამობა აღწერილია `docs/audits/geoapi-security.md` §7. მწვანე CI ავტომატურად არ ნიშნავს, რომ deploy დირექტორია ცოცხალი nginx-ის checkout-ია.
