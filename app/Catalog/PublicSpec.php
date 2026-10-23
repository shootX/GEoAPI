<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Billing\Plans;
use App\Billing\V2\Catalog;
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
                        'description' => 'Secret server key geoapi_sk_…. geoapi_pk_, a key in the URL query, a cookie, and a cabinet session are not accepted.',
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
            'cURL' => "curl \"{$url}\" \\\n  -H \"Authorization: Bearer geoapi_sk_…\"",
            'Node.js' => "// Server-side only: a geoapi_sk_ key must never reach the browser. In a web page use the widget (geoapi_pk_).\nconst response = await fetch({$encoded}, {\n  headers: { Authorization: 'Bearer geoapi_sk_…' },\n});\nconsole.log(await response.json());",
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
        $v2 = Flag::on();
        $limitCodes = $v2
            ? 'RATE_LIMIT_EXCEEDED, MONTHLY_QUOTA_EXCEEDED, PROJECT_CAP_EXCEEDED'
            : 'RATE_LIMIT_EXCEEDED, DAILY_QUOTA_EXCEEDED, MONTHLY_QUOTA_EXCEEDED';

        $rows = [
            [
                'id' => 'guide-quickstart',
                'title' => $english ? 'Quickstart' : 'სწრაფი დასაწყისი',
                'paragraphs' => $english ? [
                    'Sign up and confirm your email. Turn on the Cars API at /account/products. Then create a project and its secret key. The secret key (geoapi_sk_…) is shown only once, right after it is created. Store it on your server. Later cabinet pages do not show it again. Without a confirmed email or the Cars API, the key gets 403.',
                    'Build a vehicle picker in this order: GET /api/v1/brands → GET /api/v1/brands/{slug}/models → GET /api/v1/models/{id}/years → GET /api/v1/models/{id}/generations → GET /api/v1/generations/{id}/modifications. Take each slug and id from the previous response.',
                    'In a web page, use the widget with the public key (geoapi_pk_…). Never put the secret key in a page. The TypeScript SDK, the MCP server, and the CLI are not published yet. Until they are, call the REST API directly.',
                ] : [
                    'დარეგისტრირდით და დაადასტურეთ ელფოსტა. ჩართეთ Cars API გვერდზე /account/products. შემდეგ შექმენით პროექტი და მისი საიდუმლო გასაღები. საიდუმლო გასაღები (geoapi_sk_…) მხოლოდ ერთხელ, შექმნისთანავე გამოჩნდება. შეინახეთ ის სერვერზე. კაბინეტის შემდეგ გვერდებზე გასაღები აღარ გამოჩნდება. დაუდასტურებელი ელფოსტით ან გამორთული Cars API-ით გასაღები 403-ს მიიღებს.',
                    'მანქანის ამორჩევის ფორმა ააწყვეთ ამ თანმიმდევრობით: GET /api/v1/brands → GET /api/v1/brands/{slug}/models → GET /api/v1/models/{id}/years → GET /api/v1/models/{id}/generations → GET /api/v1/generations/{id}/modifications. ყოველი slug და id წინა პასუხიდან აიღეთ.',
                    'ვებგვერდზე გამოიყენეთ ვიჯეტი საჯარო გასაღებით (geoapi_pk_…). საიდუმლო გასაღები გვერდზე არასოდეს ჩასვათ. TypeScript SDK, MCP სერვერი და CLI ჯერ გამოქვეყნებული არ არის. მანამდე REST API პირდაპირ გამოიძახეთ.',
                ],
            ],
            [
                'id' => 'guide-auth',
                'title' => $english ? 'Authentication' : 'ავთენტიფიკაცია',
                'paragraphs' => $english ? [
                    'The catalog accepts the key only in the Authorization header: Authorization: Bearer geoapi_sk_…. A key in the URL query, a cookie, or a cabinet session is not accepted. The public key geoapi_pk_ works only in the widget.',
                    'A personal access token from the cabinet with the cars:read or * ability also needs the X-GEOAPI-PROJECT header with the id of one of your active projects. A secret key ignores X-GEOAPI-PROJECT. Its usage counts against the project the key belongs to.',
                    '401 unauthorized means the key is missing, unknown, revoked, or expired. The response has WWW-Authenticate: Bearer and no X-Quota-* headers. The key is checked before the HTTP method, so a POST without a valid key returns 401, not 405. 405 method_not_allowed comes only after the key is accepted. Use GET. 403 forbidden means the key is valid, but the project is disabled, the account is suspended, the email is not confirmed, or the Cars API is not turned on. One IP address may send 300 requests a minute. That limit is checked before the key, so many requests without a key can get 429 instead of 401.',
                    'Rotating a key creates a new key and revokes the old one. Re-enabling a project does not bring a revoked key back.',
                ] : [
                    'კატალოგი გასაღებს მხოლოდ HTTP სათაურიდან (header) იღებს: Authorization: Bearer geoapi_sk_…. URL-ის პარამეტრში, cookie-ში ან კაბინეტის სესიაში გადაცემული გასაღები არ მიიღება. საჯარო გასაღები geoapi_pk_ მხოლოდ ვიჯეტში მუშაობს.',
                    'კაბინეტის პერსონალურ ტოკენს, რომელსაც cars:read ან * უფლება აქვს, დამატებით სჭირდება სათაური X-GEOAPI-PROJECT თქვენი რომელიმე აქტიური პროექტის id-ით. საიდუმლო გასაღები X-GEOAPI-PROJECT-ს არ ითვალისწინებს. მოხმარება აღირიცხება იმ პროექტზე, რომელსაც გასაღები ეკუთვნის.',
                    '401 unauthorized ნიშნავს, რომ გასაღები არ არის მითითებული, უცნობია, გაუქმებულია ან ვადაგასულია. პასუხს აქვს WWW-Authenticate: Bearer და არ აქვს X-Quota-* სათაურები. გასაღები HTTP მეთოდამდე მოწმდება, ამიტომ სწორი გასაღების გარეშე POST მოთხოვნა 401-ს აბრუნებს და არა 405-ს. 405 method_not_allowed მხოლოდ გასაღების მიღების შემდეგ ბრუნდება. გამოიყენეთ GET. 403 forbidden ნიშნავს, რომ გასაღები სწორია, მაგრამ პროექტი გამორთულია, ანგარიში შეჩერებულია, ელფოსტა დაუდასტურებელია ან Cars API ჩართული არ არის. ერთი IP მისამართიდან წუთში 300 მოთხოვნაა დასაშვები. ეს ლიმიტი გასაღებამდე მოწმდება, ამიტომ გასაღების გარეშე ბევრმა მოთხოვნამ 401-ის ნაცვლად 429 შეიძლება მიიღოს.',
                    'გასაღების განახლებისას (როტაცია) იქმნება ახალი გასაღები, ძველი კი უქმდება. პროექტის ხელახლა ჩართვა გაუქმებულ გასაღებს არ აღადგენს.',
                ],
            ],
            [
                'id' => 'guide-dictionary',
                'title' => $english ? 'Data dictionary' : 'მონაცემების ლექსიკონი',
                'paragraphs' => $english ? [
                    'Ids in JSON are strings. A slug identifies a brand or a model in a URL. A model belongs to a brand, a generation belongs to a model, and a modification belongs to a generation. Rows of one chassis, such as a restyling, are grouped into one public generation. Its id is the smallest id in the group. A request with another id from the group returns 404.',
                    'year_start and year_end are the production years. An empty year_end means the model is still in production. Year lists stop at the current UTC year, and a later year_end is cut to the current year too. year_from and year_to are filters, not fields. A row matches when its years overlap the range, and a row without years always matches. REST year lists are in ascending order. The widget list starts with the newest year.',
                    'engine_volume_cc and volume_cc are in cubic centimetres. power_hp is horsepower and may be null. Fuel consumption is in litres per 100 km. null means the value is unknown. dimensions and performance are always null because this data is not collected yet.',
                    'Colors returns every stored color. verified is false when the photo was not checked. In a color row, id is the model id, so several colors of one model share one id. Photo rights are cleared, but the v1 API does not return photos yet. VIN lookup is not available.',
                ] : [
                    'JSON-ში id სტრიქონია (string). slug ბრენდს ან მოდელს URL-ში ასახელებს. მოდელი ბრენდს ეკუთვნის, თაობა მოდელს, მოდიფიკაცია კი თაობას. ერთი შასის ჩანაწერები, მაგალითად რესტაილინგი, ერთ საჯარო თაობად ერთიანდება. მისი id ამ ჯგუფის უმცირესი id არის. ჯგუფის სხვა id-ით მოთხოვნა 404-ს აბრუნებს.',
                    'year_start და year_end წარმოების წლებია. ცარიელი year_end ნიშნავს, რომ მოდელი ჯერ კიდევ იწარმოება. წლების სია მიმდინარე UTC წელზე ჩერდება და უფრო გვიანდელი year_end-იც მიმდინარე წლამდე იჭრება. year_from და year_to ფილტრებია და არა ველები. ჩანაწერი შედეგში ხვდება, თუ მისი წლები დიაპაზონს ეხება, ხოლო წლების გარეშე ჩანაწერი ყოველთვის ხვდება. REST-ის წლების სია ზრდადობით არის დალაგებული. ვიჯეტის სია უახლესი წლით იწყება.',
                    'engine_volume_cc და volume_cc კუბურ სანტიმეტრებშია. power_hp ცხენის ძალაა და შეიძლება null იყოს. საწვავის ხარჯი მოცემულია ლიტრებში 100 კმ-ზე. null ნიშნავს, რომ მნიშვნელობა უცნობია. dimensions და performance ყოველთვის null-ია, რადგან ეს მონაცემები ჯერ არ გროვდება.',
                    'ფერების მისამართი ყველა შენახულ ფერს აბრუნებს. verified არის false, თუ ფოტო შემოწმებული არ არის. ფერის ჩანაწერში id მოდელის id-ია, ამიტომ ერთი მოდელის რამდენიმე ფერს ერთი id აქვს. ფოტოს უფლებები მოწესრიგებულია, მაგრამ v1 API ფოტოებს ჯერ არ აბრუნებს. VIN-ით ძებნა ხელმისაწვდომი არ არის.',
                ],
            ],
            [
                'id' => 'guide-limits',
                'title' => $english ? 'Limits' : 'ლიმიტები',
                'paragraphs' => $english ? [
                    'A project that has not chosen a paid plan has a limit of 60 requests per UTC minute and a quota of 10 000 successful requests per UTC month. Choosing the free plan sets the quota to 100 successful requests per UTC month. Basic: 2 000 successful requests per UTC month for '.Plans::lari(Plans::tetri('basic', 'month')).' a month. Premium: unlimited requests for '.Plans::lari(Plans::tetri('premium', 'month')).' a month. A yearly payment is 12% cheaper than twelve monthly payments. Prices are in GEL and include VAT. The widget and the secret key share one counter.',
                    'The daily limit of 2 000 successful requests is not enforced in production today. If the service cannot read the project limits or count the request, it returns 503, never an unmetered success.',
                    'A successful response sends X-RateLimit-Limit, X-RateLimit-Remaining, and X-RateLimit-Reset for the minute limit, and X-Quota-Limit, X-Quota-Remaining, and X-Quota-Reset for the monthly quota. On an unlimited plan, X-Quota-Limit and X-Quota-Remaining are the word unlimited. 401 sends no X-Quota-* headers.',
                    '429 is a JSON error with a Retry-After header. Codes: '.$limitCodes.'. On 429, the X-RateLimit-* headers describe the limit that was hit. Wait the number of seconds in Retry-After, then retry. Do not retry 401, 403, 404, or 422. Errors are not counted against the monthly quota. 404 and 422 still count toward the minute limit. A new key does not reset the counters.',
                ] : [
                    'პროექტს, რომელსაც ფასიანი გეგმა არ აურჩევია, აქვს წუთის ლიმიტი 60 მოთხოვნა (UTC) და თვიური კვოტა 10 000 წარმატებული მოთხოვნა (UTC თვე). უფასო გეგმის არჩევისას კვოტა თვეში 100 წარმატებული მოთხოვნა ხდება. Basic: თვეში 2 000 წარმატებული მოთხოვნა, '.Plans::lari(Plans::tetri('basic', 'month')).' თვეში. Premium: შეუზღუდავი მოთხოვნები, '.Plans::lari(Plans::tetri('premium', 'month')).' თვეში. წლიური გადახდა 12 თვის ჯამზე 12%-ით იაფია. ფასი ლარშია და დღგ-ს შეიცავს. ვიჯეტი და საიდუმლო გასაღები ერთ მრიცხველს იზიარებენ.',
                    'დღიური ლიმიტი (2 000 წარმატებული მოთხოვნა) მოქმედ სერვისზე დღეს არ მოქმედებს. თუ სერვისი პროექტის ლიმიტებს ვერ წაიკითხავს ან მოთხოვნას ვერ დაითვლის, ის 503-ს აბრუნებს და მოთხოვნას აღურიცხავად არასოდეს ასრულებს.',
                    'წარმატებული პასუხი აგზავნის X-RateLimit-Limit, X-RateLimit-Remaining და X-RateLimit-Reset სათაურებს წუთის ლიმიტისთვის და X-Quota-Limit, X-Quota-Remaining და X-Quota-Reset სათაურებს თვიური კვოტისთვის. შეუზღუდავ გეგმაზე X-Quota-Limit და X-Quota-Remaining სიტყვა unlimited-ია. 401 X-Quota-* სათაურებს არ აგზავნის.',
                    '429 პასუხი JSON-ია და შეიცავს Retry-After სათაურს. კოდები: '.$limitCodes.'. 429-ზე X-RateLimit-* აღწერს იმ ლიმიტს, რომელიც ამოიწურა. დაელოდეთ Retry-After-ში მითითებულ წამებს და შემდეგ სცადეთ ხელახლა. 401, 403, 404 და 422 ხელახლა არ გაგზავნოთ. შეცდომები თვიურ კვოტაში არ ითვლება. 404 და 422 წუთის ლიმიტში მაინც ითვლება. ახალი გასაღები მრიცხველებს არ ანულებს.',
                ],
            ],
            [
                'id' => 'guide-changelog',
                'title' => $english ? 'Changelog' : 'ცვლილებები',
                'paragraphs' => $english ? [
                    self::VERSION.' (2026-10-22): list responses have data, meta, and collection. Added years, specs, equipment, and colors with the verified field. No field is deprecated in this version.',
                    'The API is served at '.self::ORIGIN.'. The TypeScript SDK, the MCP server, and the CLI (all 0.1.0) are not published yet.',
                ] : [
                    self::VERSION.' (2026-10-22): სიის პასუხს აქვს data, meta და collection. დაემატა წლები, სპეციფიკაცია, კომპლექტაცია და ფერები verified ველით. ამ ვერსიაში არცერთი ველი მოძველებულად (deprecated) არ არის მონიშნული.',
                    'API მუშაობს მისამართზე '.self::ORIGIN.'. TypeScript SDK, MCP სერვერი და CLI (სამივე 0.1.0) ჯერ გამოქვეყნებული არ არის.',
                ],
            ],
            [
                'id' => 'guide-recipes',
                'title' => $english ? 'Recipes' : 'რეცეპტები',
                'paragraphs' => $english ? [
                    'Vehicle picker: brands, then models by slug, then years, then generations, then the modifications of the chosen public generation id. year_from and year_to work on generations and modifications. The years route returns 422 if you send them.',
                    'Inventory search: GET /api/v1/modifications with brand_id, model_id, year_from, and fuel. Specs and equipment: GET /api/v1/modifications/{id}/specs and /equipment. Treat null as unknown. dimensions and performance are always null.',
                    'Photos: the v1 API does not return photos yet. Show the color name instead and do not build image URLs yourself. VIN lookup is not available.',
                ] : [
                    'მანქანის ამორჩევის ფორმა: ბრენდები, შემდეგ მოდელები slug-ით, შემდეგ წლები, შემდეგ თაობები და ბოლოს არჩეული საჯარო თაობის მოდიფიკაციები. year_from და year_to თაობებსა და მოდიფიკაციებზე მუშაობს. წლების მისამართი 422-ს აბრუნებს, თუ მათ გაგზავნით.',
                    'მარაგის ძებნა: GET /api/v1/modifications პარამეტრებით brand_id, model_id, year_from და fuel. სპეციფიკაცია და კომპლექტაცია: GET /api/v1/modifications/{id}/specs და /equipment. null უცნობ მნიშვნელობად ჩათვალეთ. dimensions და performance ყოველთვის null-ია.',
                    'ფოტოები: v1 API ფოტოებს ჯერ არ აბრუნებს. აჩვენეთ ფერის სახელი და სურათის URL თავად არ ააწყოთ. VIN-ით ძებნა ხელმისაწვდომი არ არის.',
                ],
            ],
            [
                'id' => 'guide-packages',
                'title' => $english ? 'SDK, MCP, and CLI' : 'SDK, MCP და CLI',
                'paragraphs' => $english ? [
                    'TypeScript SDK 0.1.0. It is not published yet, so it cannot be installed today. It calls this REST API and needs Node 20 or newer. Create a client with new GeoAPI({ apiKey }). Picker: brands.list, models.byBrand, years.byModel, generations.byModel, and generations.modifications. Search and details: modifications.list, modifications.get, modifications.specs, and modifications.equipment. Options: retries (default 2), timeout (default 30 000 ms), and baseUrl. It retries only 429 and 503. It waits Retry-After seconds, at most 2 seconds. Without Retry-After it waits 250 ms times the attempt number. GeoAPIError has status, code, retryAfter, and requestId. requestId is null because this API does not send a request id. The SDK refuses a secret key in a browser page or a web worker.',
                    'MCP server 0.1.0. It is not published yet. It runs locally over stdio and is not an HTTP endpoint. Put the secret key in the GEOAPI_API_KEY environment variable of the host, never in a tool argument. The tools are geoapi_list_brands, geoapi_list_models, geoapi_list_generations, geoapi_list_years, geoapi_list_modifications, geoapi_get_modification, geoapi_get_specs, geoapi_get_equipment, and geoapi_get_coverage. For example, the prompt "list BMW models and one generation" uses geoapi_list_models and geoapi_list_generations.',
                    'CLI 0.1.0. It is not published yet. It uses the TypeScript SDK and needs Node 20 or newer. The key comes from GEOAPI_API_KEY and is never passed as a flag. Examples: geoapi brands list --limit 20 --json, geoapi models list --brand bmw, geoapi modifications list --model-id 314 --year-from 2020, and geoapi coverage. --json writes one JSON document to stdout. Errors go to stderr. Exit codes: 0 success, 2 invalid input or not found, 3 authentication (401 or 403), 4 rate limit (429), 5 network or service error. The SDK retries 429 and 503 twice by default.',
                ] : [
                    'TypeScript SDK 0.1.0. ის ჯერ გამოქვეყნებული არ არის, ამიტომ დღეს მისი დაყენება შეუძლებელია. ის ამ REST API-ს იძახებს და სჭირდება Node 20 ან უფრო ახალი. კლიენტი იქმნება new GeoAPI({ apiKey })-ით. ამორჩევის ფორმისთვის: brands.list, models.byBrand, years.byModel, generations.byModel და generations.modifications. ძებნა და დეტალები: modifications.list, modifications.get, modifications.specs და modifications.equipment. პარამეტრები: retries (ნაგულისხმევად 2), timeout (ნაგულისხმევად 30 000 მწ) და baseUrl. ხელახლა მხოლოდ 429-სა და 503-ს ცდის. ელოდება Retry-After-ში მითითებულ წამებს, მაქსიმუმ 2 წამს. Retry-After-ის გარეშე ელოდება 250 მწ-ს გამრავლებულს მცდელობის ნომერზე. GeoAPIError-ს აქვს status, code, retryAfter და requestId. requestId არის null, რადგან ეს API მოთხოვნის id-ს არ აგზავნის. SDK საიდუმლო გასაღებით ბრაუზერის გვერდზე ან web worker-ში არ ეშვება.',
                    'MCP სერვერი 0.1.0. ის ჯერ გამოქვეყნებული არ არის. ის ლოკალურად, stdio-ით მუშაობს და HTTP მისამართი არ არის. საიდუმლო გასაღები ჩაწერეთ ჰოსტის გარემოს ცვლადში GEOAPI_API_KEY და არასოდეს ხელსაწყოს არგუმენტში. ხელსაწყოებია geoapi_list_brands, geoapi_list_models, geoapi_list_generations, geoapi_list_years, geoapi_list_modifications, geoapi_get_modification, geoapi_get_specs, geoapi_get_equipment და geoapi_get_coverage. მაგალითად, მოთხოვნა „აჩვენე BMW-ის მოდელები და ერთი თაობა“ იყენებს geoapi_list_models-სა და geoapi_list_generations-ს.',
                    'CLI 0.1.0. ის ჯერ გამოქვეყნებული არ არის. ის TypeScript SDK-ს იყენებს და სჭირდება Node 20 ან უფრო ახალი. გასაღები აიღება GEOAPI_API_KEY ცვლადიდან და ბრძანების პარამეტრად არასოდეს გადაეცემა. მაგალითები: geoapi brands list --limit 20 --json, geoapi models list --brand bmw, geoapi modifications list --model-id 314 --year-from 2020 და geoapi coverage. --json stdout-ზე ერთ JSON დოკუმენტს წერს. შეცდომები stderr-ზე გადის. გამოსვლის კოდები (exit code): 0 წარმატება, 2 არასწორი შეყვანა ან ჩანაწერი ვერ მოიძებნა, 3 ავთენტიფიკაცია (401 ან 403), 4 ლიმიტი (429), 5 ქსელის ან სერვისის შეცდომა. SDK 429-სა და 503-ს ნაგულისხმევად ორჯერ ცდის ხელახლა.',
                ],
            ],
            [
                'id' => 'guide-errors',
                'title' => $english ? 'Errors' : 'შეცდომები',
                'paragraphs' => $english ? [
                    'Every error is JSON with only error.code and error.message. There is no request id. Limit codes are upper case. Other codes are lower case.',
                    '401 unauthorized: the key is missing or no longer works. The key is checked before the method, so a non-GET request without a valid key returns 401, not 405. 403 forbidden: the key is valid, but the project is disabled, the account is suspended, the email is not confirmed, or the Cars API is not turned on. 404 not_found: the record or the address was not found. 405 method_not_allowed is returned only after the key is accepted. Use GET. 422 invalid_parameter: page, limit, per_page, sort, direction, a filter, or an id is invalid. 429 uses '.$limitCodes.'. 503 unavailable: the service or the request counter is temporarily unavailable.',
                    'Messages are in Georgian when Accept-Language is empty or starts with ka. Any other value, such as en, returns the message in English.',
                ] : [
                    'ყოველი შეცდომა JSON-ია მხოლოდ ორი ველით: error.code და error.message. მოთხოვნის id არ ბრუნდება. ლიმიტის კოდები დიდი ასოებითაა, დანარჩენი კოდები პატარა ასოებით.',
                    '401 unauthorized: გასაღები არ არის მითითებული ან აღარ მუშაობს. გასაღები მეთოდამდე მოწმდება, ამიტომ სწორი გასაღების გარეშე არა-GET მოთხოვნა 401-ს აბრუნებს და არა 405-ს. 403 forbidden: გასაღები სწორია, მაგრამ პროექტი გამორთულია, ანგარიში შეჩერებულია, ელფოსტა დაუდასტურებელია ან Cars API ჩართული არ არის. 404 not_found: ჩანაწერი ან მისამართი ვერ მოიძებნა. 405 method_not_allowed მხოლოდ გასაღების მიღების შემდეგ ბრუნდება. გამოიყენეთ GET. 422 invalid_parameter: page, limit, per_page, sort, direction, ფილტრი ან id არასწორია. 429 იყენებს კოდებს: '.$limitCodes.'. 503 unavailable: სერვისი ან მოთხოვნების მრიცხველი დროებით მიუწვდომელია.',
                    'შეტყობინება ქართულია, როცა Accept-Language ცარიელია ან ka-თი იწყება. ნებისმიერი სხვა მნიშვნელობისას, მაგალითად en, შეტყობინება ინგლისურია.',
                ],
            ],
        ];

        if ($v2) {
            foreach ($rows as &$row) {
                if (($row['id'] ?? '') !== 'guide-limits') {
                    continue;
                }
                $starter = Plans::lari(Catalog::tetri('starter', 'month'));
                $growth = Plans::lari(Catalog::tetri('growth', 'month'));
                $row['paragraphs'][0] = $english
                    ? 'All projects in a workspace share one monthly quota. Free: 1 000 successful requests a month, one active project, and 15 requests a minute. Starter: 25 000 requests a month, 3 projects, and 60 requests a minute for '.$starter.' a month. Growth: 150 000 requests a month, 10 projects, and 120 requests a minute for '.$growth.' a month. A yearly payment is 15% cheaper than twelve monthly payments, and the quota still resets every billing month. Prices are in GEL and include VAT. Nothing is charged automatically: you pay only when you renew. Business is not on sale yet because its minute limit has to be measured first. The widget and the secret key share one counter. A new project or a new key does not reset usage.'
                    : 'სამუშაო სივრცის ყველა პროექტი ერთ თვიურ კვოტას იზიარებს. უფასო გეგმა: თვეში 1 000 წარმატებული მოთხოვნა, ერთი აქტიური პროექტი და წუთში 15 მოთხოვნა. Starter: თვეში 25 000 მოთხოვნა, 3 პროექტი და წუთში 60 მოთხოვნა, '.$starter.' თვეში. Growth: თვეში 150 000 მოთხოვნა, 10 პროექტი და წუთში 120 მოთხოვნა, '.$growth.' თვეში. წლიური გადახდა 12 თვის ჯამზე 15%-ით იაფია, კვოტა კი მაინც ყოველ საბილინგო თვეში ნულდება. ფასი ლარშია და დღგ-ს შეიცავს. თანხა ავტომატურად არ ჩამოიჭრება: იხდით მხოლოდ მაშინ, როცა თავად განაახლებთ. Business ჯერ არ იყიდება: ჯერ მისი წუთის ლიმიტი უნდა გაიზომოს. ვიჯეტი და საიდუმლო გასაღები ერთ მრიცხველს იზიარებენ. ახალი პროექტი ან ახალი გასაღები მოხმარებას არ ანულებს.';
                $row['paragraphs'][1] = $english
                    ? 'Prepaid request packs are valid for 90 days and are used after the monthly quota runs out. A project can also have its own monthly cap. When the cap is reached, the API returns PROJECT_CAP_EXCEEDED. There is no daily limit. If the service cannot count the request, it returns 503, never an unmetered success.'
                    : 'წინასწარ ნაყიდი მოთხოვნების პაკეტი 90 დღე მოქმედებს და თვიური კვოტის ამოწურვის შემდეგ იხარჯება. პროექტს შეიძლება საკუთარი თვიური ზღვარიც ჰქონდეს. ზღვრის მიღწევისას API აბრუნებს PROJECT_CAP_EXCEEDED-ს. დღიური ლიმიტი არ არის. თუ სერვისი მოთხოვნას ვერ დაითვლის, ის 503-ს აბრუნებს და მოთხოვნას აღურიცხავად არასოდეს ასრულებს.';
            }
            unset($row);
        }

        return $rows;
    }

    public static function widgetText(string $locale): string
    {
        if ($locale === 'en') {
            return 'The widget calls these POST routes, where {public-key} is the project public key: /widget/v1/{public-key}/brands, /widget/v1/{public-key}/brands/{slug}/models, /widget/v1/{public-key}/models/{id}/years, /widget/v1/{public-key}/models/{id}/generations, and /widget/v1/{public-key}/generations/{id}/modifications. The years are the same as GET /api/v1/models/{id}/years but start with the newest year. A project public key is geoapi_pk_ followed by 32 hexadecimal characters. The demo key works only on this site, so replace it with your own geoapi_pk_ key. The geoapi:change event carries brand_id, model_id, year, body_type, generation_id, and modification_id, plus the display names brand_name, model_name, year_name, body_name, generation_name, and modification_name. modification_id stays null until a modification is chosen. The widget answers only sites on the project allowed-origins list. A non-browser client can fake the Origin header, so the list is not full protection. The script never contains geoapi_sk_.';
        }

        return 'ვიჯეტი იძახებს ამ POST მისამართებს, სადაც {public-key} პროექტის საჯარო გასაღებია: /widget/v1/{public-key}/brands, /widget/v1/{public-key}/brands/{slug}/models, /widget/v1/{public-key}/models/{id}/years, /widget/v1/{public-key}/models/{id}/generations და /widget/v1/{public-key}/generations/{id}/modifications. წლები იგივეა, რაც GET /api/v1/models/{id}/years-ში, მაგრამ უახლესი წლით იწყება. პროექტის საჯარო გასაღები არის geoapi_pk_ და 32 თექვსმეტობითი სიმბოლო. demo გასაღები მხოლოდ ამ საიტზე მუშაობს, ამიტომ ის საკუთარი geoapi_pk_ გასაღებით შეცვალეთ. geoapi:change მოვლენა შეიცავს ველებს brand_id, model_id, year, body_type, generation_id და modification_id, ასევე საჩვენებელ სახელებს brand_name, model_name, year_name, body_name, generation_name და modification_name. modification_id null-ია, სანამ მოდიფიკაციას აირჩევთ. ვიჯეტი მხოლოდ იმ საიტებს პასუხობს, რომლებიც პროექტის დაშვებული origin-ების სიაშია. არაბრაუზერულ კლიენტს Origin სათაურის გაყალბება შეუძლია, ამიტომ ეს სია სრული დაცვა არ არის. სკრიპტი geoapi_sk_ გასაღებს არასოდეს შეიცავს.';
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
            .'The dimensions and performance groups are always null because this data is not collected yet. '
            .'Colors returns every stored color with a verified flag. '
            .'Document version '.self::VERSION.' matches the deployed catalog. '
            .'The TypeScript SDK, the MCP server, and the CLI (all 0.1.0) are not published yet. '
            .'The host is '.self::ORIGIN.'.';
    }

    /** @return array<string, mixed> */
    private static function parameters(): array
    {
        return [
            'page' => self::queryParameter('page', 'Page number. Default 1. Maximum 1000000.', ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000000, 'default' => 1]),
            'limit' => self::queryParameter('limit', 'Page size, from 1 to 1000. Default 100. per_page is ignored when limit is present.', ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 100]),
            'perPage' => self::queryParameter('per_page', 'Alias of limit. Ignored when limit is present.', ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000]),
            'direction' => self::queryParameter('direction', 'asc or desc. Default asc. Applied when sort is set.', ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc']),
            'q' => self::queryParameter('q', 'Case-insensitive search, 1 to 100 characters. It searches the name; on generations also the chassis code, and on colors the color name. Control characters are rejected. Not accepted on years.', ['type' => 'string', 'minLength' => 1, 'maxLength' => 100]),
            'modifiedAfter' => self::queryParameter('modified_after', 'UTC timestamp. Returns rows whose modified value is newer. Example 2026-01-01T00:00:00Z. Not accepted on years or bodies.', ['type' => 'string', 'format' => 'date-time']),
            'yearFrom' => self::queryParameter('year_from', 'Inclusive. Matches rows whose year_start to year_end range reaches this year or later. Rows without years always match. Integer from 1886 to 2100. A filter, not a field.', ['type' => 'integer', 'minimum' => 1886, 'maximum' => 2100]),
            'yearTo' => self::queryParameter('year_to', 'Inclusive. Matches rows whose year_start to year_end range starts in this year or earlier. Works alone or with year_from. Rows without years always match. Integer from 1886 to 2100.', ['type' => 'integer', 'minimum' => 1886, 'maximum' => 2100]),
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
            'Generation' => self::object(['id' => 'string', 'model_id' => 'string', 'slug' => 'string', 'name' => self::nullable('string'), 'year_start' => self::nullable('integer'), 'year_end' => self::nullable('integer'), 'period_text' => self::nullable('string'), 'body_type' => self::nullable('string'), 'chassis_code' => self::nullable('string'), 'steering_wheel' => self::nullable('string'), 'market' => ['type' => 'string', 'nullable' => true, 'description' => 'Always null for now.'], 'display_label' => 'string', 'modified' => self::nullable('string')]),
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
                'dimensions' => ['type' => 'object', 'nullable' => true, 'description' => 'Always null. Dimensions are not collected yet.'],
                'performance' => ['type' => 'object', 'nullable' => true, 'description' => 'Always null. Performance data is not collected yet. power_hp is in engine.'],
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
            'Color' => self::object(['id' => ['type' => 'string', 'description' => 'The model id. Several colors of one model share it, so it is not unique.'], 'model_id' => 'string', 'brand_id' => 'string', 'name' => 'string', 'verified' => 'boolean', 'modified' => self::nullable('string')]),
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
            ? 'Pagination: page defaults to 1 (maximum 1000000). limit defaults to 100 and cannot exceed 1000. per_page is used only when limit is absent. direction defaults to asc and applies when sort is set. Without sort, this resource keeps its default order. REST year lists are ascending. The widget list starts with the newest year.'
            : ($endpoint['mode'] === 'coverage'
                ? 'This route returns counts, not a list. Filters, q, sort, and modified_after return 422.'
                : 'This route returns one object in data. It has no pagination. Extra query parameters are ignored.');

        return $endpoint['purpose']."\n\n".$specific."\n\n".self::contract();
    }

    private static function contract(): string
    {
        $limitCodes = Flag::on()
            ? 'RATE_LIMIT_EXCEEDED, MONTHLY_QUOTA_EXCEEDED, or PROJECT_CAP_EXCEEDED'
            : 'RATE_LIMIT_EXCEEDED, DAILY_QUOTA_EXCEEDED, or MONTHLY_QUOTA_EXCEEDED';

        return 'Authentication: send Authorization: Bearer geoapi_sk_… from your server. A key in the URL query, a cookie, or a cabinet session is not accepted. geoapi_pk_ is the widget key and does not work here. A cabinet personal access token with cars:read or * also needs X-GEOAPI-PROJECT with the id of one of your active projects. A secret key ignores X-GEOAPI-PROJECT. Before using a key, confirm your email and turn on the Cars API at /account/products, or the key gets 403.'."\n\n"
            .'Errors are JSON with only error.code and error.message, and no request id: 401 unauthorized, 403 forbidden, 404 not_found, 405 method_not_allowed, 422 invalid_parameter, 429 '.$limitCodes.', 503 unavailable. The key is checked before the method, so a non-GET request without a valid key returns 401, not 405. 405 is returned only after the key is accepted. Use GET. One IP address may send 300 requests a minute. That limit is checked before the key, so it can return 429 before 401.'."\n\n"
            .(Flag::on() ? 'Limits: one workspace subscription and one monthly quota shared by all its projects and the widget. Free: 1 000 successful requests a month, 1 active project, 15 requests a minute. Starter: 25 000, 3 projects, 60 a minute. Growth: 150 000, 10 projects, 120 a minute. The quota resets every billing month, including on a yearly payment. Prepaid packs (90 days) are spent after the quota. Older plans may still be unlimited and then send X-Quota-Limit: unlimited. A per-project cap is optional and returns PROJECT_CAP_EXCEEDED. There is no daily limit. If the request cannot be counted, the API returns 503.' : 'Limits: a project shares 60 requests per UTC minute and its monthly quota with the widget. A project that has not chosen a paid plan has 10 000 successful requests per UTC month. Choosing the free plan sets that to 100. An unlimited plan sends X-Quota-Limit: unlimited. The daily limit of 2 000 successful requests is not enforced in production today. If the request cannot be counted, the API returns 503, never an unmetered success. Errors are not counted against the monthly quota. 404 and 422 still count toward the minute limit. On 429, wait Retry-After seconds. Do not retry 401, 403, 404, or 422. A success sends X-RateLimit-* for the minute and X-Quota-* for the month. 429 sends X-RateLimit-* for the limit that was hit.')."\n\n"
            .'Data: ids are strings. null means the value is unknown. dimensions and performance are always null. Colors include unchecked rows with verified false, and a color id is the model id. Photo rights are cleared, but the v1 API does not return photos yet. VIN lookup is not available.'."\n\n"
            .'Version '.self::VERSION.' at '.self::ORIGIN.'. The TypeScript SDK, the MCP server (stdio), and the CLI (all 0.1.0) are not published yet. The widget is the browser embed and uses geoapi_pk_.';
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
        $langs = ['cURL' => 'curl', 'Node.js' => 'Node.js', 'Python' => 'Python', 'PHP' => 'PHP'];
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
        $quota = [
            'description' => 'An integer, or the word unlimited on an unlimited plan.',
            'schema' => ['oneOf' => [['type' => 'integer'], ['type' => 'string', 'enum' => ['unlimited']]]],
        ];

        return [
            '200' => [
                'description' => 'OK',
                'headers' => $rateHeaders + [
                    'X-Quota-Limit' => $quota,
                    'X-Quota-Remaining' => $quota,
                    'X-Quota-Reset' => ['schema' => ['type' => 'integer']],
                ],
                'content' => ['application/json' => ['schema' => $schema]],
            ],
            '401' => $error('The key is missing, unknown, revoked, or expired.', 'unauthorized', [
                'WWW-Authenticate' => ['schema' => ['type' => 'string']],
            ]),
            '403' => $error('The key is valid, but the project is disabled, the account is suspended, the email is not confirmed, or the Cars API is not turned on.', 'forbidden'),
            '404' => $error('The record or the address was not found.', 'not_found'),
            '405' => $error('Use GET.', 'method_not_allowed', [
                'Allow' => ['schema' => ['type' => 'string']],
            ]),
            '422' => $error('A parameter is invalid or is not accepted on this route.', 'invalid_parameter'),
            '429' => $error(Flag::on() ? 'RATE_LIMIT_EXCEEDED, MONTHLY_QUOTA_EXCEEDED, or PROJECT_CAP_EXCEEDED.' : 'RATE_LIMIT_EXCEEDED, DAILY_QUOTA_EXCEEDED, or MONTHLY_QUOTA_EXCEEDED.', 'RATE_LIMIT_EXCEEDED', $rateHeaders + [
                'Retry-After' => ['schema' => ['type' => 'integer']],
            ]),
            '503' => $error('The catalog or the request counter is temporarily unavailable.', 'unavailable'),
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
        $modified = '2026-01-01T00:00:00Z';
        $modelId = ['id' => 'Positive model id. Zero or a non-numeric id returns 422. An unknown id returns 404.'];

        return [
            self::listEndpoint('listBrands', '/api/v1/brands', 'brands', 'Brand', 'Brands', '/api/v1/brands?limit=1', 'Brand list. page defaults to 1. limit defaults to 100 and cannot exceed 1000. per_page is the same parameter when limit is omitted. sort is name, id, or modified. direction is asc or desc. q searches the name.', 'ბრენდების სია.', 'Brand list. page defaults to 1. limit defaults to 100 and cannot exceed 1000. per_page is the same parameter when limit is omitted. sort is name, id, or modified. direction is asc or desc. q searches the name.', 'ბრენდების სია. page ნაგულისხმევად 1-ია. limit ნაგულისხმევად 100-ია და 1000-ს არ აღემატება. per_page იგივე პარამეტრია, როცა limit მითითებული არ არის. sort არის name, id ან modified. direction არის asc ან desc. q სახელში ეძებს.', self::listSample('/api/v1/brands', ['id' => '1', 'slug' => 'toyota', 'name' => 'Toyota', 'synonyms' => ['Тойота'], 'is_lcv_or_commercial' => false, 'logo_url_light' => null, 'logo_url_dark' => null, 'modified' => $modified], 120)),
            self::listEndpoint('listBrandModels', '/api/v1/brands/{slug}/models', 'models', 'Model', 'Models for one brand slug', '/api/v1/brands/toyota/models?limit=1', 'Models for one brand. The slug comes from the brands response. body filters by body type.', 'ერთი ბრენდის მოდელები.', 'Models for one brand. Take the slug from the brands response. Filter: body (body type).', 'ერთი ბრენდის მოდელები. slug ბრენდების პასუხიდან აიღეთ. ფილტრი: body (ძარის ტიპი).', self::listSample('/api/v1/brands/toyota/models', ['id' => '10', 'brand_id' => '1', 'slug' => 'camry', 'name' => 'Camry', 'markets' => ['JP', 'US'], 'has_generations' => true, 'generations_count' => 1, 'modified' => $modified], 40), ['slug' => 'Brand slug from the brands response. An unknown slug returns 404.']),
            self::listEndpoint('listModelYears', '/api/v1/models/{id}/years', 'years', 'Year', 'Years covered by one model. The list stops at the current UTC year.', '/api/v1/models/10/years?limit=1', 'Years for one model, from the year_start and year_end of its generations and modifications. The list stops at the current UTC year. q, modified_after, year_from, and year_to return 422.', 'ერთი მოდელის წლები.', 'Years for one model, from the year_start and year_end of its generations and modifications. The list stops at the current UTC year. It is ascending unless sort and direction say otherwise.', 'ერთი მოდელის წლები მისი თაობებისა და მოდიფიკაციების year_start-ისა და year_end-ის მიხედვით. სია მიმდინარე UTC წელზე ჩერდება. სია ზრდადობით არის დალაგებული, თუ sort და direction სხვა რიგს არ მიუთითებს.', self::listSample('/api/v1/models/10/years', ['id' => '2018', 'year' => 2018], 8), $modelId),
            self::listEndpoint('listModelGenerations', '/api/v1/models/{id}/generations', 'generations', 'Generation', 'Generations grouped by chassis for one model id', '/api/v1/models/10/generations?limit=1', 'Generations for one model, grouped by chassis. The public id is the smallest id in the chassis group. Filter with year_from, year_to, and body.', 'ერთი მოდელის თაობები.', 'Generations for one model, grouped by chassis. Filters: year_from, year_to, and body. The public id is the smallest id in the chassis group.', 'ერთი მოდელის თაობები, შასის მიხედვით დაჯგუფებული. ფილტრები: year_from, year_to და body. საჯარო id ერთი შასის ჩანაწერებიდან უმცირესი id არის.', self::listSample('/api/v1/models/10/generations', ['id' => '100', 'model_id' => '10', 'slug' => 'xv70', 'name' => 'XV70', 'year_start' => 2017, 'year_end' => 2024, 'period_text' => '2017–2024', 'body_type' => 'sedan', 'chassis_code' => 'XV70', 'steering_wheel' => 'left', 'market' => null, 'display_label' => 'XV70 · 2017–2024', 'modified' => $modified], 3), $modelId),
            self::listEndpoint('listGenerationModifications', '/api/v1/generations/{id}/modifications', 'modifications', 'GenerationModification', 'Modifications for one public generation id', '/api/v1/generations/100/modifications?limit=1', 'Modifications for one public generation id. Filters: year_from, year_to, body, fuel, drive, and transmission.', 'ერთი საჯარო თაობის მოდიფიკაციები.', 'Modifications for one public generation id. Filters: year_from, year_to, body, fuel, drive, and transmission.', 'ერთი საჯარო თაობის მოდიფიკაციები. ფილტრები: year_from, year_to, body, fuel, drive და transmission.', self::listSample('/api/v1/generations/100/modifications', ['id' => '1000', 'generation_id' => '100', 'name' => '2.5', 'engine_volume_cc' => 2494, 'transmission' => 'automatic', 'year_start' => 2018, 'year_end' => 2024, 'modified' => $modified], 6), ['id' => 'Positive public generation id. Another id from the same chassis group returns 404.']),
            self::oneEndpoint('getModificationSpecs', '/api/v1/modifications/{id}/specs', 'ModificationSpecs', 'Engine, transmission, and fuel consumption for one modification. Unknown specification keys are left out.', '/api/v1/modifications/1000/specs', 'Engine, transmission, and fuel consumption (L/100 km) for one modification. Unknown values are null. dimensions and performance are always null. Unknown specification keys are left out.', 'ერთი მოდიფიკაციის სპეციფიკაცია.', 'Engine, transmission, and fuel consumption (L/100 km) for one modification. Unknown values are null. dimensions and performance are always null.', 'ერთი მოდიფიკაციის ძრავა, ტრანსმისია და საწვავის ხარჯი (ლიტრი 100 კმ-ზე). უცნობი მნიშვნელობა null-ია. dimensions და performance ყოველთვის null-ია.', ['data' => ['modification_id' => '1000', 'engine' => ['volume_cc' => 2494, 'fuel' => 'petrol', 'power_hp' => 200], 'transmission' => ['type' => 'automatic', 'drive' => 'fwd'], 'dimensions' => null, 'performance' => null, 'consumption' => ['city_l' => 9.1, 'highway_l' => 5.9, 'combined_l' => 7.4, 'unit' => 'L/100km']]]),
            self::oneEndpoint('getModificationEquipment', '/api/v1/modifications/{id}/equipment', 'ModificationEquipment', 'Equipment grouped as standard, optional, not_available, and unknown.', '/api/v1/modifications/1000/equipment', 'Equipment for one modification in four groups: standard, optional, not_available, and unknown. Rows with the same category, name, and group are merged.', 'ერთი მოდიფიკაციის კომპლექტაცია.', 'Equipment for one modification in four groups: standard, optional, not_available, and unknown.', 'ერთი მოდიფიკაციის კომპლექტაცია ოთხ ჯგუფად: standard (სტანდარტული), optional (დამატებითი), not_available (მიუწვდომელი) და unknown (უცნობი). ერთნაირი კატეგორიის, სახელისა და ჯგუფის ჩანაწერები ერთიანდება.', ['data' => ['modification_id' => '1000', 'standard' => [['id' => '1', 'category' => 'Safety', 'name' => 'ABS', 'value' => null]], 'optional' => [['id' => '2', 'category' => 'Comfort', 'name' => 'Sunroof', 'value' => null]], 'not_available' => [], 'unknown' => []]]),
            self::oneEndpoint('getModification', '/api/v1/modifications/{id}', 'Modification', 'One modification', '/api/v1/modifications/1000', 'One modification with its brand, model, and generation. modified is the time of the last change, in UTC.', 'ერთი მოდიფიკაცია.', 'One modification with its brand, model, and generation. modified is the time of the last change, in UTC.', 'ერთი მოდიფიკაცია ბრენდით, მოდელითა და თაობით. modified ბოლო ცვლილების დროა (UTC).', ['data' => ['id' => '1000', 'name' => '2.5', 'engine_volume_cc' => 2494, 'transmission' => 'automatic', 'drive_type' => 'fwd', 'body_type' => 'sedan', 'year_start' => 2018, 'year_end' => 2024, 'brand' => ['id' => '1', 'slug' => 'toyota', 'name' => 'Toyota'], 'model' => ['id' => '10', 'slug' => 'camry', 'name' => 'Camry'], 'generation' => ['id' => '100', 'name' => 'XV70'], 'modified' => $modified]]),
            self::listEndpoint('listModifications', '/api/v1/modifications', 'modification_search', 'ModificationSearch', 'Modifications across brands, models, and generations', '/api/v1/modifications?limit=1', 'Search modifications across brands, models, and generations. Filters: year_from, year_to, body, fuel, drive, transmission, brand_id, model_id, and q.', 'მოდიფიკაციების ძებნა.', 'Search modifications across brands, models, and generations. Filters: year_from, year_to, body, fuel, drive, transmission, brand_id, model_id, and q.', 'მოდიფიკაციების ძებნა ყველა ბრენდში, მოდელსა და თაობაში. ფილტრები: year_from, year_to, body, fuel, drive, transmission, brand_id, model_id და q.', self::listSample('/api/v1/modifications', ['id' => '1000', 'name' => '2.5', 'brand_id' => '1', 'model_id' => '10', 'generation_id' => '100', 'engine_volume_cc' => 2494, 'transmission' => 'automatic', 'drive_type' => 'fwd', 'body_type' => 'sedan', 'fuel' => 'petrol', 'year_start' => 2018, 'year_end' => 2024, 'modified' => $modified], 5000)),
            self::listEndpoint('listEngines', '/api/v1/engines', 'engines', 'Engine', 'Engine volume, fuel, and power of each modification', '/api/v1/engines?limit=1', 'Engine volume, fuel, and power of each modification. Filter with fuel, brand_id, model_id, volume_from, and volume_to.', 'ძრავები.', 'Engine volume, fuel, and power of each modification. Filters: fuel, brand_id, model_id, volume_from, and volume_to.', 'თითოეული მოდიფიკაციის ძრავის მოცულობა, საწვავი და სიმძლავრე. ფილტრები: fuel, brand_id, model_id, volume_from და volume_to.', self::listSample('/api/v1/engines', ['id' => '1000', 'volume_cc' => 2494, 'fuel' => 'petrol', 'power_hp' => 200, 'brand_id' => '1', 'model_id' => '10', 'modified' => $modified], 5000)),
            self::listEndpoint('listBodies', '/api/v1/bodies', 'bodies', 'Body', 'Distinct body types used by models, generations, and modifications', '/api/v1/bodies?limit=1', 'Distinct body types and how many records use each. q searches the name. modified_after returns 422.', 'ძარის ტიპები.', 'Distinct body types and how many records use each. q searches the name. modified_after is not accepted.', 'ძარის ყველა ტიპი და მისი ჩანაწერების რაოდენობა. q სახელში ეძებს. modified_after არ მიიღება.', self::listSample('/api/v1/bodies', ['id' => 'sedan', 'name' => 'sedan', 'count' => 1520, 'modified' => null], 12)),
            self::listEndpoint('listFuel', '/api/v1/fuel', 'fuel', 'Fuel', 'Fuel consumption of each modification', '/api/v1/fuel?limit=1', 'City, highway, and combined fuel consumption of each modification, in litres per 100 km. The modification search filters are accepted, including body, drive, and transmission.', 'საწვავის ხარჯი.', 'City, highway, and combined fuel consumption of each modification, in litres per 100 km. The modification search filters are accepted, including body, drive, and transmission.', 'თითოეული მოდიფიკაციის ქალაქის, ტრასისა და კომბინირებული ხარჯი, ლიტრი 100 კმ-ზე. მიიღება მოდიფიკაციების ძებნის ფილტრები, მათ შორის body, drive და transmission.', self::listSample('/api/v1/fuel', ['id' => '1000', 'fuel' => 'petrol', 'city_l' => 9.1, 'highway_l' => 5.9, 'combined_l' => 7.4, 'brand_id' => '1', 'model_id' => '10', 'modified' => $modified], 5000)),
            self::listEndpoint('listColors', '/api/v1/colors', 'colors', 'Color', 'Colors of model photos', '/api/v1/colors?limit=2', 'Every color stored for a model photo. id is the model id, so it is not unique. verified is false when the photo was not checked, and those rows stay in the list. The image is not included.', 'ფერები.', 'Every color stored for a model photo. id is the model id, so several colors of one model share it. verified is false when the photo was not checked, and those rows stay in the list. The image is not included.', 'მოდელის ფოტოზე შენახული ყველა ფერი. id მოდელის id-ია, ამიტომ ერთი მოდელის რამდენიმე ფერს ერთი id აქვს. verified არის false, თუ ფოტო შემოწმებული არ არის, და ასეთი ჩანაწერი სიაში რჩება. სურათი პასუხში არ არის.', [
                'data' => [
                    ['id' => '10', 'model_id' => '10', 'brand_id' => '1', 'name' => 'white', 'verified' => true, 'modified' => $modified],
                    ['id' => '10', 'model_id' => '10', 'brand_id' => '1', 'name' => 'red', 'verified' => false, 'modified' => $modified],
                ],
                'meta' => ['page' => 1, 'per_page' => 2, 'total' => 2400],
                'collection' => ['count' => 2, 'total' => 2400, 'pages' => 1200, 'next' => '/api/v1/colors?page=2&limit=2', 'prev' => null, 'first' => '/api/v1/colors?page=1&limit=2', 'last' => '/api/v1/colors?page=1200&limit=2'],
            ]),
            [
                'operation_id' => 'getCoverage',
                'path' => '/api/v1/coverage',
                'kind' => 'coverage',
                'mode' => 'coverage',
                'item' => 'Coverage',
                'summary' => 'Catalog counts.',
                'example' => '/api/v1/coverage',
                'purpose' => 'Catalog counts measured when the request runs. They are not your quota.',
                'title_en' => 'Coverage',
                'title_ka' => 'დაფარვა',
                'guide_en' => 'Catalog counts measured when the request runs. Filters are not accepted.',
                'guide_ka' => 'კატალოგის რაოდენობები მოთხოვნის მომენტისთვის. ფილტრები არ მიიღება.',
                'sample' => ['brands_count' => 120, 'models_count' => 1800, 'generations_count' => 4200, 'models_with_generations_count' => 1650, 'models_without_generations_count' => 150, 'modifications_count' => 52000, 'photos_count' => 900, 'generations_with_photo_urls_count' => 0, 'measured_at' => $modified],
                'path_params' => [],
            ],
        ];
    }

    /**
     * An example list envelope with one row on page 1.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function listSample(string $path, array $row, int $total): array
    {
        return [
            'data' => [$row],
            'meta' => ['page' => 1, 'per_page' => 1, 'total' => $total],
            'collection' => [
                'count' => 1,
                'total' => $total,
                'pages' => $total,
                'next' => $total > 1 ? $path.'?page=2&limit=1' : null,
                'prev' => null,
                'first' => $path.'?page=1&limit=1',
                'last' => $path.'?page='.$total.'&limit=1',
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
            'path_params' => ['id' => 'Positive modification id. Zero or a non-numeric id returns 422. An unknown id returns 404.'],
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
