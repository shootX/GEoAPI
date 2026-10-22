<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Billing\V2\Flag;

/**
 * Public catalog contract. OpenAPI, Postman, and /guide are rendered from this class.
 */
final class PublicSpec
{
    public const ORIGIN = 'https://geoapi.ge';

    public const VERSION = '1.2.0';

    /** @return array<string, mixed> */
    public static function document(): array
    {
        $paths = [];
        foreach (self::endpoints() as $endpoint) {
            $paths[$endpoint['path']] = [
                'get' => self::operation($endpoint),
            ];
        }

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'GeoAPI',
                'version' => self::VERSION,
                'description' => self::infoDescription(),
            ],
            'servers' => [['url' => self::ORIGIN]],
            'tags' => [
                ['name' => 'Catalog', 'description' => 'Public read-only vehicle catalog. GET only.'],
            ],
            'components' => [
                'securitySchemes' => [
                    'secretKey' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'Server key geoapi_sk_…. geoapi_pk_, query strings, cookies, and a cabinet session are not this credential.',
                    ],
                ],
                'parameters' => self::parameters(),
                'schemas' => self::schemas(),
            ],
            'security' => [['secretKey' => []]],
            'paths' => $paths,
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function requests(): array
    {
        $items = [];
        foreach (self::document()['paths'] as $path => $operations) {
            $items[] = [
                'name' => $path,
                'request' => [
                    'method' => 'GET',
                    'description' => (string) $operations['get']['summary'],
                    'header' => [],
                    'url' => self::postmanUrl($path),
                ],
            ];
        }

        return $items;
    }

    /** @return array<string, string> */
    public static function codeSamples(string $examplePath): array
    {
        $url = self::ORIGIN.$examplePath;
        $encoded = json_encode($url, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return [
            'curl' => "curl {$url} \\\n  -H \"Authorization: Bearer geoapi_sk_…\"",
            'JavaScript' => "const response = await fetch({$encoded}, {\n  headers: { Authorization: 'Bearer geoapi_sk_…' },\n});\nconst data = await response.json();",
            'Node' => "const response = await fetch({$encoded}, {\n  headers: { Authorization: 'Bearer geoapi_sk_…' },\n});\nconsole.log(await response.json());",
            'Python' => "import urllib.request\nrequest = urllib.request.Request({$encoded}, headers={'Authorization': 'Bearer geoapi_sk_…'})\nprint(urllib.request.urlopen(request).read().decode())",
            'PHP' => "\$ch = curl_init({$encoded});\ncurl_setopt_array(\$ch, [\n    CURLOPT_HTTPHEADER => ['Authorization: Bearer geoapi_sk_…'],\n    CURLOPT_RETURNTRANSFER => true,\n]);\necho curl_exec(\$ch);",
        ];
    }

    /**
     * Human guide sections, one per public route. The example path is what the code samples call.
     *
     * @return list<array{title:string, text:string, path:string, example:string, response:string}>
     */
    public static function guideSections(string $locale): array
    {
        $english = $locale === 'en';
        $sections = [];
        foreach (self::endpoints() as $endpoint) {
            $sections[] = [
                'title' => $english ? $endpoint['title_en'] : $endpoint['title_ka'],
                'text' => $english ? $endpoint['guide_en'] : $endpoint['guide_ka'],
                'path' => $endpoint['path'],
                'example' => $endpoint['example'],
                'response' => self::pretty($endpoint['sample']),
            ];
        }

        return $sections;
    }

    /**
     * @return list<array{id:string, title:string, paragraphs:list<string>}>
     */
    public static function narrative(string $locale): array
    {
        $english = $locale === 'en';

        $rows = [
            [
                'id' => 'guide-quickstart',
                'title' => $english ? 'Quickstart' : 'სწრაფი დასაწყისი',
                'paragraphs' => $english ? [
                    'Create an account, then a project. The secret geoapi_sk_ is shown once on the create response. Store it on your server. The next cabinet page does not show it again.',
                    'Call GET /api/v1/brands, then GET /api/v1/brands/{slug}/models, then GET /api/v1/models/{id}/years or /generations, then modifications. Replace the slug and ids with values from the previous response.',
                    'The browser embed uses the public key geoapi_pk_, never the secret. The TypeScript SDK is packages/sdk-js in this repository. The MCP server is packages/mcp-server in this repository. The CLI is packages/cli in this repository and is not published on the public npm registry.',
                ] : [
                    'შექმენით ანგარიში და პროექტი. საიდუმლო geoapi_sk_ ერთხელ ჩანს შექმნის პასუხში. შეინახეთ ის სერვერზე. შემდეგი კაბინეტის გვერდი მას აღარ აჩვენებს.',
                    'გამოიძახეთ GET /api/v1/brands, შემდეგ GET /api/v1/brands/{slug}/models, შემდეგ GET /api/v1/models/{id}/years ან /generations, შემდეგ მოდიფიკაციები. slug და id წინა პასუხიდან ჩასვით.',
                    'ბრაუზერის ჩასმა იყენებს საჯარო გასაღებს geoapi_pk_. საიდუმლო გასაღები იქ არ დებს. TypeScript SDK არის packages/sdk-js ამ რეპოზიტორიაში. MCP სერვერი არის packages/mcp-server ამ რეპოზიტორიაში. CLI არის packages/cli ამ რეპოზიტორიაში და საჯარო npm რეესტრში გამოქვეყნებული არ არის.',
                ],
            ],
            [
                'id' => 'guide-auth',
                'title' => $english ? 'Authentication' : 'ავტორიზაცია',
                'paragraphs' => $english ? [
                    'The catalog reads Authorization: Bearer geoapi_sk_…. A query string, a cookie, and a cabinet session are not credentials. geoapi_pk_ is only the widget key.',
                    'A Sanctum token with cars:read or * also needs X-CARAPI-PROJECT naming that user\'s active project. A secret key ignores X-CARAPI-PROJECT and meters its own project.',
                    '401 unauthorized means the key is missing, unknown, revoked, or expired. The response includes WWW-Authenticate: Bearer and no quota headers. Authentication runs before the HTTP method is checked, so POST without an accepted key is 401, not 405. 405 method_not_allowed is returned only after the key is accepted. Use GET. 403 forbidden means the key is valid but the project is disabled, the owner is suspended, or the email is unverified.',
                    'Rotation creates a new key and sets revoked_at on the old one. Keys are not deleted. Re-enabling a project does not restore a revoked key.',
                ] : [
                    'კატალოგი კითხულობს Authorization: Bearer geoapi_sk_…. მოთხოვნის სტრიქონი, cookie და კაბინეტის სესია გასაღები არ არის. geoapi_pk_ მხოლოდ ვიჯეტის გასაღებია.',
                    'Sanctum ტოკენს cars:read ან * სჭირდება X-CARAPI-PROJECT, რომელიც ამ მომხმარებლის აქტიურ პროექტს ასახელებს. საიდუმლო გასაღები X-CARAPI-PROJECT-ს არ ითვალისწინებს და საკუთარ პროექტს ითვლის.',
                    '401 unauthorized ნიშნავს, რომ გასაღები არ არის, უცნობია, გაუქმებულია ან ვადაგასულია. პასუხს აქვს WWW-Authenticate: Bearer და არ აქვს quota სათაურები. ავტორიზაცია HTTP მეთოდის შემოწმებამდე ხდება, ამიტომ მიღებული გასაღების გარეშე POST არის 401 და არა 405. 405 method_not_allowed ბრუნდება მხოლოდ მას შემდეგ, რაც გასაღები მიღებულია. გამოიყენეთ GET. 403 forbidden ნიშნავს, რომ გასაღები სწორია, მაგრამ პროექტი გამორთულია, მფლობელი შეჩერებულია ან ელფოსტა დაუდასტურებელია.',
                    'როტაცია ქმნის ახალ გასაღებს და ძველზე revoked_at-ს წერს. გასაღები ფიზიკურად არ იშლება. პროექტის ხელახლა ჩართვა გაუქმებულ გასაღებს არ აბრუნებს.',
                ],
            ],
            [
                'id' => 'guide-dictionary',
                'title' => $english ? 'Data dictionary' : 'მონაცემების ლექსიკონი',
                'paragraphs' => $english ? [
                    'JSON ids are strings. A slug identifies a brand or a model in a URL. A model belongs to a brand. A generation belongs to a model. The public generation id is the smallest sibling id, so a restyling row id is 404. A modification belongs to a generation.',
                    'year_start and year_end are the stored years. A null year_end runs through the current UTC year and no further. year_from and year_to filter that overlap. They are not column names. REST year lists default to ascending. The widget list is newest-first.',
                    'engine volume is cubic centimetres. power_hp is horsepower and may be null. Fuel consumption is litres per 100 km. null means the value is not stored. dimensions and performance are always null because those columns are not stored.',
                    'Colors return every stored row. verified is false when the photo was not confirmed. The image is not in this response. Photo rights are cleared; this API has no photo list yet, and the image is served by /api/models/{id}/photo. VIN is not a route.',
                ] : [
                    'JSON-ში id სტრიქონია. slug ბრენდს ან მოდელს URL-ში ასახელებს. მოდელი ბრენდს ეკუთვნის. თაობა მოდელს ეკუთვნის. საჯარო თაობის id უმცირესი დობილი id-ა, ამიტომ რესტაილინგის სტრიქონის id არის 404. მოდიფიკაცია თაობას ეკუთვნის.',
                    'year_start და year_end შენახული წლებია. ცარიელი year_end მიმდინარე UTC წლამდე მიდის და შემდეგ ჩერდება. year_from და year_to ამ გადაფარვას ფილტრავს. ისინი სვეტის სახელები არ არის. REST წლების სია ნაგულისხმევად ზრდადია. ვიჯეტის სია უახლესით იწყება.',
                    'ძრავის მოცულობა კუბურ სანტიმეტრებშია. power_hp ცხენის ძალაა და შეიძლება null იყოს. საწვავის ხარჯი ლიტრია 100 კილომეტრზე. null ნიშნავს, რომ მნიშვნელობა არ ინახება. dimensions და performance ყოველთვის null-ია, რადგან ეს სვეტები არ ინახება.',
                    'ფერები აბრუნებს ყველა შენახულ სტრიქონს. verified არის false, თუ ფოტო დადასტურებული არ არის. სურათი ამ პასუხში არ არის. ფოტოს უფლებები დადგენილი არ არის, ამიტომ ამ API-ს ფოტოების სია არ აქვს. VIN მისამართი არ არის.',
                ],
            ],
            [
                'id' => 'guide-limits',
                'title' => $english ? 'Limits' : 'ლიმიტები',
                'paragraphs' => $english ? [
                    'A project that has not chosen a commercial plan keeps 60 requests each UTC minute and 10 000 successful requests each UTC month. Choosing the free plan sets that project to 100 successful requests each UTC month. Basic is 2 000 successful requests each UTC month at 59 USD. Premium is unlimited at 99 USD. A year is 12% below twelve monthly prices. Prices include VAT. The billing page shows GEL from the National Bank of Georgia rate for that date. The 2 000 successful requests per UTC day limit is not enforced in production today. The widget and the server key share the counter.',
                    'The daily check runs only when platform.project_limits.per_day exists. That column is not active in production, so the daily cap is skipped. A missing limits row is 503, not unlimited. A failed meter is 503 and the body is not the catalog.',
                    'A successful response sends X-RateLimit-Limit, X-RateLimit-Remaining, and X-RateLimit-Reset for the minute, plus X-Quota-Limit, X-Quota-Remaining, and X-Quota-Reset for the month. 401 does not send quota headers.',
                    '429 is JSON with Retry-After. Codes: RATE_LIMIT_EXCEEDED, DAILY_QUOTA_EXCEEDED, MONTHLY_QUOTA_EXCEEDED. On 429 the X-RateLimit-* headers describe the window that was hit, not X-Quota-*. Wait Retry-After seconds. Do not retry 401, 403, 404, or 422. 401, 403, 404, 405, 422, 429, and 503 do not spend the month. Failures still use the minute window. A new key does not reset the counter.',
                ] : [
                    'პროექტი, რომელსაც კომერციული გეგმა არ აურჩევია, ინარჩუნებს UTC წუთში 60 მოთხოვნას და UTC თვეში 10 000 წარმატებულ მოთხოვნას. უფასო გეგმის არჩევა ამ პროექტს UTC თვეში 100 წარმატებულ მოთხოვნაზე აყენებს. Basic არის UTC თვეში 2 000 წარმატებული მოთხოვნა 59 USD-ად. Premium შეუზღუდავია 99 USD-ად. წელიწადი 12 თვის ფასზე 12%-ით ნაკლებია. ფასი დღგ-ს შეიცავს. გადახდის გვერდი ლარს იმ დღის ეროვნული ბანკის კურსით აჩვენებს. UTC დღეში 2 000 წარმატებული მოთხოვნის ზღვარი პროდაქშენში დღეს არ მოქმედებს. ვიჯეტი და სერვერის გასაღები ერთ მრიცხველს იყოფს.',
                    'დღიური შემოწმება მხოლოდ მაშინ ეშვება, როცა platform.project_limits.per_day არსებობს. ეს სვეტი პროდაქშენში აქტიური არ არის, ამიტომ დღიური ზღვარი გამოტოვებულია. ლიმიტების სტრიქონის არყოფნა არის 503, არა შეუზღუდავი. მრიცხველის შეცდომა არის 503 და სხეული კატალოგი არ არის.',
                    'წარმატებული პასუხი აგზავნის X-RateLimit-Limit, X-RateLimit-Remaining და X-RateLimit-Reset წუთისთვის, პლუს X-Quota-Limit, X-Quota-Remaining და X-Quota-Reset თვისთვის. 401 quota სათაურებს არ აგზავნის.',
                    '429 არის JSON და Retry-After. კოდები: RATE_LIMIT_EXCEEDED, DAILY_QUOTA_EXCEEDED, MONTHLY_QUOTA_EXCEEDED. 429-ზე X-RateLimit-* იმ ფანჯარას აღწერს, რომელიც ამოიწურა, არა X-Quota-*. დაელოდეთ Retry-After წამს. 401, 403, 404 და 422 ხელახლა არ გაგზავნოთ. 401, 403, 404, 405, 422, 429 და 503 თვეს არ ხარჯავს. წარუმატებელი მოთხოვნა მაინც ხარჯავს წუთს. ახალი გასაღები მრიცხველს არ ანულებს.',
                ],
            ],
            [
                'id' => 'guide-changelog',
                'title' => $english ? 'Changelog' : 'ცვლილებები',
                'paragraphs' => $english ? [
                    'OpenAPI info.version is 1.2.0. It documents the catalog that is deployed: the list envelope, years, specs, equipment, and colors with a verified flag. This version does not deprecate a field.',
                    'The host is https://geoapi.ge. This page is not an incident log. The TypeScript SDK in packages/sdk-js is 0.1.0 and is not on the public npm registry. The MCP server in packages/mcp-server is 0.1.0 and is not on the public npm registry. The CLI in packages/cli is 0.1.0 and is not published on the public npm registry.',
                ] : [
                    'OpenAPI info.version არის 1.2.0. ის აღწერს კატალოგს, რომელიც გაშვებულია: სიის კონვერტს, წლებს, სპეციფიკაციას, კომპლექტაციას და ფერებს verified დროშით. ეს ვერსია ველს არ აუქმებს.',
                    'ჰოსტია https://geoapi.ge. ეს გვერდი ინციდენტების ჟურნალი არ არის. TypeScript SDK packages/sdk-js-ში არის 0.1.0 და საჯარო npm რეესტრში არ დევს. MCP სერვერი packages/mcp-server-ში არის 0.1.0 და საჯარო npm რეესტრში არ დევს. CLI packages/cli-ში არის 0.1.0 და საჯარო npm რეესტრში გამოქვეყნებული არ არის.',
                ],
            ],
            [
                'id' => 'guide-recipes',
                'title' => $english ? 'Recipes' : 'რეცეპტები',
                'paragraphs' => $english ? [
                    'Picker: brands, then models by slug, then years, then generations, then modifications for that public generation id. Pass year_from and year_to only on generations and modifications. The years route returns 422 if those filters are sent.',
                    'Inventory search: GET /api/v1/modifications with brand_id, model_id, year_from, and fuel. Specs and equipment are GET /api/v1/modifications/{id}/specs and /equipment. Treat null as missing. Do not expect dimensions or performance.',
                    'Photos: there is no v1 photo list. Color names are the fallback. Do not invent an image URL. VIN is not a route.',
                ] : [
                    'ამრჩევი: ბრენდები, შემდეგ მოდელები slug-ით, შემდეგ წლები, შემდეგ თაობები, შემდეგ მოდიფიკაციები ამ საჯარო თაობის id-ზე. year_from და year_to მხოლოდ თაობებსა და მოდიფიკაციებზე გაგზავნეთ. წლების მისამართი 422-ს აბრუნებს, თუ ეს ფილტრები გაგზავნილია.',
                    'მარაგის ძებნა: GET /api/v1/modifications brand_id, model_id, year_from და fuel პარამეტრებით. სპეციფიკაცია და კომპლექტაცია არის GET /api/v1/modifications/{id}/specs და /equipment. null ნიშნავს, რომ მნიშვნელობა არ არის. dimensions და performance არ ელოდოთ.',
                    'ფოტოები: v1 ფოტოების სია არ არსებობს. ფერის სახელი არის შემცვლელი. სურათის URL არ გამოიგონოთ. VIN მისამართი არ არის.',
                ],
            ],
            [
                'id' => 'guide-packages',
                'title' => $english ? 'SDK, MCP, and CLI' : 'SDK, MCP და CLI',
                'paragraphs' => $english ? [
                    'The TypeScript SDK is packages/sdk-js in this repository, version 0.1.0. It calls this REST API and does not keep a second copy of the catalog rules. It is not on the public npm registry. Node 20 or newer. Construct it with new GeoAPI({ apiKey }). brands.list, models.byBrand, generations.byModel, modifications.list, and modifications.get cover the picker. It retries 429 and 503 at most twice. A present Retry-After is capped at 2 seconds. A missing Retry-After waits 250 ms times the attempt. It does not retry 400, 401, or 403. GeoAPIError has status, code, retryAfter, and requestId. requestId is null because this API does not send one. scripts/smoke.mjs names the call that failed.',
                    'A secret key is refused in a browser page and in a web worker. The MCP server is packages/mcp-server in this repository, version 0.1.0. It is a local stdio process, not an HTTP route, and it is not on the public npm registry. GEOAPI_API_KEY is geoapi_sk_… in the host environment, never a tool argument. The tools are geoapi_list_brands, geoapi_list_models, geoapi_list_generations, geoapi_list_years, geoapi_list_modifications, geoapi_get_modification, geoapi_get_specs, geoapi_get_equipment, and geoapi_get_coverage. A prompt such as list BMW models and one generation uses those tools. This page does not run that prompt against production. The widget script is the browser embed. It uses geoapi_pk_ and does not contain a secret key.',
                    'The CLI is packages/cli in this repository, version 0.1.0. It calls the TypeScript SDK and is not published on the public npm registry. Node 20 or newer. The key is GEOAPI_API_KEY and is never a flag. Examples: geoapi brands list --limit 20 --json, geoapi models list --brand bmw, geoapi modifications list --model-id 314 --year-from 2020, and geoapi coverage. --json writes one JSON document to stdout. The API code goes to stderr. Exit 0 is success, 2 is invalid input, 3 is auth, 4 is rate limited, and 5 is network or service. 429 and 503 retry through the SDK at most twice. This page does not run the CLI against production.',
                ] : [
                    'TypeScript SDK არის packages/sdk-js ამ რეპოზიტორიაში, ვერსია 0.1.0. ის ამ REST API-ს იძახებს და კატალოგის წესების მეორე ასლს არ ინახავს. საჯარო npm რეესტრში არ დევს. სჭირდება Node 20 ან უფრო ახალი. იქმნება new GeoAPI({ apiKey })-ით. brands.list, models.byBrand, generations.byModel, modifications.list და modifications.get ამრჩევს ფარავს. 429-სა და 503-ს ხელახლა ცდის მაქსიმუმ ორჯერ. Retry-After იზღუდება 2 წამით. სათაურის არყოფნისას ლოდინი არის 250 მილიწამი გამრავლებული მცდელობაზე. 400, 401 და 403 ხელახლა არ იგზავნება. GeoAPIError-ს აქვს status, code, retryAfter და requestId. requestId არის null, რადგან ეს API მას არ აგზავნის. scripts/smoke.mjs ასახელებს მოთხოვნას, რომელიც ჩავარდა.',
                    'საიდუმლო გასაღები უარყოფილია ბრაუზერის გვერდზე და web worker-ში. MCP სერვერი არის packages/mcp-server ამ რეპოზიტორიაში, ვერსია 0.1.0. ის ლოკალური stdio პროცესია, არა HTTP მისამართი, და საჯარო npm რეესტრში არ დევს. GEOAPI_API_KEY არის geoapi_sk_… ჰოსტის გარემოში და არა tool-ის არგუმენტი. ხელსაწყოებია geoapi_list_brands, geoapi_list_models, geoapi_list_generations, geoapi_list_years, geoapi_list_modifications, geoapi_get_modification, geoapi_get_specs, geoapi_get_equipment და geoapi_get_coverage. მოთხოვნა, როგორიცაა BMW-ის მოდელები და ერთი თაობა, ამ ხელსაწყოებს იყენებს. ეს გვერდი ამ მოთხოვნას პროდაქშენზე არ უშვებს. ვიჯეტის სკრიპტი ბრაუზერის ჩასმაა. ის იყენებს geoapi_pk_ და საიდუმლო გასაღებს არ შეიცავს.',
                    'CLI არის packages/cli ამ რეპოზიტორიაში, ვერსია 0.1.0. ის TypeScript SDK-ს იძახებს და საჯარო npm რეესტრში გამოქვეყნებული არ არის. სჭირდება Node 20 ან უფრო ახალი. გასაღები არის GEOAPI_API_KEY და flag არ არის. მაგალითები: geoapi brands list --limit 20 --json, geoapi models list --brand bmw, geoapi modifications list --model-id 314 --year-from 2020 და geoapi coverage. --json stdout-ზე ერთ JSON დოკუმენტს წერს. API-ის კოდი stderr-ზე გადის. გასვლა 0 არის წარმატება, 2 არასწორი შეყვანა, 3 ავტორიზაცია, 4 ლიმიტი და 5 ქსელი ან სერვისი. 429 და 503 SDK-ით ხელახლა იგზავნება მაქსიმუმ ორჯერ. ეს გვერდი CLI-ს პროდაქშენზე არ უშვებს.',
                ],
            ],
            [
                'id' => 'guide-errors',
                'title' => $english ? 'Errors' : 'შეცდომები',
                'paragraphs' => $english ? [
                    'Every error is JSON with error.code and error.message only. There is no request id.',
                    '401 unauthorized. The key is missing or no longer works. This check happens before the method, so a non-GET request without an accepted key is 401, not 405. 403 forbidden. The project, email, or origin does not match. 404 not_found. The record was not found. 405 method_not_allowed is returned only after the key is accepted. Use GET. 422 invalid_parameter. page, limit, per_page, sort, direction, a filter, or id is invalid. 429 uses RATE_LIMIT_EXCEEDED, DAILY_QUOTA_EXCEEDED, or MONTHLY_QUOTA_EXCEEDED. 503 unavailable. The service or the meter is temporarily unavailable.',
                    'Georgian is the default message. Send Accept-Language: en for the English message.',
                ] : [
                    'ყოველი შეცდომა არის JSON მხოლოდ error.code და error.message ველებით. მოთხოვნის id არ არის.',
                    '401 unauthorized. გასაღები არ არის ან ვეღარ გამოიყენება. ეს შემოწმება მეთოდამდე ხდება, ამიტომ მიღებული გასაღების გარეშე არა-GET მოთხოვნა არის 401 და არა 405. 403 forbidden. პროექტი, ელფოსტა ან origin არ ემთხვევა. 404 not_found. ჩანაწერი ვერ მოიძებნა. 405 method_not_allowed ბრუნდება მხოლოდ მას შემდეგ, რაც გასაღები მიღებულია. გამოიყენეთ GET. 422 invalid_parameter. page, limit, per_page, sort, direction, ფილტრი ან id არასწორია. 429 იყენებს RATE_LIMIT_EXCEEDED, DAILY_QUOTA_EXCEEDED ან MONTHLY_QUOTA_EXCEEDED. 503 unavailable. სერვისი ან მრიცხველი დროებით მიუწვდომელია.',
                    'შეტყობინება ნაგულისხმევად ქართულია. ინგლისურისთვის გაგზავნეთ Accept-Language: en.',
                ],
            ],
        ];

        if (Flag::on()) {
            foreach ($rows as &$row) {
                if (($row['id'] ?? '') !== 'guide-limits') {
                    continue;
                }
                $row['paragraphs'][0] = $english
                    ? 'A workspace shares one monthly quota across its projects. Free is 1 000 successful requests each month, one active project, and 15 requests each minute. Starter is 25 000 requests, 3 projects, and 60 requests each minute at 19 USD. Growth is 150 000 requests, 10 projects, and 120 requests each minute at 59 USD. A year is 15% below twelve monthly prices and the quota still resets every month. Prices include VAT. The billing page shows GEL from the National Bank of Georgia rate for that date. Nothing is charged again unless you pay. Business is not offered until its rate limit is measured. The widget and the server key share the counter. A new project or a new key does not reset usage.'
                    : 'სამუშაო სივრცე ერთ თვიურ კვოტას იზიარებს პროექტებს შორის. უფასო არის თვეში 1 000 წარმატებული მოთხოვნა, ერთი აქტიური პროექტი და წუთში 15 მოთხოვნა. Starter არის 25 000 მოთხოვნა, 3 პროექტი და წუთში 60 მოთხოვნა 19 USD-ად. Growth არის 150 000 მოთხოვნა, 10 პროექტი და წუთში 120 მოთხოვნა 59 USD-ად. წელიწადი 12 თვის ფასზე 15%-ით ნაკლებია და კვოტა მაინც ყოველ თვე ნულდება. ფასი დღგ-ს შეიცავს. გადახდის გვერდი ლარს იმ დღის ეროვნული ბანკის კურსით აჩვენებს. თანხა ხელახლა არ ჩამოიჭრება, სანამ თავად არ გადაიხდით. Business არ იყიდება, სანამ მისი სიჩქარის ზღვარი იზომება. ვიჯეტი და სერვერის გასაღები ერთ მრიცხველს იყოფს. ახალი პროექტი ან ახალი გასაღები მოხმარებას არ ანულებს.';
                $row['paragraphs'][3] = str_replace(
                    'MONTHLY_QUOTA_EXCEEDED',
                    'MONTHLY_QUOTA_EXCEEDED, PROJECT_CAP_EXCEEDED',
                    (string) $row['paragraphs'][3],
                );
            }
            unset($row);
        }

        return $rows;
    }

    public static function widgetText(string $locale): string
    {
        if ($locale === 'en') {
            return 'The browser calls POST /widget/v1/{public-key}/brands and the same path for models, years, generations, and POST /widget/v1/{public-key}/generations/{id}/modifications. Years match GET /api/v1/models/{id}/years. On this site the key demo works only for this origin. A project key looks like geoapi_pk_ and 32 hex characters. The geoapi:change event keeps brand_id, model_id, year, and generation_id. modification_id is set when a modification is chosen and stays null until then. An allowed Origin is not proof of a browser. A non-browser client can send that header, so the allow-list is not complete protection. The script never contains geoapi_sk_.';
        }

        return 'ბრაუზერი იძახებს POST /widget/v1/{საჯარო-გასაღები}/brands და იმავე გზას მოდელების, წლებისა და თაობებისთვის, ასევე POST /widget/v1/{საჯარო-გასაღები}/generations/{id}/modifications. წლები ემთხვევა GET /api/v1/models/{id}/years-ს. ამ საიტზე demo გასაღები მხოლოდ ამ origin-ზე მუშაობს. პროექტის გასაღები არის geoapi_pk_ და 32 თექვსმეტობითი სიმბოლო. geoapi:change ინარჩუნებს brand_id, model_id, year და generation_id. modification_id ივსება მოდიფიკაციის არჩევის შემდეგ და მანამდე null-ია. დაშვებული Origin ბრაუზერის მტკიცებულება არ არის. არაბრაუზერულ კლიენტსაც შეუძლია ეს სათაური გაგზავნოს, ამიტომ სია სრული დაცვა არ არის. სკრიპტი geoapi_sk_ არასოდეს შეიცავს.';
    }

    /** @param array<string, mixed> $value */
    private static function pretty(array $value): string
    {
        return (string) json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function infoDescription(): string
    {
        return 'Public catalog API. Send Authorization: Bearer geoapi_sk_… from your server. '
            .'List responses keep data and meta, and add collection. limit defaults to 100 and cannot exceed 1000. '
            .'per_page is an alias used only when limit is absent. Fuel consumption is litres per 100 km. '
            .'Dimensions and performance groups are null because those columns are not stored. '
            .'Colors return every stored row with a verified flag. '
            .'Document version '.self::VERSION.' matches the deployed catalog. '
            .'The TypeScript SDK is packages/sdk-js 0.1.0 in this repository and is not on the public npm registry. The MCP server is packages/mcp-server 0.1.0 in this repository and is not on the public npm registry. The CLI is packages/cli 0.1.0 in this repository and is not published on the public npm registry. '
            .'The host is '.self::ORIGIN.'.';
    }

    /** @return array<string, mixed> */
    private static function parameters(): array
    {
        return [
            'page' => self::queryParameter('page', 'Page number. Default 1. Maximum 1000000.', ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000, 'default' => 1]),
            'limit' => self::queryParameter('limit', 'Page size. Default 100. per_page is ignored when limit is present.', ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 100]),
            'perPage' => self::queryParameter('per_page', 'Alias of limit. Ignored when limit is present.', ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000]),
            'direction' => self::queryParameter('direction', 'asc or desc. Default asc. Applied when sort is set.', ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc']),
            'q' => self::queryParameter('q', 'Case-insensitive name search, 1 to 100 characters. Control characters are rejected. Not accepted on years.', ['type' => 'string', 'minLength' => 1, 'maxLength' => 100]),
            'modifiedAfter' => self::queryParameter('modified_after', 'UTC timestamp. Returns rows whose modified value is newer. Example 2026-01-01T00:00:00Z. Not accepted on years or bodies.', ['type' => 'string', 'format' => 'date-time']),
            'yearFrom' => self::queryParameter('year_from', 'Inclusive overlap with stored year_start and year_end. Integer from 1886 to 2100. Not a column.', ['type' => 'integer', 'minimum' => 1886, 'maximum' => 2100]),
            'yearTo' => self::queryParameter('year_to', 'Inclusive overlap with stored year_start and year_end. Integer from 1886 to 2100. Use with year_from.', ['type' => 'integer', 'minimum' => 1886, 'maximum' => 2100]),
            'body' => self::queryParameter('body', 'Stored body type, up to 64 characters.', ['type' => 'string', 'maxLength' => 64]),
            'fuel' => self::queryParameter('fuel', 'Stored fuel type, up to 64 characters.', ['type' => 'string', 'maxLength' => 64]),
            'drive' => self::queryParameter('drive', 'Stored drive type, up to 64 characters.', ['type' => 'string', 'maxLength' => 64]),
            'transmission' => self::queryParameter('transmission', 'Stored transmission, up to 64 characters.', ['type' => 'string', 'maxLength' => 64]),
            'brandId' => self::queryParameter('brand_id', 'Positive integer id from a brand response. Zero is rejected.', ['type' => 'integer', 'minimum' => 1]),
            'modelId' => self::queryParameter('model_id', 'Positive integer id from a model response. Zero is rejected.', ['type' => 'integer', 'minimum' => 1]),
            'volumeFrom' => self::queryParameter('volume_from', 'Minimum engine volume in cubic centimetres, from 1 to 20000.', ['type' => 'integer', 'minimum' => 1, 'maximum' => 20000]),
            'volumeTo' => self::queryParameter('volume_to', 'Maximum engine volume in cubic centimetres, from 1 to 20000.', ['type' => 'integer', 'minimum' => 1, 'maximum' => 20000]),
        ];
    }

    /** @param array<string, mixed> $schema @return array<string, mixed> */
    private static function queryParameter(string $name, string $description, array $schema): array
    {
        return [
            'name' => $name,
            'in' => 'query',
            'description' => $description,
            'schema' => $schema,
        ];
    }

    /** @return array<string, mixed> */
    private static function schemas(): array
    {
        return [
            'List' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'meta' => [
                        'type' => 'object',
                        'properties' => [
                            'page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                    'collection' => [
                        'type' => 'object',
                        'properties' => [
                            'count' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                            'pages' => ['type' => 'integer'],
                            'next' => ['type' => 'string', 'nullable' => true],
                            'prev' => ['type' => 'string', 'nullable' => true],
                            'first' => ['type' => 'string', 'nullable' => true],
                            'last' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                ],
            ],
            'Error' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['error'],
                'properties' => [
                    'error' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['code', 'message'],
                        'properties' => [
                            'code' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'Brand' => self::object(['id' => 'string', 'slug' => 'string', 'name' => 'string', 'synonyms' => ['type' => 'array', 'items' => ['type' => 'string']], 'is_lcv_or_commercial' => 'boolean', 'logo_url_light' => self::nullable('string'), 'logo_url_dark' => self::nullable('string'), 'modified' => self::nullable('string')]),
            'Model' => self::object(['id' => 'string', 'brand_id' => 'string', 'slug' => 'string', 'name' => 'string', 'markets' => ['type' => 'array', 'items' => ['type' => 'string']], 'has_generations' => 'boolean', 'generations_count' => 'integer', 'modified' => self::nullable('string')]),
            'Generation' => self::object(['id' => 'string', 'model_id' => 'string', 'slug' => 'string', 'name' => self::nullable('string'), 'year_start' => self::nullable('integer'), 'year_end' => self::nullable('integer'), 'period_text' => self::nullable('string'), 'body_type' => self::nullable('string'), 'chassis_code' => self::nullable('string'), 'steering_wheel' => self::nullable('string'), 'market' => self::nullable('string'), 'display_label' => 'string', 'modified' => self::nullable('string')]),
            'Year' => self::object(['id' => 'string', 'year' => 'integer']),
            'GenerationModification' => self::object(['id' => 'string', 'generation_id' => 'string', 'name' => 'string', 'engine_volume_cc' => self::nullable('integer'), 'transmission' => self::nullable('string'), 'year_start' => self::nullable('integer'), 'year_end' => self::nullable('integer'), 'modified' => self::nullable('string')]),
            'ModificationSearch' => self::object(['id' => 'string', 'name' => 'string', 'brand_id' => 'string', 'model_id' => 'string', 'generation_id' => self::nullable('string'), 'engine_volume_cc' => self::nullable('integer'), 'transmission' => self::nullable('string'), 'drive_type' => self::nullable('string'), 'body_type' => self::nullable('string'), 'fuel' => self::nullable('string'), 'year_start' => self::nullable('integer'), 'year_end' => self::nullable('integer'), 'modified' => self::nullable('string')]),
            'Modification' => self::object([
                'id' => 'string',
                'name' => 'string',
                'engine_volume_cc' => self::nullable('integer'),
                'transmission' => self::nullable('string'),
                'drive_type' => self::nullable('string'),
                'body_type' => self::nullable('string'),
                'year_start' => self::nullable('integer'),
                'year_end' => self::nullable('integer'),
                'brand' => self::object(['id' => 'string', 'slug' => 'string', 'name' => 'string']),
                'model' => self::object(['id' => 'string', 'slug' => 'string', 'name' => 'string']),
                'generation' => self::nullableObject(['id' => 'string', 'name' => self::nullable('string')]),
                'modified' => self::nullable('string'),
            ]),
            'ModificationSpecs' => self::object([
                'modification_id' => 'string',
                'engine' => self::object(['volume_cc' => self::nullable('integer'), 'fuel' => self::nullable('string'), 'power_hp' => self::nullable('number')]),
                'transmission' => self::object(['type' => self::nullable('string'), 'drive' => self::nullable('string')]),
                'dimensions' => ['type' => 'object', 'nullable' => true, 'description' => 'Always null. Dimension columns are not stored.'],
                'performance' => ['type' => 'object', 'nullable' => true, 'description' => 'Always null. Performance columns are not stored. power_hp stays on engine.'],
                'consumption' => self::object(['city_l' => self::nullable('number'), 'highway_l' => self::nullable('number'), 'combined_l' => self::nullable('number'), 'unit' => 'string']),
            ]),
            'EquipmentItem' => self::object(['id' => 'string', 'category' => self::nullable('string'), 'name' => 'string', 'value' => self::nullable('string')]),
            'ModificationEquipment' => self::object([
                'modification_id' => 'string',
                'standard' => self::itemArray('EquipmentItem'),
                'optional' => self::itemArray('EquipmentItem'),
                'not_available' => self::itemArray('EquipmentItem'),
                'unknown' => self::itemArray('EquipmentItem'),
            ]),
            'Engine' => self::object(['id' => 'string', 'volume_cc' => self::nullable('integer'), 'fuel' => self::nullable('string'), 'power_hp' => self::nullable('number'), 'brand_id' => 'string', 'model_id' => 'string', 'modified' => self::nullable('string')]),
            'Body' => self::object(['id' => 'string', 'name' => 'string', 'count' => 'integer', 'modified' => self::nullable('string')]),
            'Fuel' => self::object(['id' => 'string', 'fuel' => self::nullable('string'), 'city_l' => self::nullable('number'), 'highway_l' => self::nullable('number'), 'combined_l' => self::nullable('number'), 'brand_id' => 'string', 'model_id' => 'string', 'modified' => self::nullable('string')]),
            'Color' => self::object(['id' => 'string', 'model_id' => 'string', 'brand_id' => 'string', 'name' => 'string', 'verified' => 'boolean', 'modified' => self::nullable('string')]),
            'Coverage' => self::object([
                'brands_count' => 'integer',
                'models_count' => 'integer',
                'generations_count' => 'integer',
                'models_with_generations_count' => 'integer',
                'models_without_generations_count' => 'integer',
                'modifications_count' => 'integer',
                'photos_count' => 'integer',
                'generations_with_photo_urls_count' => 'integer',
                'measured_at' => 'string',
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $endpoint
     * @return array<string, mixed>
     */
    private static function operation(array $endpoint): array
    {
        $parameters = [];
        foreach ($endpoint['path_params'] as $name => $description) {
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'description' => $description,
                'schema' => ['type' => $name === 'slug' ? 'string' : 'integer', 'minimum' => $name === 'slug' ? null : 1],
            ];
        }
        foreach ($parameters as &$parameter) {
            if ($parameter['schema']['minimum'] === null) {
                unset($parameter['schema']['minimum']);
            }
        }
        unset($parameter);

        if ($endpoint['mode'] === 'list') {
            foreach (CatalogApi::listParameters($endpoint['kind']) as $name) {
                $parameters[] = self::listParameter($name, $endpoint['kind']);
            }
        }

        $schema = match ($endpoint['mode']) {
            'list' => [
                'allOf' => [
                    ['$ref' => '#/components/schemas/List'],
                    ['type' => 'object', 'properties' => [
                        'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/'.$endpoint['item']]],
                    ]],
                ],
            ],
            'one' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['data'],
                'properties' => ['data' => ['$ref' => '#/components/schemas/'.$endpoint['item']]],
            ],
            default => ['$ref' => '#/components/schemas/Coverage'],
        };

        return [
            'tags' => ['Catalog'],
            'operationId' => $endpoint['operation_id'],
            'summary' => $endpoint['summary'],
            'description' => self::description($endpoint),
            'parameters' => $parameters,
            'responses' => self::responses($schema),
            'x-codeSamples' => self::codeSampleObjects($endpoint['example']),
            'x-geoapi-example' => $endpoint['example'],
            'x-geoapi-kind' => $endpoint['kind'],
            'x-geoapi-mode' => $endpoint['mode'],
        ];
    }

    /** @param array<string, mixed> $endpoint */
    private static function description(array $endpoint): string
    {
        $specific = $endpoint['mode'] === 'list'
            ? 'Pagination: page defaults to 1 (maximum 1000000). limit defaults to 100 and cannot exceed 1000. per_page is used only when limit is absent. direction defaults to asc and applies when sort is set. Omitting sort keeps this resource\'s default order. REST year lists are ascending. The widget list is newest-first.'
            : ($endpoint['mode'] === 'coverage'
                ? 'This route returns counts, not a list. year_from, q, sort, and modified_after return 422.'
                : 'This route returns one object in data. It has no pagination. Extra query parameters are ignored.');

        return $endpoint['purpose']."\n\n".$specific."\n\n".self::contract();
    }

    private static function contract(): string
    {
        return 'Authentication: Authorization: Bearer geoapi_sk_… from your server. Query strings, cookies, and a cabinet session are not credentials. geoapi_pk_ is the widget key, not a catalog bearer. Sanctum cars:read or * also requires X-CARAPI-PROJECT naming that user\'s active project. A secret key ignores X-CARAPI-PROJECT.'."\n\n"
            .'Errors, JSON error.code and error.message only, no request id: 401 unauthorized, 403 forbidden, 404 not_found, 405 method_not_allowed, 422 invalid_parameter, 429 RATE_LIMIT_EXCEEDED or DAILY_QUOTA_EXCEEDED or MONTHLY_QUOTA_EXCEEDED, 503 unavailable. Authentication runs before the method check. A non-GET request without an accepted key is 401, not 405. 405 is returned only after the key is accepted. Use GET.'."\n\n"
            .'Limits: one project shares 60 requests each UTC minute and 10 000 successful requests each UTC month with the widget. Those caps are active. A project that has not chosen a commercial plan keeps 10 000 successful requests each UTC month. Choosing the free plan sets that project to 100 successful requests each UTC month. An unlimited plan sends X-Quota-Limit: unlimited. The 2 000 successful requests per UTC day limit is not enforced in production today, because the daily check runs only when platform.project_limits.per_day exists. A missing limits row or a failed meter is 503, not an unmetered success. 401, 403, 404, 405, 422, 429, and 503 do not spend the month. Failures still use the minute window. On 429, wait Retry-After seconds. Do not retry 401, 403, 404, or 422. Success sends X-RateLimit-* for the minute and X-Quota-* for the month. 429 sends X-RateLimit-* for the window that was hit.'."\n\n"
            .'Coverage: JSON ids are strings. null means the stored value is missing. dimensions and performance are null. Colors include unverified rows with verified false. Photo files and VIN are not on these routes. Photo rights are cleared (owner-generated images); photo files are served by /api/models/{id}/photo, not by these routes.'."\n\n"
            .'Version '.self::VERSION.' at '.self::ORIGIN.'. The TypeScript SDK is packages/sdk-js 0.1.0 in this repository and is not on the public npm registry. The MCP server is packages/mcp-server 0.1.0 in this repository, speaks stdio, and is not on the public npm registry. The CLI is packages/cli 0.1.0 in this repository and is not published on the public npm registry. The widget is the browser embed and uses geoapi_pk_.';
    }

    /** @return array<string, mixed> */
    private static function listParameter(string $name, string $kind): array
    {
        if ($name === 'sort') {
            $enum = CatalogApi::sortAllowlist($kind);

            return [
                'name' => 'sort',
                'in' => 'query',
                'description' => 'Optional. One of '.implode(', ', $enum).'. direction applies when sort is set.',
                'schema' => ['type' => 'string', 'enum' => $enum],
            ];
        }

        $refs = [
            'page' => 'page',
            'limit' => 'limit',
            'per_page' => 'perPage',
            'direction' => 'direction',
            'q' => 'q',
            'modified_after' => 'modifiedAfter',
            'year_from' => 'yearFrom',
            'year_to' => 'yearTo',
            'body' => 'body',
            'fuel' => 'fuel',
            'drive' => 'drive',
            'transmission' => 'transmission',
            'brand_id' => 'brandId',
            'model_id' => 'modelId',
            'volume_from' => 'volumeFrom',
            'volume_to' => 'volumeTo',
        ];

        return ['$ref' => '#/components/parameters/'.$refs[$name]];
    }

    /** @return list<array{lang:string, source:string}> */
    private static function codeSampleObjects(string $example): array
    {
        $langs = ['curl' => 'curl', 'JavaScript' => 'JavaScript', 'Node' => 'Node.js', 'Python' => 'Python', 'PHP' => 'PHP'];
        $objects = [];
        foreach (self::codeSamples($example) as $label => $source) {
            $objects[] = ['lang' => $langs[$label], 'source' => $source];
        }

        return $objects;
    }

    /** @param array<string, mixed>|array{'$ref': string} $schema @return array<string, mixed> */
    private static function responses(array $schema): array
    {
        $error = static function (string $description, string $code, array $headers = []): array {
            $response = [
                'description' => $description,
                'content' => ['application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/Error'],
                    'example' => ['error' => ['code' => $code, 'message' => '…']],
                ]],
            ];
            if ($headers !== []) {
                $response['headers'] = $headers;
            }

            return $response;
        };
        $rateHeaders = [
            'X-RateLimit-Limit' => ['schema' => ['type' => 'integer']],
            'X-RateLimit-Remaining' => ['schema' => ['type' => 'integer']],
            'X-RateLimit-Reset' => ['schema' => ['type' => 'integer']],
        ];

        return [
            '200' => [
                'description' => 'OK',
                'headers' => $rateHeaders + [
                    'X-Quota-Limit' => ['schema' => ['type' => 'integer']],
                    'X-Quota-Remaining' => ['schema' => ['type' => 'integer']],
                    'X-Quota-Reset' => ['schema' => ['type' => 'integer']],
                ],
                'content' => ['application/json' => ['schema' => $schema]],
            ],
            '401' => $error('The key is missing, unknown, revoked, or expired.', 'unauthorized', [
                'WWW-Authenticate' => ['schema' => ['type' => 'string']],
            ]),
            '403' => $error('The key is valid, but the project, owner, or email does not allow the call.', 'forbidden'),
            '404' => $error('The record was not found.', 'not_found'),
            '405' => $error('Use GET.', 'method_not_allowed', [
                'Allow' => ['schema' => ['type' => 'string']],
            ]),
            '422' => $error('A parameter is invalid or is not accepted on this route.', 'invalid_parameter'),
            '429' => $error('RATE_LIMIT_EXCEEDED, DAILY_QUOTA_EXCEEDED, or MONTHLY_QUOTA_EXCEEDED.', 'RATE_LIMIT_EXCEEDED', $rateHeaders + [
                'Retry-After' => ['schema' => ['type' => 'integer']],
            ]),
            '503' => $error('The catalog or the meter is temporarily unavailable.', 'unavailable'),
        ];
    }

    /** @return array<string, mixed> */
    private static function postmanUrl(string $path): array
    {
        $postmanPath = (string) preg_replace('/\{([^}]+)\}/', ':$1', $path);
        $url = [
            'raw' => '{{baseUrl}}'.$postmanPath,
            'host' => ['{{baseUrl}}'],
            'path' => explode('/', ltrim($postmanPath, '/')),
        ];
        if (preg_match_all('/\{([^}]+)\}/', $path, $matches) === 1 || $matches[1] !== []) {
            $url['variable'] = [];
            foreach ($matches[1] as $name) {
                $url['variable'][] = [
                    'key' => $name,
                    'value' => '',
                    'description' => 'Replace with a value from an earlier response.',
                ];
            }
        }

        return $url;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function endpoints(): array
    {
        return [
            self::listEndpoint('listBrands', '/api/v1/brands', 'brands', 'Brand', 'Brands', '/api/v1/brands?limit=1', 'Brand list.', 'ბრენდების სია.', 'Brand list. page defaults to 1. limit defaults to 100 and cannot exceed 1000. per_page is the same parameter when limit is omitted. sort is name, id, or modified. direction is asc or desc. q searches the name.', 'ბრენდების სია. page ნაგულისხმევად 1-ია. limit ნაგულისხმევად 100-ია და 1000-ს არ აჭარბებს. per_page იგივე პარამეტრია, როცა limit არ არის. sort არის name, id ან modified. direction არის asc ან desc. q სახელში ეძებს.', ['data' => [['id' => '1', 'slug' => 'toyota', 'name' => 'Toyota', 'modified' => '2026-01-01T00:00:00Z']], 'meta' => ['page' => 1, 'per_page' => 100, 'total' => 1], 'collection' => ['count' => 1, 'total' => 1, 'pages' => 1, 'next' => null, 'prev' => null, 'first' => '/api/v1/brands?page=1&limit=100', 'last' => '/api/v1/brands?page=1&limit=100']]),
            self::listEndpoint('listBrandModels', '/api/v1/brands/{slug}/models', 'models', 'Model', 'Models for one brand slug', '/api/v1/brands/toyota/models?limit=1', 'Models for one brand. The slug comes from the brands response. body filters the stored body type.', 'ერთი ბრენდის მოდელები. slug ბრენდების პასუხიდან მოდის. body შენახულ ძარას ფილტრავს.', 'Models for one brand. Filter with body. The slug comes from the brands response.', 'ერთი ბრენდის მოდელები. ფილტრი body. slug ბრენდების პასუხიდან მოდის.', ['data' => [['id' => '10', 'slug' => 'camry', 'name' => 'Camry', 'generations_count' => 1]], 'meta' => ['page' => 1, 'per_page' => 100, 'total' => 1]], ['slug' => 'Brand slug from the brands response.']),
            self::listEndpoint('listModelYears', '/api/v1/models/{id}/years', 'years', 'Year', 'Years covered by one model. An open year_end stops at the current UTC year.', '/api/v1/models/10/years?limit=1', 'Years for one model, from generation and modification year_start and year_end. A null year_end stops at the current UTC year. q, modified_after, year_from, and year_to are rejected.', 'ერთი მოდელის წლები თაობისა და მოდიფიკაციის year_start და year_end-იდან. ცარიელი year_end მიმდინარე UTC წელზე ჩერდება. q, modified_after, year_from და year_to უარყოფილია.', 'Years for one model, taken from generation and modification year_start/year_end. An empty year_end stops at the current UTC year. The list is ascending unless sort says otherwise.', 'ერთი მოდელის წლები თაობისა და მოდიფიკაციის year_start/year_end-იდან. ცარიელი year_end მიმდინარე UTC წელზე ჩერდება. სია ზრდადია, თუ sort სხვას არ ამბობს.', ['data' => [['id' => '2018', 'year' => 2018]]], ['id' => 'Positive model id. Zero or a non-numeric id is 422. An unknown id is 404.']),
            self::listEndpoint('listModelGenerations', '/api/v1/models/{id}/generations', 'generations', 'Generation', 'Generations grouped by chassis for one model id', '/api/v1/models/10/generations?limit=1', 'Generations for one model, grouped by chassis. The public id is the smallest sibling id. Filter with year_from, year_to, and body.', 'ერთი მოდელის თაობები, დაჯგუფებული შასიით. საჯარო id უმცირესი დობილი id-ა. ფილტრები year_from, year_to და body.', 'Generations for one model id. Filter with year_from, year_to, and body. The public id is the smallest sibling id.', 'ერთი მოდელის თაობები. ფილტრები year_from, year_to და body. საჯარო id უმცირესი დობილი id-ა.', ['data' => [['id' => '100', 'name' => 'XV70', 'year_start' => 2017, 'year_end' => 2024]], 'meta' => ['page' => 1, 'per_page' => 100, 'total' => 1]], ['id' => 'Positive model id. Zero or a non-numeric id is 422. An unknown id is 404.']),
            self::listEndpoint('listGenerationModifications', '/api/v1/generations/{id}/modifications', 'modifications', 'GenerationModification', 'Modifications for one public generation id', '/api/v1/generations/100/modifications?limit=1', 'Modifications for one public generation id. Filters: year_from, year_to, body, fuel, drive, and transmission.', 'მოდიფიკაციები ერთი საჯარო თაობის id-სთვის. ფილტრები: year_from, year_to, body, fuel, drive და transmission.', 'Modifications for one public generation id. Filters: year_from, year_to, body, fuel, drive, and transmission.', 'ერთი საჯარო თაობის მოდიფიკაციები. ფილტრები: year_from, year_to, body, fuel, drive და transmission.', ['data' => [['id' => '1000', 'generation_id' => '100', 'name' => '2.5', 'year_start' => 2018]]], ['id' => 'Positive public generation id. A sibling restyling id is 404.']),
            self::oneEndpoint('getModificationSpecs', '/api/v1/modifications/{id}/specs', 'ModificationSpecs', 'Stored engine, transmission, and consumption fields for one modification. Unknown specification keys are omitted.', '/api/v1/modifications/1000/specs', 'Stored engine, transmission, and L/100km consumption for one modification. Missing values are null. dimensions and performance are null. Unknown specification keys are omitted.', 'შენახული ძრავა, ტრანსმისია და ხარჯი ლიტრში 100 კმ-ზე. ცარიელი მნიშვნელობა null-ია. dimensions და performance null-ია. უცნობი სპეციფიკაციის გასაღები არ ბრუნდება.', 'Stored engine, transmission, and L/100km consumption for one modification. Missing values are null. Dimensions are not stored.', 'შენახული ძრავა, ტრანსმისია და ხარჯი ლიტრში 100 კმ-ზე. ცარიელი მნიშვნელობა null-ია. ზომები არ ინახება.', ['data' => ['modification_id' => '1000', 'engine' => ['volume_cc' => 2494, 'fuel' => 'petrol', 'power_hp' => 200], 'dimensions' => null]]),
            self::oneEndpoint('getModificationEquipment', '/api/v1/modifications/{id}/equipment', 'ModificationEquipment', 'Equipment grouped as standard, optional, not_available, and unknown.', '/api/v1/modifications/1000/equipment', 'Equipment for one modification. included becomes standard, option becomes optional, not_available stays not_available, and any other status becomes unknown. Duplicate category, name, and status rows collapse.', 'ერთი მოდიფიკაციის კომპლექტაცია. included ხდება standard, option ხდება optional, not_available რჩება not_available, სხვა სტატუსი ხდება unknown. ერთნაირი კატეგორია, სახელი და სტატუსი ერთდება.', 'Equipment for one modification: standard, optional, not_available, and unknown.', 'ერთი მოდიფიკაციის კომპლექტაცია: standard, optional, not_available და unknown.', ['data' => ['modification_id' => '1000', 'standard' => [['id' => '1', 'name' => 'ABS']]]]),
            self::oneEndpoint('getModification', '/api/v1/modifications/{id}', 'Modification', 'One modification', '/api/v1/modifications/1000', 'One modification, including brand, model, and generation. modified is the UTC updated_at value. source_url is not returned.', 'ერთი მოდიფიკაცია, ბრენდით, მოდელით და თაობით. modified არის UTC updated_at. source_url არ ბრუნდება.', 'One modification. modified is the UTC updated_at value.', 'ერთი მოდიფიკაცია. modified არის UTC updated_at.', ['data' => ['id' => '1000', 'name' => '2.5', 'modified' => '2026-01-01T00:00:00Z']]),
            self::listEndpoint('listModifications', '/api/v1/modifications', 'modification_search', 'ModificationSearch', 'Modifications across brands, models, and generations', '/api/v1/modifications?limit=1', 'Search modifications across brands, models, and generations. Filters: year_from, year_to, body, fuel, drive, transmission, brand_id, model_id, and q.', 'მოდიფიკაციების ძებნა ბრენდებში, მოდელებსა და თაობებში. ფილტრები: year_from, year_to, body, fuel, drive, transmission, brand_id, model_id და q.', 'Modifications across brands, models, and generations. Filters: year_from, year_to, body, fuel, drive, transmission, brand_id, model_id, and q.', 'ყველა მოდიფიკაცია. ფილტრები: year_from, year_to, body, fuel, drive, transmission, brand_id, model_id და q.', ['data' => [['id' => '1000', 'name' => '2.5', 'year_start' => 2018, 'fuel' => 'petrol']], 'meta' => ['page' => 1, 'per_page' => 100, 'total' => 1]]),
            self::listEndpoint('listEngines', '/api/v1/engines', 'engines', 'Engine', 'Engine volume, fuel, and power stored on modifications', '/api/v1/engines?limit=1', 'Engine volume, fuel, and power stored on a modification. Filter with fuel, brand_id, model_id, volume_from, and volume_to. There is no engines table.', 'მოცულობა, საწვავი და სიმძლავრე, რაც მოდიფიკაციაზე ინახება. ფილტრები fuel, brand_id, model_id, volume_from და volume_to. engines ცხრილი არ არსებობს.', 'Volume, fuel, and power stored on a modification. Filter with fuel, brand_id, model_id, volume_from, and volume_to.', 'მოცულობა, საწვავი და სიმძლავრე, რაც მოდიფიკაციაზე ინახება. ფილტრები fuel, brand_id, model_id, volume_from და volume_to.', ['data' => [['id' => '1000', 'volume_cc' => 2494, 'fuel' => 'petrol', 'power_hp' => 200]]]),
            self::listEndpoint('listBodies', '/api/v1/bodies', 'bodies', 'Body', 'Distinct body types stored on models, generations, and modifications', '/api/v1/bodies?limit=1', 'Distinct body types that are stored. q searches the name. modified_after is rejected. There is no bodies table.', 'შენახული ძარის ტიპები. q სახელში ეძებს. modified_after უარყოფილია. bodies ცხრილი არ არსებობს.', 'Distinct body types that are stored. q searches the name. modified_after is not accepted.', 'შენახული ძარის ტიპები. q სახელში ეძებს. modified_after არ მიიღება.', ['data' => [['id' => 'sedan', 'name' => 'sedan', 'count' => 1]]]),
            self::listEndpoint('listFuel', '/api/v1/fuel', 'fuel', 'Fuel', 'Fuel consumption stored on modification specs', '/api/v1/fuel?limit=1', 'City, highway, and combined consumption stored on modification specs, in litres per 100 km. The same filters as modification search are accepted, including body, drive, and transmission.', 'ქალაქის, ტრასის და კომბინირებული ხარჯი modification specs-იდან, ლიტრი 100 კმ-ზე. მიიღება იგივე ფილტრები, რაც მოდიფიკაციების ძებნაზე, მათ შორის body, drive და transmission.', 'City, highway, and combined consumption stored on modification specs, in litres per 100 km. body, drive, and transmission are accepted.', 'ქალაქის, ტრასის და კომბინირებული ხარჯი modification specs-იდან, ლიტრი 100 კმ-ზე. body, drive და transmission მიიღება.', ['data' => [['id' => '1000', 'fuel' => 'petrol', 'city_l' => 9.1, 'combined_l' => 7.4]]]),
            self::listEndpoint('listColors', '/api/v1/colors', 'colors', 'Color', 'Colors stored on model photos', '/api/v1/colors?limit=1', 'Every color stored on a model photo. verified is false when the photo was not confirmed. Unverified rows stay in the list. The image is not included.', 'მოდელის ფოტოზე შენახული ყველა ფერი. verified არის false, თუ ფოტო დადასტურებული არ არის. დაუდასტურებელი სტრიქონი სიაში რჩება. სურათი პასუხში არ არის.', 'Every color stored on a model photo. verified is false when the photo was not confirmed. Unverified rows stay in the list. The image is not included.', 'მოდელის ფოტოზე შენახული ყველა ფერი. verified არის false, თუ ფოტო დადასტურებული არ არის. დაუდასტურებელი სტრიქონი სიაში რჩება. სურათი პასუხში არ არის.', ['data' => [['id' => '10', 'name' => 'white', 'verified' => true], ['id' => '11', 'name' => 'red', 'verified' => false]]]),
            [
                'operation_id' => 'getCoverage',
                'path' => '/api/v1/coverage',
                'kind' => 'coverage',
                'mode' => 'coverage',
                'item' => 'Coverage',
                'summary' => 'Catalog counts.',
                'example' => '/api/v1/coverage',
                'purpose' => 'Catalog counts measured when the request runs. These are not a quota.',
                'title_en' => 'Coverage',
                'title_ka' => 'დაფარვა',
                'guide_en' => 'Catalog counts. Filters are not accepted.',
                'guide_ka' => 'კატალოგის რაოდენობები. ფილტრები არ მიიღება.',
                'sample' => ['brands_count' => 1, 'models_count' => 1, 'generations_count' => 1],
                'path_params' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $sample
     * @param  array<string, string>  $pathParams
     * @return array<string, mixed>
     */
    private static function listEndpoint(string $operationId, string $path, string $kind, string $item, string $summary, string $example, string $purpose, string $purposeKa, string $guideEn, string $guideKa, array $sample, array $pathParams = []): array
    {
        unset($purposeKa);

        return [
            'operation_id' => $operationId,
            'path' => $path,
            'kind' => $kind,
            'mode' => 'list',
            'item' => $item,
            'summary' => $summary,
            'example' => $example,
            'purpose' => $purpose,
            'title_en' => self::englishTitle($operationId),
            'title_ka' => self::georgianTitle($operationId),
            'guide_en' => $guideEn,
            'guide_ka' => $guideKa,
            'sample' => $sample,
            'path_params' => $pathParams,
        ];
    }

    /**
     * @param  array<string, mixed>  $sample
     * @return array<string, mixed>
     */
    private static function oneEndpoint(string $operationId, string $path, string $item, string $summary, string $example, string $purpose, string $purposeKa, string $guideEn, string $guideKa, array $sample): array
    {
        unset($purposeKa);

        return [
            'operation_id' => $operationId,
            'path' => $path,
            'kind' => $item,
            'mode' => 'one',
            'item' => $item,
            'summary' => $summary,
            'example' => $example,
            'purpose' => $purpose,
            'title_en' => self::englishTitle($operationId),
            'title_ka' => self::georgianTitle($operationId),
            'guide_en' => $guideEn,
            'guide_ka' => $guideKa,
            'sample' => $sample,
            'path_params' => ['id' => 'Positive modification id. Zero or a non-numeric id is 422. An unknown id is 404.'],
        ];
    }

    private static function englishTitle(string $operationId): string
    {
        return match ($operationId) {
            'listBrands' => 'Brands',
            'listBrandModels' => 'Models',
            'listModelYears' => 'Years',
            'listModelGenerations' => 'Generations',
            'listGenerationModifications' => 'Generation modifications',
            'getModificationSpecs' => 'Specs',
            'getModificationEquipment' => 'Equipment',
            'getModification' => 'Modification',
            'listModifications' => 'Modifications',
            'listEngines' => 'Engines',
            'listBodies' => 'Bodies',
            'listFuel' => 'Fuel',
            'listColors' => 'Colors',
            default => 'Coverage',
        };
    }

    private static function georgianTitle(string $operationId): string
    {
        return match ($operationId) {
            'listBrands' => 'ბრენდები',
            'listBrandModels' => 'მოდელები',
            'listModelYears' => 'წლები',
            'listModelGenerations' => 'თაობები',
            'listGenerationModifications' => 'თაობის მოდიფიკაციები',
            'getModificationSpecs' => 'სპეციფიკაცია',
            'getModificationEquipment' => 'კომპლექტაცია',
            'getModification' => 'მოდიფიკაცია',
            'listModifications' => 'მოდიფიკაციები',
            'listEngines' => 'ძრავები',
            'listBodies' => 'ძარები',
            'listFuel' => 'საწვავი',
            'listColors' => 'ფერები',
            default => 'დაფარვა',
        };
    }

    /** @param array<string, mixed> $properties @return array<string, mixed> */
    private static function object(array $properties): array
    {
        $fields = [];
        foreach ($properties as $name => $type) {
            $fields[$name] = is_array($type) ? $type : ['type' => $type];
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($fields),
            'properties' => $fields,
        ];
    }

    /** @return array<string, mixed> */
    private static function nullable(string $type): array
    {
        return ['type' => $type, 'nullable' => true];
    }

    /** @param array<string, mixed> $properties @return array<string, mixed> */
    private static function nullableObject(array $properties): array
    {
        return self::object($properties) + ['nullable' => true];
    }

    /** @return array<string, mixed> */
    private static function itemArray(string $schema): array
    {
        return ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/'.$schema]];
    }
}
