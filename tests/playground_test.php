<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$secret = '';
foreach (file('/www/server/carapi/platform.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'CARAPI_PLATFORM_PASSWORD=')) {
        $secret = substr($line, 26);
    }
}
require $root . '/vendor/autoload.php';
$admin = new PDO('pgsql:host=127.0.0.1;dbname=postgres', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('DROP DATABASE IF EXISTS carapi_playground_test WITH (FORCE)');
$admin->exec('CREATE DATABASE carapi_playground_test');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_playground_test', 'CARAPI_PLATFORM_ADMIN_USER' => 'postgres', 'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
]);
stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($migrate);
$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_playground_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_playground_test TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');
$db->exec('CREATE TABLE public.brands (id bigint PRIMARY KEY, slug text NOT NULL, name text NOT NULL, synonyms jsonb, is_lcv_or_commercial boolean NOT NULL DEFAULT false, logo_url_light text, logo_url_dark text)');
$db->exec('CREATE TABLE public.models (id bigint PRIMARY KEY, brand_id bigint NOT NULL, slug text NOT NULL, name text NOT NULL)');
$db->exec('CREATE TABLE public.model_markets (model_id bigint, market_slug text)');
$db->exec('CREATE TABLE public.generations (id bigint PRIMARY KEY, model_id bigint NOT NULL, slug text, name text, year_start integer, year_end integer, body_type text, chassis_code text, steering_wheel text, photo_urls jsonb)');
$db->exec('CREATE TABLE public.modifications (id bigint PRIMARY KEY, generation_id bigint, model_id bigint NOT NULL, name text NOT NULL, year_start integer, year_end integer, transmission text, engine_volume_cc integer, drive_type text, body_type text)');
$db->exec('CREATE TABLE public.photos (id bigint PRIMARY KEY)');
$db->exec("INSERT INTO public.brands (id, slug, name, synonyms) VALUES (1, 'toyota', 'Toyota', '[]'::jsonb)");
$db->exec("INSERT INTO public.models (id, brand_id, slug, name) VALUES (20, 1, 'camry', 'Camry')");
$db->exec("INSERT INTO public.generations (id, model_id, slug, name, year_start, year_end, body_type, chassis_code) VALUES (20, 20, 'xv70', 'XV70', 2017, 2024, 'sedan', 'XV70'), (21, 20, 'xv70-b', 'XV70', 2018, 2024, 'sedan', 'xv70')");
$db->exec("INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc, drive_type, body_type) VALUES (40, 21, 20, '2.5', 2018, 2024, 'automatic', 2500, 'fwd', 'sedan')");
$cacheFile = $root . '/cache/public-counts.json';
$cacheBackup = is_file($cacheFile) ? file_get_contents($cacheFile) : null;
register_shutdown_function(static function () use ($cacheFile, &$cacheBackup): void {
    if ($cacheBackup === null) {
        if (is_file($cacheFile)) {
            unlink($cacheFile);
        }

        return;
    }
    file_put_contents($cacheFile, $cacheBackup);
});
$password = 'ქართული-პაროლი-12345';
$hash = \Carapi\Platform\Passwords::hash($password);
$insertUser = $db->prepare('INSERT INTO platform.users (first_name, last_name, email, password_hash, email_verified_at) VALUES (:f,:l,:e,:p, :v)');
$insertUser->execute(['f' => 'ანა', 'l' => 'ერთი', 'e' => 'ana@example.com', 'p' => $hash, 'v' => gmdate('c')]);
$insertUser->execute(['f' => 'ბია', 'l' => 'ორი', 'e' => 'bia@example.com', 'p' => $hash, 'v' => gmdate('c')]);
$db->prepare('INSERT INTO platform.users (first_name, last_name, email, password_hash) VALUES (:f,:l,:e,:p)')->execute(['f' => 'გია', 'l' => 'სამი', 'e' => 'gia@example.com', 'p' => $hash]);
$env = [
    'CARAPI_PLATFORM_ENABLED' => '1', 'CARAPI_PLATFORM_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_PORT' => '5432',
    'CARAPI_PLATFORM_DATABASE' => 'carapi_playground_test', 'CARAPI_PLATFORM_USER' => 'carapi_platform_app', 'CARAPI_PLATFORM_PASSWORD' => $secret,
    'CARAPI_PLATFORM_SESSION_PATH' => '/tmp/carapi-playground-sessions',
    'CARAPI_CATALOG_ENABLED' => '1', 'CARAPI_CATALOG_HOST' => '127.0.0.1', 'CARAPI_CATALOG_PORT' => '5432',
    'CARAPI_CATALOG_DATABASE' => 'carapi_playground_test', 'CARAPI_CATALOG_USER' => 'postgres', 'CARAPI_CATALOG_PASSWORD' => '',
    'CARAPI_MODIFICATIONS_ENABLED' => '1', 'CARAPI_MODIFICATION_DETAILS_ENABLED' => '1',
    'CARAPI_CATALOG_STATEMENT_TIMEOUT_MS' => '10000', 'CARAPI_IP_PER_MINUTE' => '40',
];
$port = 18097;
$base = 'http://127.0.0.1:' . $port;
$proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'], [1 => ['file', '/tmp/playground.log', 'w'], 2 => ['file', '/tmp/playground.err', 'w']], $u, $root, $env);
$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'ok ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures[] = $name;
    }
};
$http = static function (string $method, string $path, array $fields = [], ?string $cookie = null) use (&$base): array {
    $ch = curl_init($base . $path);
    $headers = $cookie ? ['Cookie: carapi_platform=' . $cookie] : [];
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 15];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $header = substr($raw, 0, $size);
    $body = substr($raw, $size);
    preg_match('/^Set-Cookie:\s*carapi_platform=([^;]+)/mi', $header, $cookieMatch);

    return ['status' => $status, 'header' => $header, 'body' => $body, 'cookie' => $cookieMatch[1] ?? $cookie];
};
usleep(200000);
$login = static function (string $email) use ($http, $password): array {
    $page = $http('GET', '/login');
    preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $csrf);

    return $http('POST', '/login', ['_csrf' => $csrf[1] ?? '', 'email' => $email, 'password' => $password], $page['cookie']);
};
$used = static function () use ($db): int {
    return (int) $db->query('SELECT COALESCE(sum(used_count), 0) FROM platform.monthly_usage')->fetchColumn();
};
$guest = $http('GET', '/account/projects/1/playground');
$check($guest['status'] === 302 && str_contains($guest['header'], 'Location: /login'), 'guest page redirected to login');
$ana = $login('ana@example.com');
$bia = $login('bia@example.com');
$gia = $login('gia@example.com');
$account = $http('GET', '/account', [], $ana['cookie']);
preg_match('/name="_csrf" value="([^"]+)"/', $account['body'], $csrf);
$http('POST', '/account', ['_csrf' => $csrf[1] ?? '', 'action' => 'create_project', 'name' => 'ანას პროექტი'], $ana['cookie']);
$list = $http('GET', '/account', [], $ana['cookie']);
preg_match('#/account/projects/([1-9][0-9]*)#', $list['body'], $id);
$projectId = $id[1] ?? '0';
$owner = $http('GET', '/account/projects/' . $projectId . '/playground', [], $ana['cookie']);
$check($owner['status'] === 200 && str_contains($owner['body'], 'API Playground'), 'owner can open playground');
preg_match('/name="_csrf" value="([^"]+)"/', $owner['body'], $runCsrf);
$token = $runCsrf[1] ?? '';
$routes = [
    '/api/v1/brands',
    '/api/v1/brands/{slug}/models',
    '/api/v1/models/{id}/generations',
    '/api/v1/generations/{id}/modifications',
    '/api/v1/modifications/{id}',
    '/api/v1/coverage',
];
preg_match_all('/<option value="([^"]+)"/', $owner['body'], $options);
$check($options[1] === $routes, 'selector lists only approved routes');
$check(!str_contains($owner['body'], '<option disabled') && str_contains($owner['body'], 'Playground-ის წარმატებული მოთხოვნები ითვლება თქვენი პროექტის API კვოტაში.'), 'all six operations enabled');
$check(!str_contains($owner['body'], 'carapi_sk_') && str_contains($owner['body'], 'YOUR_API_KEY'), 'placeholder key only');
$check(str_contains($owner['body'], 'ბრენდების სიის მიღება') && str_contains($owner['body'], 'კატალოგის დაფარვის სტატისტიკა'), 'endpoint descriptions present');
$check(str_contains($owner['body'], 'ბრენდის კოდი (slug)') && str_contains($owner['body'], 'გვერდი') && str_contains($owner['body'], 'ჩანაწერები გვერდზე'), 'parameter labels present');
$check(str_contains($owner['body'], 'თაობის საჯარო ID აიღეთ თაობების სიის პასუხიდან'), 'generation id guidance present');
$check(str_contains($owner['body'], 'იგზავნება') && str_contains($owner['body'], 'if (sending) return') && str_contains($owner['body'], 'ქსელის შეცდომა'), 'loading and double submit guard present');
$check(str_contains($owner['body'], '429: "მოთხოვნების ლიმიტი მიღწეულია"') && str_contains($owner['body'], '404: "ჩანაწერი ვერ მოიძებნა"'), 'status messages present');
$check(!str_contains($owner['body'], 'innerHTML'), 'response is not written as html');
$check(str_contains($owner['body'], 'მოთხოვნის გაგზავნა') && !str_contains($owner['body'], 'type="submit" disabled'), 'brands send button is enabled');
$other = $http('GET', '/account/projects/' . $projectId . '/playground', [], $bia['cookie']);
$check($other['status'] === 404, 'other user gets 404');
$missing = $http('GET', '/account/projects/999999/playground', [], $ana['cookie']);
$check($missing['status'] === 404, 'missing project gets 404');
$dash = $http('GET', '/account/projects/' . $projectId, [], $ana['cookie']);
$check($dash['status'] === 200 && str_contains($dash['body'], 'href="/account/projects/' . $projectId . '/playground"'), 'dashboard navigation still works');
$run = '/account/projects/' . $projectId . '/playground/run';
$fields = static function (array $extra = []) use ($token): array {
    return ['_csrf' => $token, 'operation' => '/api/v1/brands'] + $extra;
};
$guestRun = $http('POST', $run, $fields());
$check($guestRun['status'] === 401, 'unauthenticated run rejected');
$noCsrf = $http('POST', $run, ['operation' => '/api/v1/brands'], $ana['cookie']);
$check($noCsrf['status'] === 419, 'missing csrf rejected');
$badCsrf = $http('POST', $run, ['_csrf' => 'არასწორი', 'operation' => '/api/v1/brands'], $ana['cookie']);
$check($badCsrf['status'] === 419, 'invalid csrf rejected');
$biaAccount = $http('GET', '/account', [], $bia['cookie']);
preg_match('/name="_csrf" value="([^"]+)"/', $biaAccount['body'], $biaCsrf);
$steal = $http('POST', $run, ['_csrf' => $biaCsrf[1] ?? '', 'operation' => '/api/v1/brands'], $bia['cookie']);
$check($steal['status'] === 404, 'cross project rejected');
$db->exec('UPDATE platform.projects SET status = \'disabled\' WHERE id = ' . (int) $projectId);
$disabled = $http('POST', $run, $fields(), $ana['cookie']);
$check($disabled['status'] === 403, 'inactive project rejected');
$db->exec('UPDATE platform.projects SET status = \'active\' WHERE id = ' . (int) $projectId);
$anaId = (int) $db->query("SELECT id FROM platform.users WHERE email = 'ana@example.com'")->fetchColumn();
$db->exec('UPDATE platform.users SET status = \'suspended\' WHERE id = ' . $anaId);
$inactiveUser = $http('POST', $run, $fields(), $ana['cookie']);
$check($inactiveUser['status'] === 401, 'inactive user rejected');
$db->exec('UPDATE platform.users SET status = \'active\' WHERE id = ' . $anaId);
$ana = $login('ana@example.com');
$owner = $http('GET', '/account/projects/' . $projectId . '/playground', [], $ana['cookie']);
preg_match('/name="_csrf" value="([^"]+)"/', $owner['body'], $runCsrf);
$token = $runCsrf[1] ?? '';
$giaAccount = $http('GET', '/account', [], $gia['cookie']);
preg_match('/name="_csrf" value="([^"]+)"/', $giaAccount['body'], $giaCsrf);
$http('POST', '/account', ['_csrf' => $giaCsrf[1] ?? '', 'action' => 'create_project', 'name' => 'გიას პროექტი'], $gia['cookie']);
preg_match('#/account/projects/([1-9][0-9]*)#', $http('GET', '/account', [], $gia['cookie'])['body'], $giaProject);
$giaPage = $http('GET', '/account/projects/' . ($giaProject[1] ?? '0') . '/playground', [], $gia['cookie']);
preg_match('/name="_csrf" value="([^"]+)"/', $giaPage['body'], $giaRun);
$unverified = $http('POST', '/account/projects/' . ($giaProject[1] ?? '0') . '/playground/run', ['_csrf' => $giaRun[1] ?? '', 'operation' => '/api/v1/brands'], $gia['cookie']);
$check($unverified['status'] === 403, 'unverified email rejected');
$before = $used();
$unsupported = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/specs'], $ana['cookie']);
$check($unsupported['status'] === 422 && $used() === $before, 'unsupported operation rejected without monthly quota');
$url = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'url' => 'http://127.0.0.1/'], $ana['cookie']);
$check($url['status'] === 422, 'arbitrary url rejected');
$method = $http('GET', $run, [], $ana['cookie']);
$check($method['status'] === 405, 'unsupported method rejected');
$badPage = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'page' => '0'], $ana['cookie']);
$check($badPage['status'] === 422 && $used() === $before, 'invalid page rejected without monthly quota');
$badPer = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'per_page' => '101'], $ana['cookie']);
$check($badPer['status'] === 422 && $used() === $before, 'invalid per_page rejected without monthly quota');
$ok = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$payload = json_decode($ok['body'], true);
$check(
    $ok['status'] === 200
    && is_string($payload['body']['data'][0]['slug'] ?? null)
    && ($payload['body']['data'][0]['slug'] ?? '') !== ''
    && ($payload['body']['meta']['total'] ?? 0) >= 1
    && isset($payload['headers']['X-Quota-Limit']),
    'valid brands request succeeds'
);
$check($used() === $before + 1, 'successful request consumes monthly quota');
$models = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands/{slug}/models', 'slug' => 'toyota'], $ana['cookie']);
$modelsBody = json_decode($models['body'], true);
$check($models['status'] === 200 && ($modelsBody['body']['data'][0]['slug'] ?? '') === 'camry', 'models request succeeds');
$generations = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/models/{id}/generations', 'id' => '20'], $ana['cookie']);
$generationsBody = json_decode($generations['body'], true);
$check($generations['status'] === 200 && ($generationsBody['body']['data'][0]['id'] ?? '') === '20', 'generations request succeeds');
$modifications = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/generations/{id}/modifications', 'id' => '20'], $ana['cookie']);
$modificationsBody = json_decode($modifications['body'], true);
$check($modifications['status'] === 200 && ($modificationsBody['body']['data'][0]['id'] ?? '') === '40', 'modifications list succeeds');
$detail = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/modifications/{id}', 'id' => '40'], $ana['cookie']);
$detailBody = json_decode($detail['body'], true);
$check($detail['status'] === 200 && ($detailBody['body']['data']['generation']['id'] ?? '') === '20', 'modification detail succeeds');
$coverage = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/coverage'], $ana['cookie']);
$coverageBody = json_decode($coverage['body'], true);
$check($coverage['status'] === 200 && ($coverageBody['body']['brands_count'] ?? 0) === 1, 'coverage request succeeds');
$missingSlug = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands/{slug}/models'], $ana['cookie']);
$check($missingSlug['status'] === 422, 'missing slug rejected');
$badSlug = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands/{slug}/models', 'slug' => 'bad slug'], $ana['cookie']);
$check($badSlug['status'] === 422, 'invalid slug rejected');
$unknownBrand = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands/{slug}/models', 'slug' => 'no-such'], $ana['cookie']);
$check($unknownBrand['status'] === 404, 'unknown brand rejected');
$badModel = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/models/{id}/generations', 'id' => '0'], $ana['cookie']);
$check($badModel['status'] === 422, 'invalid model id rejected');
$unknownModel = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/models/{id}/generations', 'id' => '999'], $ana['cookie']);
$check($unknownModel['status'] === 404, 'unknown model rejected');
$badGeneration = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/generations/{id}/modifications', 'id' => 'abc'], $ana['cookie']);
$check($badGeneration['status'] === 422, 'invalid generation id rejected');
$usedBeforeSibling = $used();
$sibling = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/generations/{id}/modifications', 'id' => '21'], $ana['cookie']);
$check($sibling['status'] === 404 && $used() === $usedBeforeSibling, 'noncanonical generation id rejected without monthly quota');
$badModification = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/modifications/{id}', 'id' => '0'], $ana['cookie']);
$check($badModification['status'] === 422, 'invalid modification id rejected');
$unknownModification = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/modifications/{id}', 'id' => '999'], $ana['cookie']);
$check($unknownModification['status'] === 404 && $used() === $usedBeforeSibling, 'unknown modification does not consume monthly quota');
$pagedCoverage = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/coverage', 'page' => '1'], $ana['cookie']);
$check($pagedCoverage['status'] === 422, 'coverage rejects pagination');
$pagedDetail = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/modifications/{id}', 'id' => '40', 'per_page' => '10'], $ana['cookie']);
$check($pagedDetail['status'] === 422, 'detail rejects pagination');
$array = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'page' => ['1']], $ana['cookie']);
$check($array['status'] === 422, 'array parameter rejected');
$routesUsed = $db->query('SELECT route FROM platform.daily_usage')->fetchAll(PDO::FETCH_COLUMN);
sort($routesUsed);
$check($routesUsed === ['brands', 'coverage', 'generations', 'models', 'modification', 'modifications'], 'daily usage records canonical routes');
$check(!str_contains($ok['body'], 'carapi_sk_'), 'response has no secret');
$catalog = $http('GET', '/api/v1/brands');
$check($catalog['status'] === 401, 'catalog api still requires a key');
$db->exec('DELETE FROM platform.rate_windows');
$db->exec('UPDATE platform.project_limits SET per_minute = 1 WHERE project_id = ' . (int) $projectId);
$first = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands'], $ana['cookie']);
$second = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands'], $ana['cookie']);
$check($first['status'] === 200 && $second['status'] === 429 && str_contains($second['body'], 'RATE_LIMIT_EXCEEDED'), 'project rate limit enforced');
$db->exec('DELETE FROM platform.rate_windows');
$db->exec('UPDATE platform.project_limits AS limits SET per_minute = 60, monthly_quota = usage.used_count FROM platform.monthly_usage AS usage WHERE limits.project_id = usage.project_id AND limits.project_id = ' . (int) $projectId);
$monthBefore = $used();
$month = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/brands'], $ana['cookie']);
$check($month['status'] === 429 && str_contains($month['body'], 'MONTHLY_QUOTA_EXCEEDED') && $used() === $monthBefore, 'monthly quota enforced');
$db->exec('DELETE FROM platform.rate_windows');
$ipLimited = false;
for ($i = 0; $i < 45; $i++) {
    $hit = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/specs'], $ana['cookie']);
    if ($hit['status'] === 429 && str_contains($hit['body'], 'RATE_LIMIT_EXCEEDED')) {
        $ipLimited = true;
        break;
    }
}
$check($ipLimited, 'ip rate limit enforced');
$specs = $http('POST', $run, ['_csrf' => $token, 'operation' => '/api/v1/specifications'], $ana['cookie']);
$check($specs['status'] === 422 || $specs['status'] === 429, 'specifications stay blocked');
proc_terminate($proc);
proc_close($proc);
$admin->exec('DROP DATABASE IF EXISTS carapi_playground_test WITH (FORCE)');
if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "all passed\n";
