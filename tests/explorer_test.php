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
$admin->exec('DROP DATABASE IF EXISTS carapi_explorer_test WITH (FORCE)');
$admin->exec('CREATE DATABASE carapi_explorer_test');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_explorer_test', 'CARAPI_PLATFORM_ADMIN_USER' => 'postgres', 'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
]);
stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($migrate);
$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_explorer_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_explorer_test TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');
$db->exec('CREATE TABLE public.brands (id bigint PRIMARY KEY, slug text NOT NULL, name text NOT NULL, synonyms jsonb, is_lcv_or_commercial boolean NOT NULL DEFAULT false, logo_url_light text, logo_url_dark text)');
$db->exec('CREATE TABLE public.models (id bigint PRIMARY KEY, brand_id bigint NOT NULL, slug text NOT NULL, name text NOT NULL)');
$db->exec('CREATE TABLE public.model_markets (model_id bigint, market_slug text)');
$db->exec('CREATE TABLE public.generations (id bigint PRIMARY KEY, model_id bigint NOT NULL, slug text, name text, year_start integer, year_end integer, body_type text, chassis_code text, steering_wheel text, photo_urls jsonb)');
$db->exec('CREATE TABLE public.modifications (id bigint PRIMARY KEY, generation_id bigint, model_id bigint NOT NULL, name text NOT NULL, year_start integer, year_end integer, transmission text, engine_volume_cc integer, drive_type text, body_type text)');
$db->exec('CREATE TABLE public.photos (id bigint PRIMARY KEY)');
$insertBrand = $db->prepare('INSERT INTO public.brands (id, slug, name, synonyms) VALUES (:id, :slug, :name, \'[]\'::jsonb)');
for ($i = 1; $i <= 51; $i++) {
    $n = sprintf('%03d', $i);
    $insertBrand->execute(['id' => $i, 'slug' => 'brand-' . $n, 'name' => 'Brand ' . $n]);
}
$insertBrand->execute(['id' => 52, 'slug' => 'xss-brand', 'name' => '<script>alert(1)</script>']);
$insertModel = $db->prepare('INSERT INTO public.models (id, brand_id, slug, name) VALUES (:id, :brand, :slug, :name)');
$insertModel->execute(['id' => 1000, 'brand' => 1, 'slug' => 'model-xss', 'name' => '<script>alert(1)</script>']);
for ($i = 1; $i <= 51; $i++) {
    $n = sprintf('%03d', $i);
    $insertModel->execute(['id' => 1000 + $i, 'brand' => 1, 'slug' => 'model-' . $n, 'name' => 'Model ' . $n]);
}
$insertGeneration = $db->prepare('INSERT INTO public.generations (id, model_id, slug, name, year_start, year_end, body_type, chassis_code) VALUES (:id, :model, :slug, :name, :start, :end, :body, :chassis)');
$insertGeneration->execute(['id' => 20, 'model' => 1001, 'slug' => 'xv70', 'name' => 'XV70', 'start' => 2000, 'end' => 2005, 'body' => 'sedan', 'chassis' => 'XV70']);
$insertGeneration->execute(['id' => 21, 'model' => 1001, 'slug' => 'xv70-b', 'name' => 'XV70', 'start' => 2001, 'end' => 2006, 'body' => 'sedan', 'chassis' => 'xv70']);
for ($i = 1; $i <= 50; $i++) {
    $n = sprintf('%03d', $i);
    $insertGeneration->execute(['id' => 3000 + $i, 'model' => 1001, 'slug' => 'gen-' . $n, 'name' => 'Gen ' . $n, 'start' => 2000 + $i, 'end' => 2000 + $i, 'body' => 'sedan', 'chassis' => 'C' . $n]);
}
$insertModification = $db->prepare('INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc, drive_type, body_type) VALUES (:id, :generation, :model, :name, :start, :end, :transmission, :engine, :drive, :body)');
$insertModification->execute(['id' => 40, 'generation' => 21, 'model' => 1001, 'name' => '2.5', 'start' => 1990, 'end' => 1995, 'transmission' => 'automatic', 'engine' => 2500, 'drive' => 'fwd', 'body' => 'sedan']);
for ($i = 1; $i <= 50; $i++) {
    $n = sprintf('%03d', $i);
    $insertModification->execute(['id' => 4000 + $i, 'generation' => 20, 'model' => 1001, 'name' => 'Mod ' . $n, 'start' => 2000 + $i, 'end' => 2000 + $i, 'transmission' => 'manual', 'engine' => 1600, 'drive' => 'fwd', 'body' => 'sedan']);
}
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
$env = [
    'CARAPI_PLATFORM_ENABLED' => '1', 'CARAPI_PLATFORM_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_PORT' => '5432',
    'CARAPI_PLATFORM_DATABASE' => 'carapi_explorer_test', 'CARAPI_PLATFORM_USER' => 'carapi_platform_app', 'CARAPI_PLATFORM_PASSWORD' => $secret,
    'CARAPI_PLATFORM_SESSION_PATH' => '/tmp/carapi-explorer-sessions',
    'CARAPI_CATALOG_ENABLED' => '1', 'CARAPI_CATALOG_HOST' => '127.0.0.1', 'CARAPI_CATALOG_PORT' => '5432',
    'CARAPI_CATALOG_DATABASE' => 'carapi_explorer_test', 'CARAPI_CATALOG_USER' => 'postgres', 'CARAPI_CATALOG_PASSWORD' => '',
    'CARAPI_CATALOG_STATEMENT_TIMEOUT_MS' => '10000', 'CARAPI_IP_PER_MINUTE' => '80',
    'CARAPI_MODIFICATIONS_ENABLED' => '1', 'CARAPI_MODIFICATION_DETAILS_ENABLED' => '1',
];
$port = 18098;
$base = 'http://127.0.0.1:' . $port;
$proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'], [1 => ['file', '/tmp/explorer.log', 'w'], 2 => ['file', '/tmp/explorer.err', 'w']], $u, $root, $env);
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
$guest = $http('GET', '/account/projects/1/explorer');
$check($guest['status'] === 302 && str_contains($guest['header'], 'Location: /login'), 'guest redirected to login');
$ana = $login('ana@example.com');
$bia = $login('bia@example.com');
$account = $http('GET', '/account', [], $ana['cookie']);
preg_match('/name="_csrf" value="([^"]+)"/', $account['body'], $csrf);
$http('POST', '/account', ['_csrf' => $csrf[1] ?? '', 'action' => 'create_project', 'name' => 'ანას პროექტი'], $ana['cookie']);
$list = $http('GET', '/account', [], $ana['cookie']);
preg_match('#/account/projects/([1-9][0-9]*)#', $list['body'], $id);
$projectId = $id[1] ?? '0';
$path = '/account/projects/' . $projectId . '/explorer';
$owner = $http('GET', $path, [], $ana['cookie']);
$check($owner['status'] === 200 && str_contains($owner['body'], 'ავტომობილების კატალოგი'), 'owner can open explorer');
$check(str_contains($owner['body'], 'action="/account/projects/' . $projectId . '/playground/run"'), 'brands reuse playground execution');
$check(str_contains($owner['body'], 'name="_csrf"'), 'csrf field present');
preg_match('/name="_csrf" value="([^"]+)"/', $owner['body'], $runCsrf);
$token = $runCsrf[1] ?? '';
$check(str_contains($owner['body'], 'id="explorer-brand" name="brand" disabled') && str_contains($owner['body'], 'აირჩიეთ ბრენდი') && str_contains($owner['body'], 'for="explorer-brand"') && str_contains($owner['body'], 'aria-describedby="explorer-brand-help"'), 'brand selector starts disabled');
foreach (['model' => 'მოდელი', 'generation' => 'თაობა', 'modification' => 'მოდიფიკაცია'] as $name => $label) {
    $check(str_contains($owner['body'], 'id="explorer-' . $name . '" name="' . $name . '" disabled') && str_contains($owner['body'], 'for="explorer-' . $name . '"') && str_contains($owner['body'], 'aria-describedby="explorer-' . $name . '-help"'), $name . ' stays disabled');
}
$check(str_contains($owner['body'], 'მეტი ბრენდის ჩატვირთვა') && str_contains($owner['body'], 'per_page", "50"') && str_contains($owner['body'], 'operation", "/api/v1/brands"'), 'load more uses the first page size');
$check(str_contains($owner['body'], 'option.textContent = row.name') && str_contains($owner['body'], 'option.value = row.slug') && str_contains($owner['body'], 'seen[row.slug]'), 'slug is kept and names are text');
$check(str_contains($owner['body'], 'data.length === 0') && str_contains($owner['body'], 'loaded >= state.total') && str_contains($owner['body'], 'state.page >= 1000000'), 'pagination stops from metadata');
$check(str_contains($owner['body'], 'ბრენდები ვერ მოიძებნა.') && str_contains($owner['body'], 'ქსელის შეცდომა.') && str_contains($owner['body'], '429: "მოთხოვნების ლიმიტი მიღწეულია."'), 'empty and error messages present');
$check(str_contains($owner['body'], 'Explorer-ის წარმატებული მოთხოვნები ითვლება თქვენი პროექტის API კვოტაში.') && str_contains($owner['body'], 'id="explorer-modification" name="modification" disabled') && str_contains($owner['body'], 'ჯერ აირჩიეთ ბრენდი.') && str_contains($owner['body'], 'ჯერ აირჩიეთ მოდელი.') && str_contains($owner['body'], 'ჯერ აირჩიეთ თაობა.') && str_contains($owner['body'], 'aria-live="polite"') && str_contains($owner['body'], 'id="explorer-detail-title"'), 'quota notice and modification selector present');
$check(str_contains($owner['body'], '/api/v1/generations/{id}/modifications') && str_contains($owner['body'], 'append("id", generationId)') && str_contains($owner['body'], '/api/v1/modifications/{id}') && str_contains($owner['body'], 'append("id", modificationId)') && str_contains($owner['body'], 'seenModifications[row.id]'), 'modifications use the public generation and modification ids');
$check(str_contains($owner['body'], 'generation.value !== generationId') && str_contains($owner['body'], 'modification.value !== modificationId') && str_contains($owner['body'], 'resetModifications()') && str_contains($owner['body'], 'მეტი მოდიფიკაციის ჩატვირთვა') && str_contains($owner['body'], 'ამ თაობას მოდიფიკაცია არ აქვს.'), 'modification paging and stale responses are handled');
$check(!str_contains($owner['body'], 'modification_specs') && !str_contains($owner['body'], 'modification_equipment'), 'specifications stay out of the explorer');
$check(str_contains($owner['body'], '/api/v1/models/{id}/generations') && str_contains($owner['body'], 'append("id", modelId)') && str_contains($owner['body'], 'option.value = row.id') && str_contains($owner['body'], 'generationLabel(row)') && str_contains($owner['body'], 'seenGenerations[row.id]'), 'generations keep the public id from the response');
$check(str_contains($owner['body'], 'model.value !== modelId') && str_contains($owner['body'], 'generationAbort') && str_contains($owner['body'], 'resetGenerations()'), 'stale generation responses are ignored');
$check(str_contains($owner['body'], 'მეტი თაობის ჩატვირთვა') && str_contains($owner['body'], 'ამ მოდელს თაობა არ აქვს.'), 'generation paging and empty state present');
$check(str_contains($owner['body'], '/api/v1/brands/{slug}/models') && str_contains($owner['body'], 'append("slug", slug)') && str_contains($owner['body'], 'option.value = row.id') && str_contains($owner['body'], 'seenModels[row.id]'), 'models request keeps the public id');
$check(str_contains($owner['body'], 'brand.value !== slug') && str_contains($owner['body'], 'AbortController') && str_contains($owner['body'], 'resetModels()'), 'stale model responses are ignored');
$check(str_contains($owner['body'], 'მეტი მოდელის ჩატვირთვა') && str_contains($owner['body'], 'ამ ბრენდს მოდელი არ აქვს.') && str_contains($owner['body'], 'თავიდან ცდა'), 'model paging and empty state present');
$scriptAt = strpos($owner['body'], 'var state = {brand:');
$script = $scriptAt === false ? '' : substr($owner['body'], $scriptAt, 40000);
$check($script !== '' && !str_contains($script, 'innerHTML') && !str_contains($script, 'localStorage') && !str_contains($script, 'sessionStorage'), 'response is not written as html or stored');
$check(!str_contains($owner['body'], 'carapi_sk_') && !str_contains($owner['body'], 'Authorization') && !str_contains($owner['body'], '<script>alert(1)</script>'), 'no api secret or injected brand');
$other = $http('GET', $path, [], $bia['cookie']);
$check($other['status'] === 404, 'other user gets 404');
$missing = $http('GET', '/account/projects/999999/explorer', [], $ana['cookie']);
$check($missing['status'] === 404, 'missing project gets 404');
$post = $http('POST', $path, [], $ana['cookie']);
$check($post['status'] === 405, 'explorer post is not an execution route');
$runPath = '/account/projects/' . $projectId . '/playground/run';
$guestRun = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'page' => '1', 'per_page' => '50']);
$check($guestRun['status'] === 401, 'session expiration rejected');
$noCsrf = $http('POST', $runPath, ['operation' => '/api/v1/brands', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$check($noCsrf['status'] === 419, 'csrf enforced');
$before = $used();
$bad = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/specs', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$check($bad['status'] === 422 && $used() === $before, 'invalid operation rejected without monthly quota');
$first = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$firstBody = json_decode($first['body'], true);
$firstRows = $firstBody['body']['data'] ?? [];
$firstSlugs = array_column(is_array($firstRows) ? $firstRows : [], 'slug');
$check(
    $first['status'] === 200
    && ($firstBody['body']['meta']['total'] ?? 0) === 52
    && count($firstSlugs) === 50
    && ($firstRows[0]['slug'] ?? '') === 'xss-brand'
    && ($firstRows[0]['name'] ?? '') === '<script>alert(1)</script>'
    && in_array('brand-001', $firstSlugs, true),
    'first brand page succeeds'
);
$check($used() === $before + 1, 'successful brand request consumes monthly quota');
$second = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'page' => '2', 'per_page' => '50'], $ana['cookie']);
$secondBody = json_decode($second['body'], true);
$secondSlugs = array_column($secondBody['body']['data'] ?? [], 'slug');
$check($second['status'] === 200 && $secondSlugs === ['brand-050', 'brand-051'] && array_intersect($firstSlugs, $secondSlugs) === [], 'second page has no duplicates');
$emptyPage = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'page' => '3', 'per_page' => '50'], $ana['cookie']);
$emptyBody = json_decode($emptyPage['body'], true);
$check($emptyPage['status'] === 200 && ($emptyBody['body']['data'] ?? null) === [] && ($emptyBody['body']['meta']['total'] ?? 0) === 52, 'empty page is explicit');
$playground = $http('GET', '/account/projects/' . $projectId . '/playground', [], $ana['cookie']);
$check($playground['status'] === 200 && str_contains($playground['body'], 'API Playground') && str_contains($playground['body'], 'მოთხოვნის გაგზავნა'), 'playground still works');
$playgroundRun = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'page' => '1', 'per_page' => '1'], $ana['cookie']);
$playgroundBody = json_decode($playgroundRun['body'], true);
$check($playgroundRun['status'] === 200 && ($playgroundBody['body']['data'][0]['slug'] ?? '') === 'xss-brand', 'playground brands request still succeeds');
$models = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands/{slug}/models', 'slug' => 'brand-001', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$modelsBody = json_decode($models['body'], true);
$modelRows = $modelsBody['body']['data'] ?? [];
$modelIds = array_column(is_array($modelRows) ? $modelRows : [], 'id');
$check(
    $models['status'] === 200
    && ($modelsBody['body']['meta']['total'] ?? 0) === 52
    && count($modelIds) === 50
    && ($modelRows[0]['id'] ?? '') === '1000'
    && ($modelRows[0]['name'] ?? '') === '<script>alert(1)</script>'
    && ($modelRows[0]['brand_id'] ?? '') === '1',
    'first model page succeeds'
);
$modelPage = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands/{slug}/models', 'slug' => 'brand-001', 'page' => '2', 'per_page' => '50'], $ana['cookie']);
$modelPageBody = json_decode($modelPage['body'], true);
$modelPageIds = array_column($modelPageBody['body']['data'] ?? [], 'id');
$check($modelPage['status'] === 200 && $modelPageIds === ['1050', '1051'] && array_intersect($modelIds, $modelPageIds) === [], 'second model page has no duplicates');
$emptyModelsBefore = $used();
$emptyModels = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands/{slug}/models', 'slug' => 'brand-002', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$emptyModelsBody = json_decode($emptyModels['body'], true);
$check($emptyModels['status'] === 200 && ($emptyModelsBody['body']['data'] ?? null) === [] && ($emptyModelsBody['body']['meta']['total'] ?? -1) === 0 && $used() === $emptyModelsBefore + 1, 'empty model list succeeds');
$unknownBefore = $used();
$unknownBrand = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands/{slug}/models', 'slug' => 'no-such', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$missingSlug = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands/{slug}/models', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$check($unknownBrand['status'] === 404 && $missingSlug['status'] === 422 && $used() === $unknownBefore, 'unknown brand and invalid model request skip monthly quota');
$generations = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/models/{id}/generations', 'id' => '1001', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$generationsBody = json_decode($generations['body'], true);
$generationRows = $generationsBody['body']['data'] ?? [];
$generationIds = array_column(is_array($generationRows) ? $generationRows : [], 'id');
$check(
    $generations['status'] === 200
    && ($generationsBody['body']['meta']['total'] ?? 0) === 51
    && count($generationIds) === 50
    && ($generationRows[0]['id'] ?? '') === '20'
    && str_contains((string) ($generationRows[0]['display_label'] ?? ''), 'XV70')
    && !in_array('21', $generationIds, true),
    'first generation page uses the public id'
);
$generationPage = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/models/{id}/generations', 'id' => '1001', 'page' => '2', 'per_page' => '50'], $ana['cookie']);
$generationPageBody = json_decode($generationPage['body'], true);
$generationPageIds = array_column($generationPageBody['body']['data'] ?? [], 'id');
$check($generationPage['status'] === 200 && $generationPageIds === ['3050'] && !in_array('21', $generationPageIds, true) && array_intersect($generationIds, $generationPageIds) === [], 'second generation page has no duplicates or internal ids');
$emptyGenerationsBefore = $used();
$emptyGenerations = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/models/{id}/generations', 'id' => '1002', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$emptyGenerationsBody = json_decode($emptyGenerations['body'], true);
$check($emptyGenerations['status'] === 200 && ($emptyGenerationsBody['body']['data'] ?? null) === [] && ($emptyGenerationsBody['body']['meta']['total'] ?? -1) === 0 && $used() === $emptyGenerationsBefore + 1, 'empty generation list succeeds');
$unknownGenerationBefore = $used();
$unknownModel = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/models/{id}/generations', 'id' => '999', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$badModelId = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/models/{id}/generations', 'id' => '0', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$check($unknownModel['status'] === 404 && $badModelId['status'] === 422 && $used() === $unknownGenerationBefore, 'unknown and invalid model skip monthly quota');
$modifications = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/generations/{id}/modifications', 'id' => '20', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$modificationsBody = json_decode($modifications['body'], true);
$modificationRows = $modificationsBody['body']['data'] ?? [];
$modificationIds = array_column(is_array($modificationRows) ? $modificationRows : [], 'id');
$check(
    $modifications['status'] === 200
    && ($modificationsBody['body']['meta']['total'] ?? 0) === 51
    && ($modificationRows[0]['id'] ?? '') === '40'
    && ($modificationRows[0]['name'] ?? '') === '2.5'
    && ($modificationRows[0]['generation_id'] ?? '') === '20'
    && !in_array('21', $modificationIds, true),
    'modifications load from the public generation id'
);
$modificationPage = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/generations/{id}/modifications', 'id' => '20', 'page' => '2', 'per_page' => '50'], $ana['cookie']);
$modificationPageIds = array_column(json_decode($modificationPage['body'], true)['body']['data'] ?? [], 'id');
$check($modificationPage['status'] === 200 && $modificationPageIds === ['4050'] && array_intersect($modificationIds, $modificationPageIds) === [], 'second modification page has no duplicates');
$emptyModificationsBefore = $used();
$emptyModifications = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/generations/{id}/modifications', 'id' => '3001', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$emptyModificationsBody = json_decode($emptyModifications['body'], true);
$check($emptyModifications['status'] === 200 && ($emptyModificationsBody['body']['data'] ?? null) === [] && $used() === $emptyModificationsBefore + 1, 'empty modification list succeeds');
$unknownModificationBefore = $used();
$siblingGeneration = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/generations/{id}/modifications', 'id' => '21', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$unknownGenerationForMods = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/generations/{id}/modifications', 'id' => '999', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$badGenerationId = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/generations/{id}/modifications', 'id' => '0', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$check($siblingGeneration['status'] === 404 && $unknownGenerationForMods['status'] === 404 && $badGenerationId['status'] === 422 && $used() === $unknownModificationBefore, 'internal generation id and invalid id skip monthly quota');
$detail = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/modifications/{id}', 'id' => '40'], $ana['cookie']);
$detailBody = json_decode($detail['body'], true);
$detailItem = $detailBody['body']['data'] ?? [];
$check(
    $detail['status'] === 200
    && ($detailItem['id'] ?? '') === '40'
    && ($detailItem['name'] ?? '') === '2.5'
    && ($detailItem['generation']['id'] ?? '') === '20'
    && !array_key_exists('specs', $detailItem)
    && !str_contains($detail['body'], 'modification_specs'),
    'modification detail uses the public generation id'
);
$detailBefore = $used();
$missingDetail = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/modifications/{id}', 'id' => '999'], $ana['cookie']);
$badDetail = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/modifications/{id}', 'id' => '0'], $ana['cookie']);
$check($missingDetail['status'] === 404 && $badDetail['status'] === 422 && $used() === $detailBefore, 'failed detail requests skip monthly quota');
$routesUsed = $db->query('SELECT DISTINCT route FROM platform.daily_usage')->fetchAll(PDO::FETCH_COLUMN);
sort($routesUsed);
$check($routesUsed === ['brands', 'generations', 'models', 'modification', 'modifications'], 'daily usage records explorer routes');
$catalog = $http('GET', '/api/v1/brands');
$check($catalog['status'] === 401, 'catalog api still requires a key');
$db->exec('DELETE FROM platform.rate_windows');
$db->exec('UPDATE platform.project_limits AS limits SET monthly_quota = usage.used_count FROM platform.monthly_usage AS usage WHERE limits.project_id = usage.project_id AND limits.project_id = ' . (int) $projectId);
$monthBefore = $used();
$month = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$check($month['status'] === 429 && str_contains($month['body'], 'MONTHLY_QUOTA_EXCEEDED') && $used() === $monthBefore, 'monthly quota enforced');
$db->exec('DELETE FROM platform.rate_windows');
$db->exec('UPDATE platform.project_limits SET per_minute = 60, monthly_quota = 10000 WHERE project_id = ' . (int) $projectId);
$db->exec('UPDATE platform.project_limits SET per_minute = 1 WHERE project_id = ' . (int) $projectId);
$rateFirst = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$rateSecond = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/brands', 'page' => '1', 'per_page' => '50'], $ana['cookie']);
$check($rateFirst['status'] === 200 && $rateSecond['status'] === 429 && str_contains($rateSecond['body'], 'RATE_LIMIT_EXCEEDED'), 'project rate limit enforced');
$db->exec('DELETE FROM platform.rate_windows');
$ipLimited = false;
for ($i = 0; $i < 90; $i++) {
    $hit = $http('POST', $runPath, ['_csrf' => $token, 'operation' => '/api/v1/specs'], $ana['cookie']);
    if ($hit['status'] === 429 && str_contains($hit['body'], 'RATE_LIMIT_EXCEEDED')) {
        $ipLimited = true;
        break;
    }
}
$check($ipLimited, 'ip rate limit enforced');
$js = proc_open(['node', $root . '/tests/explorer_js_test.mjs'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $jsPipes, $root);
$jsOut = stream_get_contents($jsPipes[1]);
$jsErr = stream_get_contents($jsPipes[2]);
fclose($jsPipes[1]);
fclose($jsPipes[2]);
$jsCode = proc_close($js);
echo $jsOut;
if ($jsErr !== '') {
    fwrite(STDERR, $jsErr);
}
$check($jsCode === 0 && str_contains($jsOut, 'all passed'), 'explorer javascript race tests');
proc_terminate($proc);
proc_close($proc);
$admin->exec('DROP DATABASE IF EXISTS carapi_explorer_test WITH (FORCE)');
if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "all passed\n";
