<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sqlite = $root . '/data/carapi.sqlite';
$sqliteBefore = is_file($sqlite) ? filemtime($sqlite) . ':' . filesize($sqlite) : 'absent';

$env = [
    'CARAPI_CATALOG_ENABLED' => '1',
    'CARAPI_CATALOG_HOST' => getenv('CARAPI_CATALOG_HOST') ?: '127.0.0.1',
    'CARAPI_CATALOG_PORT' => getenv('CARAPI_CATALOG_PORT') ?: '5432',
    'CARAPI_CATALOG_DATABASE' => getenv('CARAPI_CATALOG_DATABASE') ?: 'carbase',
    'CARAPI_CATALOG_USER' => getenv('CARAPI_CATALOG_USER') ?: '',
    'CARAPI_CATALOG_PASSWORD' => getenv('CARAPI_CATALOG_PASSWORD') === false ? '' : (string) getenv('CARAPI_CATALOG_PASSWORD'),
    'CARAPI_CATALOG_CONNECT_TIMEOUT' => '3',
    'CARAPI_CATALOG_STATEMENT_TIMEOUT_MS' => '3000',
];
if ($env['CARAPI_CATALOG_USER'] === '') {
    fwrite(STDERR, "CARAPI_CATALOG_USER is empty\n");
    exit(1);
}

$platformSecret = '';
foreach (file('/www/server/carapi/platform.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'CARAPI_PLATFORM_PASSWORD=')) {
        $platformSecret = substr($line, 26);
    }
}
require $root . '/vendor/autoload.php';
$issued = \Carapi\Platform\ApiKeys::issue();
$admin = new PDO('pgsql:host=127.0.0.1;dbname=postgres', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('DROP DATABASE IF EXISTS carapi_catalog_auth_test WITH (FORCE)');
$admin->exec('CREATE DATABASE carapi_catalog_auth_test');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $mpipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_catalog_auth_test',
    'CARAPI_PLATFORM_ADMIN_USER' => 'postgres',
    'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
]);
stream_get_contents($mpipes[1]);
fclose($mpipes[1]);
fclose($mpipes[2]);
proc_close($migrate);
$platform = new PDO('pgsql:host=127.0.0.1;dbname=carapi_catalog_auth_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$platform->exec('GRANT CONNECT ON DATABASE carapi_catalog_auth_test TO carapi_platform_app');
$platform->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$platform->exec('GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA platform TO carapi_platform_app');
$platform->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');
$platform->exec("INSERT INTO platform.users (first_name, last_name, email, password_hash, email_verified_at) VALUES ('ტესტ', 'მომხმარე', 'catalog@example.com', 'x', now())");
$userId = (int) $platform->query('SELECT id FROM platform.users LIMIT 1')->fetchColumn();
$platform->prepare('INSERT INTO platform.projects (user_id, name) VALUES (:user_id, :name)')->execute(['user_id' => $userId, 'name' => 'კატალოგი']);
$projectId = (int) $platform->query('SELECT id FROM platform.projects LIMIT 1')->fetchColumn();
$platform->prepare('INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota) VALUES (:id, 1000, 100000)')->execute(['id' => $projectId]);
$platform->prepare('INSERT INTO platform.api_keys (project_id, name, key_selector, secret_hash) VALUES (:project_id, :name, :selector, :hash)')
    ->execute(['project_id' => $projectId, 'name' => 'სერვერი', 'selector' => $issued['selector'], 'hash' => $issued['hash']]);
$env['CARAPI_PLATFORM_ENABLED'] = '1';
$env['CARAPI_PLATFORM_HOST'] = '127.0.0.1';
$env['CARAPI_PLATFORM_PORT'] = '5432';
$env['CARAPI_PLATFORM_DATABASE'] = 'carapi_catalog_auth_test';
$env['CARAPI_PLATFORM_USER'] = 'carapi_platform_app';
$env['CARAPI_PLATFORM_PASSWORD'] = $platformSecret;
$bearer = 'Authorization: Bearer ' . $issued['token'];

$php = PHP_BINARY;
$port = 18081;
$cmd = escapeshellarg($php) . ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($root . '/public') . ' ' . escapeshellarg($root . '/public/index.php');
$procEnv = $env;
$descriptors = [0 => ['pipe', 'r'], 1 => ['file', '/tmp/catalog-api-test.log', 'w'], 2 => ['file', '/tmp/catalog-api-test.err', 'w']];
$proc = proc_open($cmd, $descriptors, $pipes, $root, $procEnv);
if (!is_resource($proc)) {
    fwrite(STDERR, "server failed to start\n");
    exit(1);
}

$base = 'http://127.0.0.1:' . $port;
$ready = false;
for ($i = 0; $i < 30; $i++) {
    $probe = @file_get_contents($base . '/api/v1/coverage', false, stream_context_create(['http' => ['header' => $bearer, 'ignore_errors' => true]]));
    if ($probe !== false) {
        $ready = true;
        break;
    }
    usleep(100000);
}
if (!$ready) {
    proc_terminate($proc);
    fwrite(STDERR, "server not ready\n");
    exit(1);
}

$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    if (!$ok) {
        $failures[] = $name;
        fwrite(STDERR, "FAIL $name\n");
    } else {
        fwrite(STDOUT, "ok $name\n");
    }
};

$get = static function (string $path, array $headers = []) use ($base, $bearer): array {
    array_unshift($headers, $bearer);
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'ignore_errors' => true,
        'timeout' => 20,
        'header' => implode("\r\n", $headers),
    ]]);
    $body = file_get_contents($base . $path, false, $ctx);
    $status = 0;
    $type = '';
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
            $status = (int) $m[1];
        }
        if (stripos($header, 'Content-Type:') === 0) {
            $type = trim(substr($header, 13));
        }
    }
    $json = json_decode((string) $body, true);

    return ['status' => $status, 'type' => $type, 'body' => (string) $body, 'json' => $json];
};

$post = static function (string $path) use ($base): array {
    $ctx = stream_context_create(['http' => [
        'method' => 'POST',
        'ignore_errors' => true,
        'timeout' => 10,
        'content' => '',
    ]]);
    file_get_contents($base . $path, false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
            $status = (int) $m[1];
        }
    }

    return ['status' => $status];
};

require $root . '/vendor/autoload.php';
$pdo = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', $env['CARAPI_CATALOG_HOST'], $env['CARAPI_CATALOG_PORT'], $env['CARAPI_CATALOG_DATABASE']),
    $env['CARAPI_CATALOG_USER'],
    $env['CARAPI_CATALOG_PASSWORD'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);
$pdo->exec('SET default_transaction_read_only = on');

$sqlCount = static function (string $sql, array $params = []) use ($pdo): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
};

$coverage = $get('/api/v1/coverage');
$check($coverage['status'] === 200 && str_contains($coverage['type'], 'application/json'), 'coverage status');
$c = $coverage['json'];
$check(is_array($c) && ($c['brands_count'] ?? null) === $sqlCount('SELECT count(*) FROM public.brands'), 'coverage brands');
$check(($c['models_count'] ?? null) === $sqlCount('SELECT count(*) FROM public.models'), 'coverage models');
$check(($c['generations_count'] ?? null) === $sqlCount(<<<'SQL'
    SELECT count(*) FROM (
        SELECT 1 FROM public.generations g
        GROUP BY g.model_id, CASE
            WHEN nullif(btrim(g.chassis_code), '') IS NOT NULL
            THEN 'c:' || upper(btrim(g.chassis_code))
            ELSE 'n:' || lower(btrim(coalesce(g.name, '')))
        END
    ) grouped
SQL), 'coverage generations');
$check(($c['models_with_generations_count'] ?? null) === $sqlCount('SELECT count(DISTINCT model_id) FROM public.generations'), 'coverage with generations');
$check(($c['models_without_generations_count'] ?? null) === $sqlCount("SELECT count(*) FROM public.models m WHERE NOT EXISTS (SELECT 1 FROM public.generations g WHERE g.model_id = m.id)"), 'coverage without generations');
$check(($c['modifications_count'] ?? null) === $sqlCount('SELECT count(*) FROM public.modifications'), 'coverage modifications');
$check(($c['photos_count'] ?? null) === $sqlCount('SELECT count(*) FROM public.photos'), 'coverage photos');
$check(($c['generations_with_photo_urls_count'] ?? null) === $sqlCount("SELECT count(*) FROM public.generations WHERE jsonb_typeof(photo_urls) = 'array' AND jsonb_array_length(photo_urls) > 0"), 'coverage photo urls');
$check(is_string($c['measured_at'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $c['measured_at']) === 1, 'coverage measured_at');
$check(($c['photos_count'] ?? 0) !== ($c['generations_with_photo_urls_count'] ?? 0), 'photo counters differ');

$all = [];
$page = 1;
$total = null;
do {
    $res = $get('/api/v1/brands?per_page=100&page=' . $page);
    $check($res['status'] === 200, "brands page $page status");
    $total = $res['json']['meta']['total'] ?? null;
    $rows = $res['json']['data'] ?? [];
    $all = array_merge($all, $rows);
    $page++;
} while (count($all) < (int) $total && $page < 20);
$check(count($all) === (int) $total && $total === $c['brands_count'], 'brands pages sum');
$ordered = true;
$prev = null;
foreach ($all as $row) {
    $key = [$row['name'] ?? '', $row['id'] ?? ''];
    if ($prev !== null && $prev > $key) {
        $ordered = false;
    }
    $prev = $key;
    if (array_key_exists('source_url', $row) || array_key_exists('drom_firm_id', $row)) {
        $ordered = false;
    }
}
$check($ordered, 'brands order and hidden fields');
$check(array_key_exists('logo_url_light', $all[0]) && $all[0]['logo_url_light'] === null && $all[0]['logo_url_dark'] === null, 'empty logo is null');
$check(is_string($all[0]['id'] ?? null) && is_array($all[0]['synonyms'] ?? null), 'brand id string and synonyms array');

$again = $get('/api/v1/brands?per_page=100&page=1');
$check($again['body'] === $get('/api/v1/brands?per_page=100&page=1')['body'], 'brands page stable');

$brand = $pdo->query("SELECT slug FROM public.brands WHERE EXISTS (SELECT 1 FROM public.models m WHERE m.brand_id = brands.id) ORDER BY name, id LIMIT 1")->fetch();
$slug = (string) $brand['slug'];
$models = [];
$page = 1;
do {
    $res = $get('/api/v1/brands/' . rawurlencode($slug) . '/models?per_page=100&page=' . $page);
    $check($res['status'] === 200, "models page $page");
    $mtotal = (int) ($res['json']['meta']['total'] ?? -1);
    $models = array_merge($models, $res['json']['data'] ?? []);
    $page++;
} while (count($models) < $mtotal && $page < 50);
$dbModels = $sqlCount('SELECT count(*) FROM public.models m JOIN public.brands b ON b.id = m.brand_id WHERE b.slug = :slug', ['slug' => $slug]);
$check(count($models) === $dbModels, 'models belong to brand count');
$brandIds = array_unique(array_column($models, 'brand_id'));
$check(count($brandIds) === 1, 'models single brand_id');

$with = null;
$without = null;
foreach ($models as $model) {
    if ($with === null && ($model['has_generations'] ?? false) === true) {
        $with = $model;
    }
    if ($without === null && ($model['has_generations'] ?? true) === false) {
        $without = $model;
    }
}
if ($without === null) {
    $without = $pdo->query('SELECT id::text AS id FROM public.models m WHERE NOT EXISTS (SELECT 1 FROM public.generations g WHERE g.model_id = m.id) ORDER BY id LIMIT 1')->fetch();
}
$emptyGen = $get('/api/v1/models/' . $without['id'] . '/generations');
$check($emptyGen['status'] === 200 && ($emptyGen['json']['data'] ?? null) === [] && ($emptyGen['json']['meta']['total'] ?? null) === 0, 'model without generations');

$gens = [];
$page = 1;
do {
    $res = $get('/api/v1/models/' . $with['id'] . '/generations?per_page=100&page=' . $page);
    $check($res['status'] === 200, "generations page $page");
    $gtotal = (int) ($res['json']['meta']['total'] ?? -1);
    $gens = array_merge($gens, $res['json']['data'] ?? []);
    $page++;
} while (count($gens) < $gtotal && $page < 20);
$check($gens !== [] && count($gens) === $gtotal, 'generations count');
$sameModel = true;
$labels = [];
foreach ($gens as $gen) {
    if (($gen['model_id'] ?? '') !== (string) $with['id']) {
        $sameModel = false;
    }
    $span = null;
    if (isset($gen['year_start']) && is_int($gen['year_start'])) {
        $span = (string) $gen['year_start'] . '–';
        if (isset($gen['year_end']) && is_int($gen['year_end'])) {
            $span .= (string) $gen['year_end'];
        }
    }
    $parts = array_values(array_filter([
        $gen['name'] ?? null,
        $span,
    ], static fn ($v) => is_string($v) && $v !== ''));
    $labelBase = implode(' · ', $parts);
    $labels[$labelBase][] = $gen;
    if (!array_key_exists('market', $gen) || ($gen['market'] !== null && trim((string) $gen['market']) === '')) {
        $sameModel = false;
    }
    foreach (['source_url', 'drom_generation_id', 'photo_urls'] as $hidden) {
        if (array_key_exists($hidden, $gen)) {
            $sameModel = false;
        }
    }
}
$check($sameModel, 'generations scoped and market cleaned');
$labelOk = true;
foreach ($labels as $label => $group) {
    foreach ($group as $gen) {
        $expected = count($group) > 1 ? ($label === '' ? (string) $gen['id'] : $label . ' · ' . $gen['id']) : $label;
        if ($expected === '') {
            $expected = (string) $gen['id'];
        }
        if (($gen['display_label'] ?? '') !== $expected) {
            $labelOk = false;
        }
    }
}
$check($labelOk, 'display_label');

$check($get('/api/v1/brands/' . rawurlencode("no-such-' OR 1=1") . '/models')['status'] === 404, 'injection slug 404');
$check($get('/api/v1/models/999999999999/generations')['status'] === 404, 'unknown model 404');
$check($get('/api/v1/brands/___missing___/models')['status'] === 404, 'unknown brand 404');
$check($get('/api/v1/brands?per_page=0')['status'] === 422, 'per_page 0');
$check($get('/api/v1/brands?per_page=101')['status'] === 422, 'per_page 101');
$check($get('/api/v1/brands?page=0')['status'] === 422, 'page 0');
$check($get('/api/v1/models/abc/generations')['status'] === 422, 'bad model id');
$check($post('/api/v1/brands')['status'] === 401, 'post without key is 401');
$check($get('/api/v1/brands?page=1', [])['status'] === 200, 'bearer still allows get');
$check((is_file($sqlite) ? filemtime($sqlite) . ':' . filesize($sqlite) : 'absent') === $sqliteBefore, 'sqlite not created');

$included = shell_exec(escapeshellarg($php) . ' -r ' . escapeshellarg(
    '$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["REQUEST_URI"]="/api/v1/coverage"; include "public/index.php";'
    . 'foreach (get_included_files() as $f) { echo basename($f), "\n"; }'
));
$check(is_string($included) && !str_contains($included, 'DromResolver.php') && !str_contains($included, 'Database.php'), 'no resolver or sqlite bootstrap');

$query = file_get_contents($base . '/api/v1/coverage?access_token=' . rawurlencode($issued['token']), false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]));
$check(str_contains((string) ($http_response_header[0] ?? ''), '401'), 'query string is not a key');
$cookieReq = file_get_contents($base . '/api/v1/coverage', false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10, 'header' => 'Cookie: api_key=' . $issued['token']]]));
$check(str_contains((string) ($http_response_header[0] ?? ''), '401'), 'cookie is not a key');
unset($query, $cookieReq);
$platform->exec('UPDATE platform.api_keys SET revoked_at = now()');
$revoked = $get('/api/v1/coverage');
$check($revoked['status'] === 401, 'revoked key is 401');
$platform->exec('UPDATE platform.api_keys SET revoked_at = NULL, expires_at = now() - interval \'1 minute\'');
$check($get('/api/v1/coverage')['status'] === 401, 'expired key is 401');
$platform->exec('UPDATE platform.api_keys SET expires_at = NULL');
$platform->exec("UPDATE platform.projects SET status = 'disabled'");
$check($get('/api/v1/coverage')['status'] === 403, 'disabled project is 403');
$platform->exec("UPDATE platform.projects SET status = 'active'");
$platform->exec("UPDATE platform.users SET status = 'suspended'");
$check($get('/api/v1/coverage')['status'] === 403, 'suspended owner is 403');
$platform->exec("UPDATE platform.users SET status = 'active'");
$noKey = file_get_contents($base . '/api/v1/coverage', false, stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 10]]));
$check(str_contains((string) ($http_response_header[0] ?? ''), '401'), 'missing key is 401');
unset($noKey);
proc_terminate($proc);
proc_close($proc);

$downScript = <<<'PHP'
<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/v1/brands';
$_SERVER['HTTP_AUTHORIZATION'] = getenv('CARAPI_TEST_BEARER') ?: '';
include 'public/index.php';
echo "\nSTATUS:" . http_response_code();
PHP;
$downFile = sys_get_temp_dir() . '/catalog-down.php';
file_put_contents($downFile, $downScript);
$downEnv = $env;
$downEnv['CARAPI_CATALOG_PORT'] = '1';
$downEnv['CARAPI_TEST_BEARER'] = 'Bearer ' . $issued['token'];
$downEnv['CARAPI_CATALOG_CONNECT_TIMEOUT'] = '1';
$downProc = proc_open(
    escapeshellarg($php) . ' ' . escapeshellarg($downFile),
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $downPipes,
    $root,
    $downEnv
);
$downOut = is_resource($downProc) ? stream_get_contents($downPipes[1]) : '';
if (is_resource($downProc)) {
    fclose($downPipes[1]);
    fclose($downPipes[2]);
    proc_close($downProc);
}
@unlink($downFile);
$check(
    str_contains($downOut, '"code":"unavailable"')
    && str_contains($downOut, 'STATUS:503')
    && !str_contains($downOut, 'SQLSTATE')
    && !str_contains(strtolower($downOut), 'password'),
    'database down 503'
);

$offEnv = $env;
$offEnv['CARAPI_CATALOG_ENABLED'] = '0';
$port3 = 18083;
$cmd3 = escapeshellarg($php) . ' -S 127.0.0.1:' . $port3 . ' -t ' . escapeshellarg($root . '/public') . ' ' . escapeshellarg($root . '/public/index.php');
$proc3 = proc_open($cmd3, $descriptors, $pipes3, $root, $offEnv);
$hidden = false;
if (is_resource($proc3)) {
    usleep(200000);
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 5]]);
    $body = @file_get_contents('http://127.0.0.1:' . $port3 . '/api/v1/brands', false, $ctx);
    $status = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $m)) {
            $status = (int) $m[1];
        }
    }
    $hidden = $status === 404;
    proc_terminate($proc3);
    proc_close($proc3);
}
$check($hidden, 'disabled returns 404');
$who = (string) $pdo->query('SELECT current_user')->fetchColumn();
$check($who === $env['CARAPI_CATALOG_USER'], 'connected as catalog user');
$writeDenied = true;
foreach (['INSERT', 'UPDATE', 'DELETE', 'TRUNCATE', 'REFERENCES', 'TRIGGER', 'MAINTAIN'] as $priv) {
    if ($pdo->query("SELECT has_table_privilege(current_user, 'public.brands', " . $pdo->quote($priv) . ')')->fetchColumn()) {
        $writeDenied = false;
    }
}
$check($writeDenied && $sqlCount('SELECT count(*) FROM public.brands') === $c['brands_count'], 'no catalog write privilege');
$check((is_file($sqlite) ? filemtime($sqlite) . ':' . filesize($sqlite) : 'absent') === $sqliteBefore, 'sqlite still absent');
$admin->exec('DROP DATABASE IF EXISTS carapi_catalog_auth_test WITH (FORCE)');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " failed\n");
    exit(1);
}
fwrite(STDOUT, "all passed\n");
