<?php
/**
 * GeoAPI — vehicle catalog front controller.
 * @author Anovo.Ge https://anovo.ge
 *
 * Like imagin.studio's /getImage, but returns REAL photographs from Wikimedia
 * Commons instead of 3D renders. Attribution + licence travel in headers and JSON.
 *
 *   GET /getImage?make=Toyota&model=Camry[&year=2015]     -> the image (or 302 to it)
 *   GET /getImage?make=Toyota&model=Camry&format=json     -> JSON metadata
 *
 * Also accepts imagin.studio's param names (modelFamily, modelYear, angle,
 * customer, …) so it's a drop-in replacement.
 *
 * Run it with the PHP built-in server (this file doubles as its router script):
 *   php -S localhost:8080 -t public public/index.php
 */

declare(strict_types=1);

use Carapi\Cache\FilesystemCache;
use Carapi\Http\FileRateLimiter;
use Carapi\Service;

// Built-in server: serve existing static files (cached images exposed manually, etc.).
if (PHP_SAPI === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if ($path !== '/' && is_file(__DIR__ . $path)) {
        return false;
    }
}

// Composer autoload when present, tiny PSR-4 fallback otherwise (no deps needed).
$composer = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'Carapi\\')) {
            $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
            if (is_file($file)) require $file;
        }
    });
}

function carapi_handle_request(): void
{
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$apiPath = rtrim($uriPath, '/') ?: '/';
if (str_starts_with(strtolower($apiPath), '/widget/v1/') && !str_ends_with(strtolower($apiPath), '.js')) {
    \Carapi\Widget\Http::dispatch($apiPath);
    return;
}

if (str_starts_with(strtolower($apiPath), '/api/v1')) {
    \Carapi\Catalog\Http::dispatch($apiPath);
    return;
}

if (\Carapi\Catalog\VehiclePickerHttp::matches($apiPath)) {
    \Carapi\Catalog\VehiclePickerHttp::dispatch($apiPath);
    return;
}

$path = strtolower(rtrim($uriPath, '/'));

if (\Carapi\Platform\Http::enabled() && \Carapi\Platform\Http::handles($path)) {
    \Carapi\Platform\Http::dispatch($path);
    return;
}

if ($path === '/guide') {
    carapi_guide();
    return;
}

if ($path === '/privacy') {
    carapi_privacy();
    return;
}

if ($path === '/terms') {
    carapi_terms();
    return;
}

if (!\Carapi\Platform\Http::enabled() && in_array($path, ['/login', '/register', '/logout', '/account', '/auth/me'], true)) {
    carapi_closed();
    return;
}

if ($path === '/getimage') {
    carapi_get_image();
} elseif (\Carapi\Auth\Router::dispatch($path)) {
    return;
} elseif ($path === '' || $path === '/docs') {
    carapi_landing();
} else {
    http_response_code(404);
    echo \Carapi\SiteChrome::start('გვერდი ვერ მოიძებნა');
    echo \Carapi\SiteChrome::header();
    echo '<main id="content" class="wrap missing"><h1>გვერდი ვერ მოიძებნა</h1><p class="muted">მითითებული მისამართით გვერდი ვერ მოიძებნა.</p><a class="btn btn-l" href="/">მთავარზე დაბრუნება</a></main>';
    echo \Carapi\SiteChrome::footer();
    echo \Carapi\SiteChrome::end();
}
}

function carapi_get_image(): void
{
    header('Vary: Accept-Encoding');
    header('X-Robots-Tag: noindex, noimageindex');
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Resource-Policy: same-site');
    header('Referrer-Policy: no-referrer');

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
        http_response_code(204);
        return;
    }

    $make  = trim((string)($_GET['make'] ?? ''));
    $model = trim((string)($_GET['model'] ?? $_GET['modelFamily'] ?? ''));
    $year  = isset($_GET['year']) ? (int)$_GET['year']
           : (isset($_GET['modelYear']) ? (int)$_GET['modelYear'] : null);
    $json  = ($_GET['format'] ?? '') === 'json';
    // imagin.studio compatibility: angle, customer, zoomType, paintDescription, …
    // are accepted but ignored — real photographs come with a fixed viewpoint.

    $limitDir = carapi_env('CARAPI_GETIMAGE_LIMIT_DIR');
    if (!is_string($limitDir) || $limitDir === '') {
        $limitDir = dirname(__DIR__) . '/data/getimage-limit';
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP) === false) {
        $ip = '0.0.0.0';
    }
    $decision = (new FileRateLimiter($limitDir, 30))->hit($ip, time());
    if (!$decision['allowed']) {
        http_response_code(429);
        header('Retry-After: ' . (string) $decision['retry_after']);
        header('Cache-Control: no-store');
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'rate limit exceeded']);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'rate limit exceeded';
        }
        return;
    }

    if ($make === '' || $model === '') {
        http_response_code(400);
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'query params "make" and "model" are required']);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'query params "make" and "model" are required';
        }
        return;
    }

    $res = null;
    $cache = null;
    try {
        $cache = new FilesystemCache(
            carapi_env('CARAPI_CACHE_DIR') ?: dirname(__DIR__) . '/cache',
            carapi_env('CARAPI_CACHE_URL') ?: null
        );
        $res = (new Service($cache))->get($make, $model, $year);
    } catch (\Throwable $e) {
        error_log('[geoapi] getImage failed: ' . $e->getMessage());
    }

    if ($res) {
        header('X-Image-Source: ' . $res['source']);
        header('X-Image-License: ' . $res['license']);
        header('X-Image-Attribution: ' . carapi_header_safe($res['attribution']));
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: public, max-age=86400');
            $self = carapi_self_base() . '/getImage?' . http_build_query(array_filter([
                'make' => $make, 'model' => $model, 'year' => $year,
            ]));
            echo json_encode([
                'found' => true,
                'make'  => $make, 'model' => $model, 'year' => $year,
                'image_url'   => $res['image_url'] ?? $self,
                'source'      => $res['source'],
                'title'       => $res['title'],
                'license'     => $res['license'],
                'author'      => $res['author'],
                'attribution' => $res['attribution'],
                'period'      => $res['period'] ?? '',
                'generation'  => $res['generation'] ?? '',
                'body'        => $res['body'] ?? '',
                'frames'      => $res['frames'] ?? '',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }
        header('Cache-Control: public, max-age=86400');
        if (!empty($res['image_url'])) {
            // CDN-backed cache -> redirect, like the hosted service does.
            header('Location: ' . $res['image_url'], true, 302);
            return;
        }
        $bytes = $cache?->readImage($res['file']);
        if ($bytes !== null) {
            $marked = \Carapi\Catalog\DemoWatermark::apply($bytes);
            if ($marked !== null) {
                header('Content-Type: ' . $marked['mime']);
                header('Content-Length: ' . strlen($marked['bytes']));
                header('Cache-Control: private, no-store');
                echo $marked['bytes'];
                return;
            }
            // Fail closed like /api/models/{id}/photo: never send a photo without the watermark.
            error_log('[geoapi] getImage watermark unavailable');
            http_response_code(503);
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: no-store');
            echo 'image temporarily unavailable';
            return;
        }
        // Cache blob vanished between lookup and read — fall through to "not found".
    }

    // No real photo found.
    if ($json) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['found' => false, 'make' => $make, 'model' => $model, 'year' => $year]);
        return;
    }
    // <img> tags render the body regardless of status, so send a neutral inline
    // placeholder with a correct 404 for API callers. No external hosts involved.
    http_response_code(404);
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    echo <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="520" viewBox="0 0 800 520">
  <rect width="800" height="520" fill="#eef1f5"/>
  <g fill="none" stroke="#b6bfca" stroke-width="14" stroke-linecap="round" stroke-linejoin="round">
    <path d="M170 330h460l-40-90a40 40 0 0 0-36-24H252a40 40 0 0 0-36 24z"/>
    <path d="M150 330h500v60h-40M190 390h-40z"/>
    <circle cx="260" cy="390" r="34"/>
    <circle cx="540" cy="390" r="34"/>
  </g>
  <text x="400" y="470" text-anchor="middle" font-family="system-ui,sans-serif" font-size="28" fill="#8a94a1">no photo found</text>
</svg>
SVG;
}

/** Scheme + host of the current request, for self-referencing JSON URLs. */
function carapi_self_base(): string
{
    $proto = $_SERVER['HTTP_X_FORWARDED_PROTO']
        ?? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $proto . '://' . $host;
}

/** HTTP header values must be a single line; attribution can contain anything. */
function carapi_header_safe(string $v): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $v));
}

/** @return array{status:string,brands:string,models:string,generations:string,note:string} */
function carapi_counts_view(): array
{
    $counts = \Carapi\Catalog\PublicCounts::read();
    $fmt = static fn (int $n): string => number_format($n, 0, '.', "\u{00A0}");
    if ($counts['status'] === 'hidden') {
        return ['status' => 'hidden', 'brands' => '—', 'models' => '—', 'generations' => '—', 'note' => ''];
    }
    if ($counts['status'] !== 'ok') {
        return [
            'status' => 'bad',
            'brands' => '—',
            'models' => '—',
            'generations' => '—',
            'note' => '<p id="counts" class="hero-note">კატალოგი დროებით მიუწვდომელია.</p>',
        ];
    }

    return [
        'status' => 'ok',
        'brands' => $fmt((int) $counts['brands']),
        'models' => $fmt((int) $counts['models']),
        'generations' => $fmt((int) $counts['generations']),
        'note' => '',
    ];
}

function carapi_demo_key(): string
{
    if (!\Carapi\Platform\Http::enabled() || !\Carapi\Catalog\Http::enabled() || !\Carapi\Widget\Http::enabled()) {
        return '';
    }
    try {
        $stmt = \Carapi\Platform\Connection::pdo()->query(
            "SELECT public_key FROM platform.widgets WHERE enabled = true AND allowed_origins @> '[\"https://geoapi.ge\"]'::jsonb LIMIT 1"
        );
        $key = $stmt ? $stmt->fetchColumn() : false;

        return is_string($key) ? $key : '';
    } catch (\Throwable) {
        return '';
    }
}

function carapi_closed(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    echo '<!doctype html><html lang="ka"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>მალე გაიხსნება — GeoAPI</title></head><body>';
    echo '<p id="closed">საჯარო გაშვება ჯერ არ დაწყებულა. რეგისტრაცია ჯერ არ არის გახსნილი.</p><p><a href="/">მთავარი</a> · <a href="/guide">დოკუმენტაცია</a></p>';
    echo '</body></html>';
}

function carapi_terms(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    echo \Carapi\SiteChrome::start('გამოყენების წესები');
    echo \Carapi\SiteChrome::header();
    echo '<main id="content" class="wrap legal">';
    echo <<<'HTML'
<section class="panel">
<p>ვერსია 2026-09-26. ეს წესები არ არის ფასების, გადახდის ან უწყვეტი მუშაობის გარანტია.</p>
<h1>გამოყენების წესები</h1>
<p>მომსახურებას გაწვდით შპს „ინოვა ლაბ“, საიდენტიფიკაციო კოდი 432291851. კონტაქტი: <a href="mailto:hello@geoapi.ge">hello@geoapi.ge</a>.</p>
</section>
<section class="panel"><h2>რას იღებთ</h2>
<p>სერვერული API გაძლევთ ბრენდების, მოდელებისა და თაობების სიებს, რათა საკუთარი ინტერფეისი ააწყოთ. ვიჯეტი იგივე არჩევანს აჩვენებს თქვენს საიტზე და არჩეულ იდენტიფიკატორებს თქვენს გვერდს გადასცემს. ვიჯეტი თქვენი საიტის განცხადებებს თავისით არ ფილტრავს. მიმდინარე სერვერულ API-ს ფოტო არ აქვს. მოდიფიკაციების სია და დეტალი არის. ტექნიკური მახასიათებლების ცალკე მოთხოვნა არ არის.</p>
<p>კატალოგი შეიძლება იყოს არასრული ან უზუსტო. ყველა მოდელს არ აქვს თაობა. კატალოგის მონაცემზე ექსკლუზიურ საავტორო უფლებას არ გაძლევთ.</p>
</section>
<section class="panel"><h2>ანგარიში და გასაღებები</h2>
<p>ანგარიშის პაროლსა და საიდუმლო API გასაღებს თქვენ იცავთ. საიდუმლო API გასაღები ბრაუზერში არ უნდა ჩანდეს. საჯარო API გასაღები საიტის კოდში ჩანს და საიდუმლო არ არის. სხვის ანგარიშზე შესვლა, ლიმიტის გვერდის ავლა და სისტემის განზრახ დაზიანება აკრძალულია.</p>
<p>ლიმიტი თქვენს გეგმაზეა დამოკიდებული: წუთში მოთხოვნების რაოდენობა და თვიური წარმატებული მოთხოვნების კვოტა, რომელსაც სამუშაო სივრცის პროექტები იზიარებენ. დეტალები — გვერდზე „ტარიფები“. ეს კომერციული ტარიფი არ არის. რიცხვს ხედავთ პროექტის გვერდზე, სექციაში „მოხმარების სტატისტიკა“.</p>
<p>თქვენი საიტის განცხადებები, სტუმრები და იქ შენახული მონაცემები თქვენი პასუხისმგებლობაა.</p>
</section>
<section class="panel"><h2>შეზღუდვა და განახლება</h2>
<p>შეზღუდვა ან შეჩერება შეიძლება, თუ ანგარიში შეჩერებულია, ელფოსტა დაუდასტურებელია, გასაღები გაუქმებულია, ლიმიტი ამოიწურა, ან მოთხოვნა სისტემას ზიანს აყენებს. ეს პასუხისმგებლობის სრულ მოხსნას არ ნიშნავს.</p>
<p>წესების მოქმედი ტექსტი ამ გვერდზეა. მხარდაჭერა: hello@geoapi.ge. პერსონალურ მონაცემებზე იხილეთ <a href="/privacy">კონფიდენციალურობის პოლიტიკა</a>.</p>
</section>
HTML;
    echo '</main>';
    echo \Carapi\SiteChrome::footer();
    echo \Carapi\SiteChrome::end();
}

function carapi_privacy(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    echo \Carapi\SiteChrome::start('კონფიდენციალურობის პოლიტიკა');
    echo \Carapi\SiteChrome::header();
    echo '<main id="content" class="wrap legal">';
    echo <<<'HTML'
<section class="panel">
<p class="note" id="updated">განახლდა 26 სექტემბერი, 2026. ეს ტექსტი არ ნიშნავს, რომ ყველა პროცესი სრულად შესაბამისია კანონთან.</p>
<h1>კონფიდენციალურობის პოლიტიკა</h1>
<p>ოპერატორი: შპს „ინოვა ლაბ“, საიდენტიფიკაციო კოდი 432291851. კონტაქტი: <a href="mailto:hello@geoapi.ge">hello@geoapi.ge</a>. საიტი: https://geoapi.ge.</p>
</section>
<section class="panel" id="scope"><h2>ვის ეხება</h2>
<p>ტექსტი ეხება საიტის სტუმრებს, ანგარიშის მფლობელებს, მათ წარმომადგენლებს, საკონტაქტო ელფოსტაზე მომწერ პირებს და სხვა საიტზე ჩასმული ვიჯეტის სტუმრებს.</p>
<p>ეს არ არის გამოყენების წესები, კატალოგის ლიცენზია ან ფასიან მომსახურებაზე შეთანხმება. გვერდის გაცნობა არ არის თანხმობა მონაცემების ნებისმიერი მიზნით დამუშავებაზე ან მარკეტინგზე.</p>
<p>თუ ვიჯეტი სხვა კომპანიის საიტზეა, იმ საიტის ანგარიშებსა და განცხადებებს იმ საიტის ოპერატორი განსაზღვრავს. GeoAPI მხოლოდ ვიჯეტის კატალოგის მოთხოვნას იღებს.</p>
</section>
<section class="panel" id="data"><h2>რა მონაცემები მუშავდება</h2>
<p>რეგისტრაცია ითხოვს სახელს, გვარს, ელფოსტას და პაროლს. პირადი ნომერი, ტელეფონი, VIN, სანომრე ნიშანი და საბანკო ბარათი არ იკითხება. პაროლი ინახება მხოლოდ ჰეშით. ბაზაში ინახება ანგარიშის სტატუსი, ელფოსტის დადასტურების დრო, შექმნის დრო და ბოლო შესვლის დრო.</p>
<p>წარუმატებელი შესვლისას ინახება ელფოსტა და IP. სესიაში ინახება მხოლოდ მომხმარებლის იდენტიფიკატორი, ავტორიზაციის ვერსია და სესიის დროები. სრული API გასაღები ბაზაში არ ინახება: საიდუმლო ნაწილი ჰეშია, სრული გასაღები ერთხელ ჩანს. ვიჯეტის საჯარო გასაღები საიდუმლო არ არის.</p>
<p>პროექტზე ინახება სახელი, მითითებული საიტი, სტატუსი და დაშვებული origin-ები. მოხმარება ინახება რაოდენობებად, დღისა და მარშრუტის მიხედვით, არა მოთხოვნის სრული შინაარსით.</p>
<p>ელფოსტის დადასტურებისა და პაროლის აღდგენის ბმული ბაზაში მხოლოდ ჰეშითაა. წერილშია მიმღების მისამართი და ბმული.</p>
</section>
<section class="panel" id="purposes"><h2>რისთვის და რა საფუძვლით ვამუშავებთ მონაცემებს</h2>
<p>ყველა დამუშავება თანხმობაზე არ დგას. ხელშეკრულების დადებას ან კანონით დაკისრებულ ვალდებულებას აქ არ ვაცხადებთ, თუ ასეთი საფუძველი ცალკე არ წერია.</p>
<table>
<tr><th>მიზანი</th><th>საფუძველი</th></tr>
<tr><td>ანგარიშის შექმნა, შესვლა, პროექტებისა და გასაღებების მართვა</td><td>მომსახურება, რომელსაც თავად ითხოვთ</td></tr>
<tr><td>ელფოსტის დადასტურება და პაროლის აღდგენა</td><td>ანგარიშზე წვდომა და მისი უსაფრთხოება. ეს იმავე მოთხოვნილი მომსახურების ნაწილია</td></tr>
<tr><td>მოხმარების აღრიცხვა და კვოტა</td><td>მოთხოვნილი მომსახურების მიწოდება და ლიმიტის დაცვა</td></tr>
<tr><td>IP, წარუმატებელი შესვლა და ტექნიკური ჟურნალი</td><td>უსაფრთხოება და ბოროტად გამოყენების თავიდან აცილება. ეს ჩვენი და სხვა მომხმარებლების ლეგიტიმური ინტერესია, არა თანხმობა</td></tr>
<tr><td>მხარდაჭერის მიმოწერა</td><td>თქვენს მოთხოვნაზე პასუხი და საკითხის მოგვარება</td></tr>
</table>
<p>რეგისტრაციას სჭირდება სახელი, გვარი, ელფოსტა და პაროლი. რომელიმე რომ არ იყოს, ანგარიში არ იქმნება. ამისთვის სხვა მონაცემს არ ვითხოვთ.</p>
</section>
<section class="panel" id="widget"><h2>ვიჯეტი</h2>
<p>სტუმრის ბრაუზერი GeoAPI-ს უგზავნის საჯარო გასაღებს, გვერდის origin-ს და სიის პარამეტრებს, მაგალითად გვერდის ნომერს ან მოდელის იდენტიფიკატორს. მოთხოვნა მიდის <code>credentials: omit</code> რეჟიმით და GeoAPI-ის სესიის cookie-ს არ აგზავნის. IP გამოიყენება ლიმიტისთვის. არჩეული იდენტიფიკატორები და სახელები მოვლენით რჩება მასპინძელ გვერდზე. GeoAPI ამ არჩევანის მუდმივ პროფილს არ ქმნის.</p>
<p>ვიჯეტის სკრიპტი ბრაუზერის მეხსიერებაში არჩევანს არ ინახავს და გარე შრიფტს არ ტვირთავს.</p>
</section>
<section class="panel" id="cookies"><h2>Cookie-ები და შრიფტი</h2>
<p>ანგარიშის cookie-ს სახელია geoapi_platform. იგი მხოლოდ საიტისთვისაა წასაკითხი, HTTPS-ზე დაცული კავშირით იგზავნება და ბრაუზერის დახურვამდე რჩება. სესია წყდება 30 წუთის უმოქმედობისას ან 12 საათში. გამოსვლა ამ cookie-ს შლის. ეს იმავე წამს ყველა ჩანაწერის წაშლას არ ნიშნავს.</p>
<p>ღია და მუქი რეჟიმის არჩევანი ინახება მხოლოდ თქვენს ბრაუზერში და სერვერზე არ იგზავნება. ანალიტიკის სკრიპტი არ არის.</p>
<p>შრიფტი საიტის საკუთარი ფაილია. გარე შრიფტის სერვისს აღარ ვიყენებთ.</p>
</section>
<section class="panel" id="recipients"><h2>ვინ ამუშავებს მონაცემს</h2>
<p>GeoAPI-ის სერვერი განთავსებულია გერმანიაში. ჰოსტინგის პროვაიდერია Contabo. ეს მხოლოდ ამ სერვერს ეხება. სხვა მომსახურებისა და მიმღების მდებარეობა ამით არ დასტურდება. სერვერის მდებარეობა არც ხელშეკრულებას და არც მონაცემთა საერთაშორისო გადაცემის სამართლებრივ საფუძველს ადასტურებს.</p>
<p>ანგარიში და კატალოგის აღრიცხვა ამ სერვერზე ინახება. სერვისულ წერილს აგზავნის ამავე სერვერის ფოსტა, მისამართიდან hello@geoapi.ge. შემდეგ წერილი მიდის იმ საფოსტო სისტემაში, რომელსაც მიმღების მისამართი იყენებს.</p>
<p>მონაცემებს არ ვყიდით და მესამე პირის დამოუკიდებელ რეკლამას არ გადავცემთ. კანონით გათვალისწინებულ შემთხვევაში მონაცემი შეიძლება მოითხოვოს უფლებამოსილმა ორგანომ.</p>
</section>
<section class="panel" id="retention"><h2>რამდენ ხანს ინახება</h2>
<p>წაშლის ღილაკი არ არის. მოთხოვნა ელფოსტით იგზავნება.</p>
<table>
<tr><th>რა</th><th>რამდენ ხანს</th></tr>
<tr><td>ანგარიში, პროექტი, მოხმარება, გაუქმებული გასაღები</td><td>ანგარიშის არსებობისას. წაშლის მოთხოვნა და კანონით დაშვებული გამონაკლისი ამას ცვლის. გათიშვა და გასაღების გაუქმება წაშლა არ არის.</td></tr>
<tr><td>დადასტურების ბმული</td><td>ბმული 24 საათი მოქმედებს. ეს ჩანაწერის წაშლის ვადა არ არის. ვადის ამოწურვისას ჩანაწერი თავისით არ იშლება.</td></tr>
<tr><td>პაროლის აღდგენის ბმული</td><td>ბმული 30 წუთი მოქმედებს. ჩანაწერი ამ წუთს არ იშლება.</td></tr>
<tr><td>წარუმატებელი შესვლა</td><td>ერთ დღეზე ძველი ჩანაწერი იშლება მაშინ, როცა ახალი წარუმატებელი შესვლა ინახება.</td></tr>
<tr><td>ლიმიტის მოკლე ფანჯარა</td><td>ყოველდღე იწმინდება. ეს არ არის გარანტია, რომ ჩანაწერი ზუსტად ორ წუთში გაქრება.</td></tr>
<tr><td>სესია</td><td>წყდება 30 წუთის უმოქმედობისას ან 12 საათში. გამოსვლა cookie-ს შლის. ფაილის იმავე წამს გაქრობას არ ვპირდებით.</td></tr>
<tr><td>საიტის ჟურნალი</td><td>აქტიურ ფაილშია IP, დრო, მეთოდი, მისამართი პარამეტრების გარეშე, სტატუსი, ზომა და ხანგრძლივობა. პაროლი, ავტორიზაციის სათაური, cookie და მოთხოვნის ტექსტი იქ არ იწერება. აქტიური ფაილი კვირაში ერთხელ იხურება. დახურული ასლი 8 ციკლს რჩება.</td></tr>
<tr><td>საფოსტო ჟურნალი</td><td>აქტიური ფაილი კვირაში ერთხელ იხურება. დახურული ასლი 4 ციკლს რჩება. ასლში შეიძლება იყოს მიმღების მისამართი.</td></tr>
<tr><td>მხარდაჭერის წერილი</td><td>საკითხის დასრულებიდან 12 თვე. ავტომატური წაშლა არ ხდება.</td></tr>
<tr><td>სარეზერვო ასლი</td><td>ანგარიშის მონაცემების ავტომატური სარეზერვო ასლების შექმნა ამ ეტაპზე გამართული არ არის.</td></tr>
</table>
</section>
<section class="panel" id="rights"><h2>თქვენი უფლებები</h2>
<p>ამჟამად მონაცემთა დაცვის ოფიცერი დანიშნული არ არის. პერსონალურ მონაცემებთან დაკავშირებულ საკითხებზე დაგვიკავშირდით: hello@geoapi.ge.</p>
<p>შეგიძლიათ მოითხოვოთ, რა მონაცემი გვაქვს და მიიღოთ ასლი. შეგიძლიათ მოითხოვოთ გასწორება და განახლება. შეგიძლიათ მოითხოვოთ დამუშავების შეწყვეტა ან წაშლა. დაბლოკვა და სხვა პირთან გადატანა შეგიძლიათ მაშინ, როცა კანონი ამას ითვალისწინებს. თუ კონკრეტული დამუშავება თქვენს თანხმობას ეფუძნება, თანხმობის გამოხმობა ნებისმიერ დროს შეგიძლიათ. ახლა მარკეტინგის თანხმობას არ ვიყენებთ.</p>
<p>წაშლის ან დამუშავების შეწყვეტის მოთხოვნას ვასრულებთ მოთხოვნიდან არაუგვიანეს 10 სამუშაო დღისა, ან იმავე ვადაში გატყობინებთ დასაბუთებულ უარს და გასაჩივრების წესს, თუ კანონით სხვა რამ არ არის დადგენილი. ეს ვადა ყველა უფლებას ერთნაირად და უპირობოდ არ ეხება. ინფორმაციისა და ასლის ვადა ასევე 10 სამუშაო დღეა და განსაკუთრებულ შემთხვევაში კიდევ 10 სამუშაო დღით შეიძლება გაგრძელდეს, რის შესახებაც დაუყოვნებლივ გატყობინებთ. დაბლოკვის პასუხის ვადა სხვაა: გადაწყვეტილებისთანავე, მაგრამ არაუგვიანეს 3 სამუშაო დღისა.</p>
<p>მოთხოვნა იგზავნება hello@geoapi.ge-ზე. შეუძლია მოგვწეროს ანგარიშის მფლობელმა, საიტის ან ვიჯეტის სტუმარმა, პირმა, რომელსაც ელფოსტაზე წვდომა დაკარგა, და სათანადოდ უფლებამოსილმა წარმომადგენელმა. ანგარიშის ელფოსტიდან წერილი ერთადერთი გზა არ არის და მარტო ეს წერილი ყოველთვის საკმარისი მტკიცებულება არ არის. საჭიროებისას ვამოწმებთ დამატებით, მოთხოვნის რისკის მიხედვით. პაროლს, სრულ API გასაღებს ან პირადობის დოკუმენტს ყველა შემთხვევაში არ ვითხოვთ.</p>
<p>შეგიძლიათ მიმართოთ საქართველოს სახელმწიფო აუდიტის სამსახურს ან სასამართლოს. ჩვენთან წინასწარი წერილი ამის პირობა არ არის.</p>
</section>
<section class="panel"><h2>უსაფრთხოება და ლიმიტები</h2>
<p>კავშირი დაშიფრულია. პაროლი და საიდუმლო API გასაღების საიდუმლო ნაწილი ჰეშდება. მოთხოვნებს აქვს მოთხოვნების სიხშირის ლიმიტი და თვიური მოთხოვნების ლიმიტი. ვიჯეტი არ აფასებს კრედიტს, ჯანმრთელობას ან პირად თვისებებს.</p>
<p>კითხვებისთვის: hello@geoapi.ge.</p>
</section>
HTML;
    echo '</main>';
    echo \Carapi\SiteChrome::footer();
    echo \Carapi\SiteChrome::end();
}

function carapi_logo(string $name): string
{
    $path = match ($name) {
        'cursor' => 'M11.503.131 1.891 5.678a.84.84 0 0 0-.42.726v11.188c0 .3.162.575.42.724l9.609 5.55a1 1 0 0 0 .998 0l9.61-5.55a.84.84 0 0 0 .42-.724V6.404a.84.84 0 0 0-.42-.726L12.497.131a1.01 1.01 0 0 0-.996 0M2.657 6.338h18.55c.263 0 .43.287.297.515L12.23 22.918c-.062.107-.229.064-.229-.06V12.335a.59.59 0 0 0-.295-.51l-9.11-5.257c-.109-.063-.064-.23.061-.23',
        'claude' => 'm4.7144 15.9555 4.7174-2.6471.079-.2307-.079-.1275h-.2307l-.7893-.0486-2.6956-.0729-2.3375-.0971-2.2646-.1214-.5707-.1215-.5343-.7042.0546-.3522.4797-.3218.686.0608 1.5179.1032 2.2767.1578 1.6514.0972 2.4468.255h.3886l.0546-.1579-.1336-.0971-.1032-.0972L6.973 9.8356l-2.55-1.6879-1.3356-.9714-.7225-.4918-.3643-.4614-.1578-1.0078.6557-.7225.8803.0607.2246.0607.8925.686 1.9064 1.4754 2.4893 1.8336.3643.3035.1457-.1032.0182-.0728-.164-.2733-1.3539-2.4467-1.445-2.4893-.6435-1.032-.17-.6194c-.0607-.255-.1032-.4674-.1032-.7285L6.287.1335 6.6997 0l.9957.1336.419.3642.6192 1.4147 1.0018 2.2282 1.5543 3.0296.4553.8985.2429.8318.091.255h.1579v-.1457l.1275-1.706.2368-2.0947.2307-2.6957.0789-.7589.3764-.9107.7468-.4918.5828.2793.4797.686-.0668.4433-.2853 1.8517-.5586 2.9021-.3643 1.9429h.2125l.2429-.2429.9835-1.3053 1.6514-2.0643.7286-.8196.85-.9046.5464-.4311h1.0321l.759 1.1293-.34 1.1657-1.0625 1.3478-.8804 1.1414-1.2628 1.7-.7893 1.36.0729.1093.1882-.0183 2.8535-.607 1.5421-.2794 1.8396-.3157.8318.3886.091.3946-.3278.8075-1.967.4857-2.3072.4614-3.4364.8136-.0425.0304.0486.0607 1.5482.1457.6618.0364h1.621l3.0175.2247.7892.522.4736.6376-.079.4857-1.2142.6193-1.6393-.3886-3.825-.9107-1.3113-.3279h-.1822v.1093l1.0929 1.0686 2.0035 1.8092 2.5075 2.3314.1275.5768-.3218.4554-.34-.0486-2.2039-1.6575-.85-.7468-1.9246-1.621h-.1275v.17l.4432.6496 2.3436 3.5214.1214 1.0807-.17.3521-.6071.2125-.6679-.1214-1.3721-1.9246L14.38 17.959l-1.1414-1.9428-.1397.079-.674 7.2552-.3156.3703-.7286.2793-.6071-.4614-.3218-.7468.3218-1.4753.3886-1.9246.3157-1.53.2853-1.9004.17-.6314-.0121-.0425-.1397.0182-1.4328 1.9672-2.1796 2.9446-1.7243 1.8456-.4128.164-.7164-.3704.0667-.6618.4008-.5889 2.386-3.0357 1.4389-1.882.929-1.0868-.0062-.1579h-.0546l-6.3385 4.1164-1.1293.1457-.4857-.4554.0608-.7467.2307-.2429 1.9064-1.3114Z',
        default => 'M22.2819 9.8211a5.9847 5.9847 0 0 0-.5157-4.9108 6.0462 6.0462 0 0 0-6.5098-2.9A6.0651 6.0651 0 0 0 4.9807 4.1818a5.9847 5.9847 0 0 0-3.9977 2.9 6.0462 6.0462 0 0 0 .7427 7.0966 5.98 5.98 0 0 0 .511 4.9107 6.051 6.051 0 0 0 6.5146 2.9001A5.9847 5.9847 0 0 0 13.2599 24a6.0557 6.0557 0 0 0 5.7718-4.2058 5.9894 5.9894 0 0 0 3.9977-2.9001 6.0557 6.0557 0 0 0-.7475-7.0729zm-9.022 12.6081a4.4755 4.4755 0 0 1-2.8764-1.0408l.1419-.0804 4.7783-2.7582a.7948.7948 0 0 0 .3927-.6813v-6.7369l2.02 1.1686a.071.071 0 0 1 .038.052v5.5826a4.504 4.504 0 0 1-4.4945 4.4944zm-9.6607-4.1254a4.4708 4.4708 0 0 1-.5346-3.0137l.142.0852 4.783 2.7582a.7712.7712 0 0 0 .7806 0l5.8428-3.3685v2.3324a.0804.0804 0 0 1-.0332.0615L9.74 19.9502a4.4992 4.4992 0 0 1-6.1408-1.6464zM2.3408 7.8956a4.485 4.485 0 0 1 2.3655-1.9728V11.6a.7664.7664 0 0 0 .3879.6765l5.8144 3.3543-2.0201 1.1685a.0757.0757 0 0 1-.071 0l-4.8303-2.7865A4.504 4.504 0 0 1 2.3408 7.872zm16.5963 3.8558L13.1038 8.364 15.1192 7.2a.0757.0757 0 0 1 .071 0l4.8303 2.7913a4.4944 4.4944 0 0 1-.6765 8.1042v-5.6772a.79.79 0 0 0-.407-.667zm2.0107-3.0231l-.142-.0852-4.7735-2.7818a.7759.7759 0 0 0-.7854 0L9.409 9.2297V6.8974a.0662.0662 0 0 1 .0284-.0615l4.8303-2.7866a4.4992 4.4992 0 0 1 6.6802 4.66zM8.3065 12.863l-2.02-1.1638a.0804.0804 0 0 1-.038-.0567V6.0742a4.4992 4.4992 0 0 1 7.3757-3.4537l-.142.0805L8.704 5.459a.7948.7948 0 0 0-.3927.6813zm1.0976-2.3654l2.602-1.4998 2.6069 1.4998v2.9994l-2.5974 1.4997-2.6067-1.4997Z',
    };

    return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="' . $path . '"/></svg>';
}

function carapi_guide(): void
{
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-cache');
    $open = \Carapi\Platform\Http::enabled();
    $status = $open ? '' : '<p id="closed" class="err">საჯარო გაშვება ჯერ არ დაწყებულა. ქვემოთ მოცემული აღწერა საჯარო წვდომის გახსნამდე მხოლოდ ცნობისთვისაა.</p>';
    $base = carapi_self_base();
    $h = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $chev = \Carapi\SiteChrome::icon('chev');
    $copy = \Carapi\SiteChrome::icon('copy');
    $info = \Carapi\SiteChrome::icon('info');
    $search = \Carapi\SiteChrome::icon('search');
    $curl = "curl " . $base . "/api/v1/brands \\\n  -H \"Authorization: Bearer <SERVER_API_KEY>\"";
    $php = "\$response = file_get_contents(\n  \"" . $base . "/api/v1/brands\",\n  false,\n  stream_context_create([\n    \"http\" => [\"header\" => \"Authorization: Bearer <SERVER_API_KEY>\"]\n  ])\n);";
    $brandJson = <<<'JSON'
{
  "data": [
    {
      "id": "1",
      "slug": "toyota",
      "name": "Toyota",
      "synonyms": [],
      "is_lcv_or_commercial": false,
      "logo_url_light": null,
      "logo_url_dark": null
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 1 }
}
JSON;
    $modelJson = <<<'JSON'
{
  "data": [
    {
      "id": "20",
      "brand_id": "1",
      "slug": "camry",
      "name": "Camry",
      "markets": [],
      "has_generations": true,
      "generations_count": 1
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 1 }
}
JSON;
    $generationJson = <<<'JSON'
{
  "data": [
    {
      "id": "30",
      "model_id": "20",
      "slug": "xv70",
      "name": "XV70",
      "year_start": 2017,
      "year_end": 2024,
      "period_text": "2017–2024",
      "body_type": "sedan",
      "chassis_code": "XV70",
      "steering_wheel": null,
      "market": null,
      "display_label": "XV70 · 2017–2024"
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 1 }
}
JSON;
    $coverageJson = <<<'JSON'
{
  "brands_count": 0,
  "models_count": 0,
  "generations_count": 0,
  "models_with_generations_count": 0,
  "models_without_generations_count": 0,
  "modifications_count": 0,
  "photos_count": 0,
  "generations_with_photo_urls_count": 0,
  "measured_at": "2026-10-18T00:00:00Z"
}
JSON;
    $modificationsJson = <<<'JSON'
{
  "data": [
    {
      "id": "40",
      "generation_id": "30",
      "name": "2.5",
      "engine_volume_cc": 2500,
      "transmission": "automatic",
      "year_start": 2017,
      "year_end": 2024
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 1 }
}
JSON;
    $modificationJson = <<<'JSON'
{
  "data": {
    "id": "40",
    "name": "2.5",
    "engine_volume_cc": 2500,
    "transmission": "automatic",
    "drive_type": "fwd",
    "body_type": "sedan",
    "year_start": 2017,
    "year_end": 2024,
    "brand": { "id": "1", "slug": "toyota", "name": "Toyota" },
    "model": { "id": "20", "slug": "camry", "name": "Camry" },
    "generation": { "id": "30", "name": "XV70" }
  }
}
JSON;
    $eventJs = <<<'JS'
document.querySelector("geoapi-filter").addEventListener("geoapi:change", function (event) {
  var detail = event.detail;
  console.log(detail.brand_id, detail.model_id, detail.year, detail.body_type, detail.generation_id, detail.modification_id);
});
JS;
    $facts = <<<TXT
ბაზა: {$base}

სერვერული API იყენებს საიდუმლო API გასაღებს geoapi_sk_. ის მხოლოდ თქვენს სერვერზე უნდა იყოს.
Authorization: Bearer <SERVER_API_KEY>
GET /api/v1/brands
GET /api/v1/brands/{slug}/models
GET /api/v1/models/{id}/generations
GET /api/v1/generations/{id}/modifications
GET /api/v1/modifications/{id}
GET /api/v1/coverage
სიის მოთხოვნებზე page ნაგულისხმევად 1-ია, per_page ნაგულისხმევად 50 და მაქსიმუმ 100. დეტალი და coverage ამ პარამეტრებს არ იყენებს.
თაობის id აიღეთ თაობების სიიდან. იგივე ჯგუფის სხვა შიდა id საჯარო id არ არის.
ბრენდი, მოდელი, თაობა და მოდიფიკაცია ცალკე მოთხოვნებია.

ვიჯეტი იყენებს საჯარო API გასაღებს geoapi_pk_. ის HTML-ში დასაშვებია.
<script src="{$base}/widget/v1/geoapi-filter.js"></script>
<geoapi-filter public-key="demo"></geoapi-filter>
ამ საიტზე demo მხოლოდ ამ origin-ზე მუშაობს. პროექტის გასაღები არის geoapi_pk_ და 32 თექვსმეტობითი სიმბოლო.
ვიჯეტი მუშაობს მხოლოდ პროექტში ჩაწერილი origin-იდან, მაგალითად https://example.com.
Origin არის პროტოკოლი, დომენი და საჭიროების შემთხვევაში პორტი. გვერდის ბილიკს არ შეიცავს.
wildcard (*) და ცარიელი Origin არ მიიღება. http დასაშვებია მხოლოდ localhost-ისა და 127.0.0.1-ისთვის.

geoapi:change
detail.brand_id, detail.model_id, detail.year და detail.body_type არის სტრიქონი ან null.
detail.generation_id და detail.modification_id ამ ვერსიაში ყოველთვის null-ია. მოდიფიკაციის არჩევა მხარდაჭერილი არ არის.
დამატებით შეიძლება იყოს brand_name, model_name, year_name და body_name.
ვიჯეტი თქვენი საიტის განცხადებებს ავტომატურად არ ფილტრავს. არჩევანის დაკავშირება თქვენს მონაცემებთან თქვენს აპლიკაციაში უნდა მოხდეს.

ლიმიტი გეგმაზეა დამოკიდებული (წუთში მოთხოვნები და თვიური კვოტა). დეტალები — გვერდზე „ტარიფები“.
მიმდინარე სერვერულ API-ს ფოტო არ აქვს. მოდიფიკაციების სია და დეტალი არის. ტექნიკური მახასიათებლების ცალკე მოთხოვნა არ არის.
საიდუმლო API გასაღები ბრაუზერში არ ჩასვათ.
TXT;
    $cursorFile = "---\ndescription: GeoAPI ვიჯეტი და სერვერული კატალოგი\nalwaysApply: true\n---\n\nეს ფაილი Cursor-ის წესია და ამ პროექტში მოქმედებს.\n\n" . trim($facts);
    $claudeFile = "ეს ფაილი Claude Code კითხულობს პროექტის ინსტრუქციად, როცა CLAUDE.md პროექტის ფესვშია.\n\n" . trim($facts);
    $codexFile = "ეს ფაილი Codex კითხულობს AGENTS.md ინსტრუქციად. Cursor-ის .mdc ფაილს ავტომატურად არ კითხულობს.\n\n" . trim($facts);
    echo \Carapi\SiteChrome::start(
        'GeoAPI-ის დოკუმენტაცია',
        'გაეცანით GeoAPI-ის ინტეგრაციის ინსტრუქციებს, ავტორიზაციას, API მეთოდებს, ვიჯეტის გამოყენებასა და მონაცემების მიღების მაგალითებს.',
        true
    );
    echo \Carapi\SiteChrome::header('guide');
    echo '<div class="wrap page" id="content"><div class="mbar"><span>დოკუმენტაცია</span><b>დაწყება ' . $chev . '</b></div><div class="dk">';
    echo '<aside class="side"><div class="srch">' . $search . '<input data-doc-search type="search" placeholder="ძიება" aria-label="ძიება დოკუმენტაციაში"></div>';
    echo '<h5>დაწყება</h5><a class="on" href="#start">საწყისი ინსტრუქცია</a><a href="#auth">ავტორიზაცია და API გასაღებები</a><a href="#limits">მოთხოვნების ლიმიტები და შეცდომები</a>';
    echo '<h5>ვიჯეტი</h5><a href="#widget">ვიჯეტის დამატება ვებსაიტზე</a><a href="#domains">დაშვებული დომენები</a><a href="#event">არჩევანის ცვლილების მოვლენა</a>';
    echo '<h5>AI ასისტენტებით ინტეგრაცია</h5><a href="#cursor">Cursor</a><a href="#claude">Claude Code</a><a href="#codex">Codex</a>';
    echo '<h5>API მეთოდები</h5><a href="#brands">ბრენდების მიღება<em>GET</em></a><a href="#models">მოდელების მიღება<em>GET</em></a><a href="#generations">თაობების მიღება<em>GET</em></a><a href="#modifications">მოდიფიკაციების მიღება<em>GET</em></a><a href="#modification">მოდიფიკაციის დეტალი<em>GET</em></a><a href="#coverage">კატალოგის სტატისტიკა<em>GET</em></a>';
    echo '</aside><article class="ct">';
    echo $status;
    echo '<div class="crumb">დოკუმენტაცია / დაწყება</div><h1 id="start" class="doc-sec">როგორ დავიწყოთ GeoAPI-ის გამოყენება</h1>';
    echo '<p class="lead">GeoAPI-ის გამოყენებისთვის შექმენით ანგარიში, დაადასტურეთ ელფოსტის მისამართი და დაამატეთ პროექტი. შემდეგ აირჩიეთ ინტეგრაციის მეთოდი თქვენი აპლიკაციის საჭიროებების მიხედვით.</p>';
    echo '<h2>ინტეგრაცია მზა ვიჯეტით</h2>';
    echo '<p>ვიჯეტი მომხმარებელს საშუალებას აძლევს, თქვენს ვებსაიტზე აირჩიოს ავტომობილის ბრენდი, მოდელი, გამოშვების წელი და ძარის ტიპი. ინტეგრაციისთვის საჭიროა საჯარო API გასაღები და დაშვებული საიტის მისამართი.</p>';
    echo '<h2>ინტეგრაცია სერვერული API-ით</h2>';
    echo '<p>სერვერული API უზრუნველყოფს ავტომობილების კატალოგის მონაცემების მიღებას HTTP მოთხოვნებით, JSON ფორმატში. მოთხოვნის ავტორიზაციისთვის გამოიყენება საიდუმლო API გასაღები.</p>';
    echo '<div class="call">' . $info . '<span>ვიჯეტი არ ფილტრავს თქვენი ვებსაიტის განცხადებებს ავტომატურად. მომხმარებლის არჩევანის საკუთარ მონაცემებთან დაკავშირება უნდა განხორციელდეს თქვენს აპლიკაციაში.</span></div>';

    echo '<h2 id="auth" class="doc-sec">ავტორიზაცია და API გასაღებები</h2>';
    echo '<p class="lead">GeoAPI იყენებს სხვადასხვა დანიშნულების საჯარო და საიდუმლო API გასაღებებს.</p>';
    echo '<h2>საჯარო API გასაღები</h2><p>პრეფიქსია <code>geoapi_pk_</code>. საჯარო API გასაღები გამოიყენება ვიჯეტის ბრაუზერში ჩასატვირთად. მისი გამოჩენა ვებსაიტის HTML ან JavaScript კოდში დასაშვებია.</p>';
    echo '<h2>საიდუმლო API გასაღები</h2><p>პრეფიქსია <code>geoapi_sk_</code>. საიდუმლო API გასაღები გამოიყენება სერვერული მოთხოვნების ავტორიზაციისთვის. მისი დამატება საჯაროდ ხელმისაწვდომ JavaScript კოდში დაუშვებელია.</p>';
    echo '<p>სერვერული API მოთხოვნისას დაამატეთ <code>Authorization</code> სათაური Bearer სქემით და მიუთითეთ თქვენი საიდუმლო API გასაღები. ქვემოთ მხოლოდ ნიმუშია. რეალურ გასაღებს მომხმარებელი საკუთარ პროექტში ქმნის.</p>';
    echo '<div class="codeb"><div class="tb"><div class="tabs"><button type="button" class="on">მოთხოვნის მაგალითი</button></div></div><pre class="json">Authorization: Bearer &lt;SERVER_API_KEY&gt;</pre></div>';
    echo '<p>გასაღები არ იქმნება ელფოსტის დადასტურებისთანავე. ჯერ შექმენით პროექტი, შემდეგ კაბინეტში აირჩიეთ საჯარო ან საიდუმლო API გასაღები.</p>';

    echo '<h2 id="brands" class="doc-sec">ბრენდების მიღება</h2>';
    echo '<p class="lead">აბრუნებს კატალოგში ხელმისაწვდომი ავტომობილების ბრენდების სიას.</p>';
    echo '<div class="ep"><b>GET</b><code>' . $h($base) . '/api/v1/brands</code></div>';
    echo '<h2>პარამეტრები</h2><table class="ptb"><thead><tr><th>სახელი</th><th>ტიპი</th><th>აღწერა</th></tr></thead><tbody>';
    echo '<tr><td><code>page</code></td><td><span class="pill">integer</span></td><td>მოთხოვნილი გვერდის ნომერი. ნაგულისხმევი მნიშვნელობაა 1. მაქსიმუმია 1000000.</td></tr>';
    echo '<tr><td><code>per_page</code></td><td><span class="pill">integer</span></td><td>ერთ გვერდზე დასაბრუნებელი ჩანაწერების მაქსიმალური რაოდენობა. ნაგულისხმევი მნიშვნელობაა 50, მაქსიმუმი 100.</td></tr>';
    echo '</tbody></table>';
    echo '<h2>მოთხოვნის მაგალითი</h2>';
    echo '<div class="codeb"><div class="tb"><div class="tabs"><button type="button" class="on" data-tab="curl">cURL</button><button type="button" data-tab="php">PHP</button></div><button type="button" class="copy" data-copy-block>' . $copy . 'კოდის კოპირება</button></div>';
    echo '<pre class="json" data-tab-panel="curl">' . $h($curl) . '</pre>';
    echo '<pre class="json" data-tab-panel="php" hidden>' . $h($php) . '</pre></div>';
    echo '<h2>პასუხის მაგალითი</h2><p>ქვემოთ ნაჩვენებია API-ის პასუხის მაგალითი. მონაცემები წარმოდგენილია განმარტების მიზნით.</p>';
    echo '<h2>HTTP სტატუსი</h2><p><span class="pill ok">200</span> <span class="pill">application/json</span></p>';
    echo '<h2>პასუხის სტრუქტურა</h2><div class="codeb"><pre class="json">' . $h(trim($brandJson)) . '</pre></div>';
    echo '<p><code>id</code> და <code>slug</code> სტრიქონებია. <code>synonyms</code> სტრიქონების სიაა. ლოგოს ველები ცარიელი რომ არის, მნიშვნელობა <code>null</code>-ია. ეს ლოგოები ვიჯეტში არ ჩანს.</p>';

    echo '<h2 id="models" class="doc-sec">მოდელების მიღება</h2>';
    echo '<div class="ep"><b>GET</b><code>/api/v1/brands/{slug}/models</code></div>';
    echo '<p>აბრუნებს მითითებული ბრენდის ავტომობილების მოდელებს. პარამეტრი <code>slug</code> წარმოადგენს ბრენდის URL-ში გამოსაყენებელ იდენტიფიკატორს, მაგალითად <code>toyota</code>. აქაც გამოიყენება <code>page</code> და <code>per_page</code>.</p>';
    echo '<h2>მოთხოვნის მაგალითი</h2><div class="codeb"><div class="tb"><button type="button" class="copy" data-copy-block>' . $copy . 'კოდის კოპირება</button></div>';
    echo '<pre class="json">' . $h("curl " . $base . "/api/v1/brands/toyota/models \\\n  -H \"Authorization: Bearer <SERVER_API_KEY>\"") . '</pre></div>';
    echo '<h2>პასუხის მაგალითი</h2><p>ქვემოთ ნაჩვენებია API-ის პასუხის მაგალითი. მონაცემები წარმოდგენილია განმარტების მიზნით.</p>';
    echo '<div class="codeb"><pre class="json">' . $h(trim($modelJson)) . '</pre></div>';
    echo '<p><code>has_generations</code> გვიჩვენებს, აქვს თუ არა მოდელს თაობა. <code>generations_count</code> მთელი რიცხვია. ზოგიერთი მოდელისთვის ის 0-ია.</p>';

    echo '<h2 id="generations" class="doc-sec">თაობების მიღება</h2>';
    echo '<div class="ep"><b>GET</b><code>/api/v1/models/{id}/generations</code></div>';
    echo '<p>აბრუნებს მითითებული მოდელის თაობების სიას. გამოიყენეთ მოდელის იდენტიფიკატორი, რომელიც API-ის პასუხშია მოცემული. <code>id</code> დადებითი მთელი რიცხვია და JSON-ში სტრიქონად ბრუნდება. ზოგიერთი მოდელისთვის თაობების სია შესაძლოა ცარიელი იყოს. <code>page</code> და <code>per_page</code> აქაც მოქმედებს.</p>';
    echo '<h2>მოთხოვნის მაგალითი</h2><div class="codeb"><pre class="json">' . $h("curl " . $base . "/api/v1/models/20/generations \\\n  -H \"Authorization: Bearer <SERVER_API_KEY>\"") . '</pre></div>';
    echo '<h2>პასუხის მაგალითი</h2><p>ქვემოთ ნაჩვენებია API-ის პასუხის მაგალითი. მონაცემები წარმოდგენილია განმარტების მიზნით.</p>';
    echo '<div class="codeb"><pre class="json">' . $h(trim($generationJson)) . '</pre></div>';
    echo '<p><code>year_start</code> და <code>year_end</code> მთელი რიცხვი ან <code>null</code>-ია. <code>body_type</code> ძარის კოდია, მაგალითად <code>sedan</code>, ან <code>null</code>. <code>market</code> ამ ვერსიაში <code>null</code>-ია.</p>';
    echo '<p>თაობის <code>id</code> საჯარო იდენტიფიკატორია. ერთი მოდელის ფარგლებში ერთნაირი შასის კოდი, ან შასის არარსებობისას ერთნაირი სახელი, ერთ ჯგუფად ითვლება. სია ამ ჯგუფის უმცირეს <code>id</code>-ს აბრუნებს. მოდიფიკაციების სიისთვის გამოიყენეთ სწორედ ეს <code>id</code>. ჯგუფის სხვა შიდა ნომერი საჯარო მისამართი არ არის და 404-ს აბრუნებს.</p>';

    echo '<h2 id="modifications" class="doc-sec">მოდიფიკაციების მიღება</h2>';
    echo '<div class="ep"><b>GET</b><code>/api/v1/generations/{id}/modifications</code></div>';
    echo '<p>აბრუნებს ერთი საჯარო თაობის მოდიფიკაციების სიას. <code>id</code> არის თაობების სიაში დაბრუნებული საჯარო იდენტიფიკატორი. იგივე ჯგუფის სხვა შიდა ნომერი 404-ს აბრუნებს. <code>page</code> და <code>per_page</code> აქაც მოქმედებს. სხვა ფილტრი არ არის.</p>';
    echo '<h2>მოთხოვნის მაგალითი</h2><div class="codeb"><div class="tb"><button type="button" class="copy" data-copy-block>' . $copy . 'კოდის კოპირება</button></div>';
    echo '<pre class="json">' . $h("curl " . $base . "/api/v1/generations/30/modifications \\\n  -H \"Authorization: Bearer <SERVER_API_KEY>\"") . '</pre></div>';
    echo '<h2>პასუხის მაგალითი</h2><p>ქვემოთ ნაჩვენებია API-ის პასუხის მაგალითი. მონაცემები განმარტებისთვისაა.</p>';
    echo '<div class="codeb"><pre class="json">' . $h(trim($modificationsJson)) . '</pre></div>';
    echo '<p><code>id</code> მოდიფიკაციის სტაბილური იდენტიფიკატორია. <code>generation_id</code> იმავე საჯარო თაობის <code>id</code>-ს უდრის. <code>engine_volume_cc</code>, <code>year_start</code> და <code>year_end</code> მთელი რიცხვი ან <code>null</code>-ია. <code>transmission</code> სტრიქონი ან <code>null</code>-ია. სახელები არ ერთიანდება.</p>';

    echo '<h2 id="modification" class="doc-sec">მოდიფიკაციის დეტალი</h2>';
    echo '<div class="ep"><b>GET</b><code>/api/v1/modifications/{id}</code></div>';
    echo '<p>აბრუნებს ერთ მოდიფიკაციას. <code>id</code> აიღეთ მოდიფიკაციების სიიდან. ეს სია არ არის, ამიტომ <code>page</code> და <code>per_page</code> არ გამოიყენება. ტექნიკური მახასიათებლების ცალკე ობიექტი ამ პასუხში არ არის.</p>';
    echo '<h2>მოთხოვნის მაგალითი</h2><div class="codeb"><div class="tb"><button type="button" class="copy" data-copy-block>' . $copy . 'კოდის კოპირება</button></div>';
    echo '<pre class="json">' . $h("curl " . $base . "/api/v1/modifications/40 \\\n  -H \"Authorization: Bearer <SERVER_API_KEY>\"") . '</pre></div>';
    echo '<h2>პასუხის მაგალითი</h2><p>ქვემოთ ნაჩვენებია API-ის პასუხის მაგალითი. მონაცემები განმარტებისთვისაა.</p>';
    echo '<div class="codeb"><pre class="json">' . $h(trim($modificationJson)) . '</pre></div>';
    echo '<p><code>data</code> ობიექტია, სია არა. <code>brand</code> და <code>model</code> ყოველთვის ობიექტებია. <code>generation</code> ობიექტია ან <code>null</code>, თუ მოდიფიკაციას თაობა არ აქვს. <code>generation.id</code>, როცა არის, საჯარო თაობის იდენტიფიკატორია და არა ჯგუფის სხვა შიდა ნომერი. <code>drive_type</code> და <code>body_type</code> სტრიქონი ან <code>null</code>-ია.</p>';

    echo '<h2 id="coverage" class="doc-sec">კატალოგის სტატისტიკა</h2>';
    echo '<div class="ep"><b>GET</b><code>/api/v1/coverage</code></div>';
    echo '<p>აბრუნებს კატალოგში არსებული ბრენდების, მოდელების, თაობებისა და მოდიფიკაციების რაოდენობას. <code>page</code> და <code>per_page</code> ამ მოთხოვნაზე არ გამოიყენება. <code>photos_count</code> ფოტოების ცხრილის რაოდენობაა. ეს მოთხოვნა ფოტოს ფაილს არ აბრუნებს.</p>';
    echo '<h2>მოთხოვნის მაგალითი</h2><div class="codeb"><pre class="json">' . $h("curl " . $base . "/api/v1/coverage \\\n  -H \"Authorization: Bearer <SERVER_API_KEY>\"") . '</pre></div>';
    echo '<h2>პასუხის მაგალითი</h2><p>ქვემოთ ნაჩვენებია API-ის პასუხის მაგალითი. რიცხვები განმარტებისთვისაა და კატალოგის მიმდინარე რაოდენობას არ ასახავს.</p>';
    echo '<div class="codeb"><pre class="json">' . $h(trim($coverageJson)) . '</pre></div>';

    echo '<h2 id="widget" class="doc-sec">ვიჯეტის დამატება ვებსაიტზე</h2>';
    echo '<p>GeoAPI-ის ვიჯეტის გამოყენებით შეგიძლიათ თქვენს ვებსაიტს დაამატოთ ავტომობილის არჩევის ინტერფეისი. ვიჯეტი იტვირთება JavaScript ფაილის საშუალებით და მუშაობისთვის იყენებს საჯარო API გასაღებს.</p>';
    echo '<div class="codeb"><div class="tb"><div class="tabs"><button type="button" class="on" data-tab="html">HTML</button></div><button type="button" class="copy" data-copy-block>' . $copy . 'კოდის კოპირება</button></div>';
    echo '<pre class="json" data-tab-panel="html">' . $h("<script src=\"" . $base . "/widget/v1/geoapi-filter.js\"></script>\n<geoapi-filter public-key=\"demo\"></geoapi-filter>") . '</pre></div>';
    echo '<p>ამ საიტზე <code>demo</code> მხოლოდ ამ origin-ზე მუშაობს. პროექტის გასაღები არის <code>geoapi_pk_</code> და 32 თექვსმეტობითი სიმბოლო.</p>';
    echo '<p>ვიჯეტი კატალოგს იღებს <code>POST</code> მოთხოვნებით: <code>/widget/v1/{publicKey}/brands</code>, <code>/widget/v1/{publicKey}/brands/{slug}/models</code>, <code>/widget/v1/{publicKey}/models/{id}/years</code> და <code>/widget/v1/{publicKey}/models/{id}/generations</code>. ეს სერვერული <code>GET /api/v1</code> მოთხოვნებისაგან დამოუკიდებელი გზაა.</p>';
    echo '<p>ვიჯეტი კატალოგს იღებს <code>POST</code> მოთხოვნებით: <code>/widget/v1/{publicKey}/brands</code>, <code>/widget/v1/{publicKey}/brands/{slug}/models</code> და <code>/widget/v1/{publicKey}/models/{id}/years</code>. ეს სერვერული <code>GET /api/v1</code> მოთხოვნებისაგან დამოუკიდებელი გზაა.</p>';

    echo '<h2 id="domains" class="doc-sec">საიტის მისამართის დამატება</h2>';
    echo '<p>ვიჯეტის გამოყენებამდე პროექტის პარამეტრებში მიუთითეთ საიტის origin, საიდანაც იგზავნება მოთხოვნები.</p>';
    echo '<p>Origin წარმოადგენს პროტოკოლის, დომენისა და საჭიროების შემთხვევაში პორტის ერთობლიობას. მაგალითია <code>https://example.com</code>. Origin არ შეიცავს URL-ის გვერდის ბილიკს.</p>';
    echo '<p>Wildcard (<code>*</code>) და ცარიელი <code>Origin</code> სათაური არ მიიღება. <code>http</code> დასაშვებია მხოლოდ <code>localhost</code>-ისა და <code>127.0.0.1</code>-ისთვის. სხვა საიტს სჭირდება <code>https</code>.</p>';

    echo '<h2 id="event" class="doc-sec">ავტომობილის არჩევანის ცვლილება</h2>';
    echo '<p>მომხმარებლის არჩევანის ცვლილებისას ვიჯეტი აგზავნის <code>geoapi:change</code> მოვლენას. ამ მოვლენის <code>detail</code> ობიექტი შეიცავს მომხმარებლის მიერ არჩეულ ავტომობილთან დაკავშირებულ მონაცემებს.</p>';
    echo '<div class="codeb"><div class="tb"><button type="button" class="copy" data-copy-block>' . $copy . 'კოდის კოპირება</button></div><pre class="json">' . $h(trim($eventJs)) . '</pre></div>';
    echo '<h2>პასუხის სტრუქტურა</h2><table class="ptb"><thead><tr><th>ველი</th><th>ტიპი</th><th>დანიშნულება</th></tr></thead><tbody>';
    echo '<tr><td><code>brand_id</code></td><td><span class="pill">string ან null</span></td><td>არჩეული ბრენდის იდენტიფიკატორი. ცარიელი არჩევანისას <code>null</code>-ია.</td></tr>';
    echo '<tr><td><code>model_id</code></td><td><span class="pill">string ან null</span></td><td>არჩეული მოდელის იდენტიფიკატორი. ცარიელი არჩევანისას <code>null</code>-ია.</td></tr>';
    echo '<tr><td><code>year</code></td><td><span class="pill">string ან null</span></td><td>გამოშვების წელი სტრიქონად, მაგალითად <code>2018</code>. რიცხვი არ არის.</td></tr>';
    echo '<tr><td><code>body_type</code></td><td><span class="pill">string ან null</span></td><td>ძარის ტიპის კოდი, მაგალითად <code>sedan</code>. არჩევანის გარეშე <code>null</code>-ია.</td></tr>';
    echo '<tr><td><code>generation_id</code></td><td><span class="pill">null</span></td><td>მიმდინარე ვერსიაში ყოველთვის <code>null</code>-ია. ვიჯეტი თაობას ცალკე არ ირჩევს.</td></tr>';
    echo '<tr><td><code>modification_id</code></td><td><span class="pill">null</span></td><td>მიმდინარე ვერსიაში ყოველთვის <code>null</code>-ია. მოდიფიკაციის არჩევა მხარდაჭერილი არ არის.</td></tr>';
    echo '</tbody></table>';
    echo '<p>დამატებით ველებია <code>brand_name</code>, <code>model_name</code>, <code>year_name</code> და <code>body_name</code>. თითოეული სტრიქონი ან <code>null</code>-ია და მხოლოდ ეკრანზე საჩვენებელ სახელს შეიცავს.</p>';
    echo '<p>მაგალითი, არა მიმდინარე შედეგი: <code>{"brand_id":"10","model_id":"20","year":"2018","body_type":"sedan","generation_id":null,"modification_id":null,"brand_name":"Toyota","model_name":"Camry","year_name":"2018","body_name":"სედანი"}</code></p>';

    echo '<h2 id="agents" class="doc-sec">GeoAPI-ის ინტეგრაცია AI კოდის ასისტენტებით</h2>';
    echo '<p class="lead">შეგიძლიათ გამოიყენოთ Cursor, Claude Code ან Codex, რათა გაამარტივოთ GeoAPI-ის ინტეგრაცია თქვენს პროექტში. ასისტენტს უნდა ჰქონდეს ინფორმაცია API მეთოდების, ავტორიზაციისა და ვიჯეტის მუშაობის შესახებ.</p>';
    echo '<div class="agent-grid">';
    echo '<article class="agent" id="cursor"><div class="mark">' . carapi_logo('cursor') . '</div><h3>GeoAPI-ის წესების ფაილის დამატება Cursor-ში</h3><span class="file">.cursor/rules/geoapi.mdc</span><p>შექმენით პროექტში შესაბამისი Cursor Rules ფაილი და დაამატეთ GeoAPI-ის ინტეგრაციის ინსტრუქციები. ფაილს აქვს frontmatter <code>alwaysApply: true</code>-ით, ამიტომ Cursor ამ წესს პროექტში იყენებს.</p></article>';
    echo '<article class="agent" id="claude"><div class="mark">' . carapi_logo('claude') . '</div><h3>GeoAPI-ის ინსტრუქციების დამატება CLAUDE.md ფაილში</h3><span class="file">CLAUDE.md</span><p>Claude Code პროექტის ფესვში არსებულ <code>CLAUDE.md</code> ფაილს კითხულობს. ეს Cursor-ის <code>.mdc</code> ფაილი არ არის და frontmatter არ სჭირდება.</p></article>';
    echo '<article class="agent" id="codex"><div class="mark">' . carapi_logo('openai') . '</div><h3>GeoAPI-ის ინსტრუქციების დამატება AGENTS.md ფაილში</h3><span class="file">AGENTS.md</span><p>Codex პროექტის ფესვში არსებულ <code>AGENTS.md</code> ფაილს კითხულობს. ის ავტომატურად არ კითხულობს არც <code>.cursor/rules/geoapi.mdc</code>-ს და არც <code>CLAUDE.md</code>-ს.</p></article>';
    echo '</div>';
    echo '<div class="codeb codeb-mt"><div class="tb"><div class="tabs"><button type="button" class="on" data-tab="cursor">Cursor</button><button type="button" data-tab="claude">Claude Code</button><button type="button" data-tab="codex">Codex</button></div><button type="button" class="copy" data-copy-block>' . $copy . 'კოდის კოპირება</button></div>';
    echo '<pre class="json" data-tab-panel="cursor">' . $h($cursorFile) . '</pre>';
    echo '<pre class="json" data-tab-panel="claude" hidden>' . $h($claudeFile) . '</pre>';
    echo '<pre class="json" data-tab-panel="codex" hidden>' . $h($codexFile) . '</pre></div>';
    echo '<p>მისამართი ამ საიტზეა: <code>' . $h($base) . '</code>. ვიჯეტის ჩართვამდე პროექტში ჩაწერეთ საიტის origin.</p>';

    echo '<h2 id="limits" class="doc-sec">მოთხოვნების ლიმიტები და შეცდომების დამუშავება</h2>';
    echo '<p>GeoAPI-ის მოთხოვნებზე მოქმედებს სიხშირისა და თვიური მოხმარების შეზღუდვები. ნაგულისხმევი ლიმიტი გეგმაზეა დამოკიდებული (წუთში მოთხოვნები და თვიური კვოტა). დეტალები — გვერდზე „ტარიფები“. ლიმიტის მიღწევის შემთხვევაში API აბრუნებს შესაბამის HTTP სტატუსს.</p>';
    echo '<p><strong>401 — ავტორიზაცია ვერ შესრულდა.</strong> API გასაღები არ არის მითითებული, არასწორია ან ვეღარ გამოიყენება. გაუქმებული ან ვადაგასული საიდუმლო გასაღებიც ამ სტატუსს აბრუნებს.</p>';
    echo '<p><strong>403 — მოთხოვნა არ არის დაშვებული.</strong> მოთხოვნა არ აკმაყოფილებს შესაბამისი გასაღების ან პროექტის წვდომის პირობებს. ასეთი პირობაა, მაგალითად, დაუდასტურებელი ელფოსტა, გამორთული პროექტი ან ვიჯეტისთვის დაუშვებელი origin.</p>';
    echo '<p><strong>429 — მოთხოვნების ლიმიტი მიღწეულია.</strong> მოთხოვნების რაოდენობამ გადააჭარბა დაშვებულ ზღვარს. კოდია <code>RATE_LIMIT_EXCEEDED</code> სიხშირის ლიმიტზე და <code>MONTHLY_QUOTA_EXCEEDED</code> თვიურ ლიმიტზე. თუ პასუხი შეიცავს <code>Retry-After</code> სათაურს, გამოიყენეთ ის განმეორებითი მცდელობის დაგეგმვისთვის.</p>';
    echo '<p><strong>503 — სერვისი დროებით მიუწვდომელია.</strong> სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.</p>';
    echo '<p><strong>404</strong> ნიშნავს, რომ მისამართი, ბრენდი, მოდელი, თაობა ან მოდიფიკაცია ვერ მოიძებნა. თაობის საჯარო <code>id</code>-ს გარეშე მოდიფიკაციების სიაც 404-ია. <strong>405</strong> ნიშნავს, რომ HTTP მეთოდი დაუშვებელია. <strong>422</strong> ნიშნავს, რომ <code>page</code>, <code>per_page</code>, მოდელის, თაობის ან მოდიფიკაციის <code>id</code> არასწორია.</p>';
    echo '<p>წარმატებული <strong>200</strong> თვიურ კვოტას ხარჯავს და აბრუნებს <code>X-Quota-Limit</code>, <code>X-Quota-Remaining</code> და <code>X-Quota-Reset</code> სათაურებს. 401, 403, 404, 405, 422 და 429 თვიურ კვოტას არ ხარჯავს. 404 და 422 ამ კვოტის სათაურებს არ აბრუნებს.</p>';
    echo '</article><aside class="toc"><h5>ამ გვერდზე</h5><a class="on" href="#start">საწყისი ინსტრუქცია</a><a href="#auth">ავტორიზაცია</a><a href="#brands">ბრენდები</a><a href="#modifications">მოდიფიკაციები</a><a href="#widget">ვიჯეტი</a><a href="#agents">AI ასისტენტები</a><a href="#limits">ლიმიტები</a></aside>';
    echo '</div></div>';
    echo \Carapi\SiteChrome::footer();
    echo \Carapi\SiteChrome::end();
}


function carapi_landing(): void
{
    $counts = carapi_counts_view();
    $open = \Carapi\Platform\Http::enabled();
    $primary = $open
        ? '<a class="btn btn-l" href="/register">უფასო ანგარიშის შექმნა</a>'
        : '<span class="btn btn-g">მალე გაიხსნება</span>';
    $months = [1 => 'იან', 2 => 'თებ', 3 => 'მარ', 4 => 'აპრ', 5 => 'მაი', 6 => 'ივნ', 7 => 'ივლ', 8 => 'აგვ', 9 => 'სექ', 10 => 'ოქტ', 11 => 'ნოე', 12 => 'დეკ'];
    $when = $months[(int) date('n')] . '. ' . date('Y');
    $base = htmlspecialchars(carapi_self_base(), ENT_QUOTES);
    $heroNote = 'ყველა ავტომობილის მოდელისთვის თაობის მონაცემები ხელმისაწვდომი არ არის. მიმდინარე ვერსია არ შეიცავს ავტომობილების ფოტოებს. მოდიფიკაციების სია და დეტალი სერვერულ API-შია.';
    $countsId = '';
    if ($counts['status'] === 'ok') {
        $heroNote = '<b>' . $counts['brands'] . '</b> ბრენდი · <b>' . $counts['models'] . '</b> მოდელი · <b>'
            . $counts['generations'] . '</b> თაობა. ' . $heroNote;
        $countsId = ' id="counts"';
    }
    $chev = \Carapi\SiteChrome::icon('chev');
    $copy = \Carapi\SiteChrome::icon('copy');
    $install = '&lt;script src="' . $base . '/widget/v1/geoapi-filter.js"&gt;&lt;/script&gt;';
    echo \Carapi\SiteChrome::start(
        'GeoAPI — ავტომობილების კატალოგი, API და მზა ვიჯეტი',
        'GeoAPI დეველოპერებს სთავაზობს ავტომობილების ბრენდების, მოდელებისა და თაობების მონაცემებს REST API-ის საშუალებით და ავტომობილის არჩევის მზა ვიჯეტს ვებსაიტებისთვის.',
        true
    );
    echo \Carapi\SiteChrome::header();
    echo <<<HTML
<main id="content">
<section class="hero">
<div class="wrap grid">
<div class="date"><b class="mono">GeoAPI</b><span class="mono">API v1</span><span class="ka">{$when}</span></div>
<div>
<h1>ავტომობილების მონაცემები თქვენი საიტისთვის — <span>API ან მზა ვიჯეტი</span></h1>
<div class="meta"><p>მიიღეთ ავტომობილების ბრენდების, მოდელებისა და თაობების მონაცემები API-ის საშუალებით, ან დაამატეთ თქვენს ვებსაიტს ავტომობილის არჩევის მზა ვიჯეტი.</p>
<div class="ctas">{$primary}<a class="btn btn-g" href="/guide">დოკუმენტაციის ნახვა</a></div></div>
</div>
<div class="cmd" id="demo">
{$counts['note']}
<p class="hero-note"><b>ნახეთ, როგორ მუშაობს ავტომობილის არჩევა</b><br>აირჩიეთ ბრენდი, მოდელი, გამოშვების წელი და ძარის ტიპი, რათა ნახოთ, როგორ მუშაობს ავტომობილის არჩევის კომპონენტი.</p>
<div class="cl">
<span class="pr">GET /api/v1/brands</span>
<button class="st" type="button" data-step="brand"><small>ბრენდი</small><b class="ph">აირჩიეთ ბრენდი</b>{$chev}</button>
<button class="st" type="button" data-step="model"><small>მოდელი</small><b class="ph">აირჩიეთ მოდელი</b>{$chev}</button>
<button class="st" type="button" data-step="year"><small>გამოშვების წელი</small><b class="ph">აირჩიეთ წელი</b>{$chev}</button>
<button class="st" type="button" data-step="body"><small>ძარის ტიპი</small><b class="ph">აირჩიეთ ძარის ტიპი</b>{$chev}</button>
</div>
<div class="picker" id="demo-picker" hidden>
<input id="demo-q" type="search" placeholder="ძიება" aria-label="სიაში ძიება" autocomplete="off">
<ul id="demo-list"></ul>
</div>
<div class="res">
<pre class="json" id="demo-json"></pre>
<div class="chain">
<span class="l">ავტომობილების კატალოგის სტრუქტურა</span>
<div class="ln" id="demo-chain"></div>
<span class="l">დაამატეთ ავტომობილის არჩევის ვიჯეტი</span>
<p class="hero-note">ჩასვით ვიჯეტის სკრიპტი თქვენს ვებსაიტზე და გამოიყენეთ საჯარო API გასაღები. მომხმარებლის მიერ არჩეული მონაცემები ხელმისაწვდომი იქნება JavaScript მოვლენის საშუალებით.</p>
<div class="inst"><span id="install-snippet">{$install}</span><button type="button" id="copy-install" aria-label="კოდის კოპირება">{$copy}</button></div>
</div>
</div>
<p class="hero-note"{$countsId}>{$heroNote}</p>
</div>
</div>
<div class="mq" aria-hidden="true"><div class="tr" id="brand-marquee"></div></div>
</section>
</main>
HTML;
    echo \Carapi\SiteChrome::footer();
    echo \Carapi\SiteChrome::end();
}
