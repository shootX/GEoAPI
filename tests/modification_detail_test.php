<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$platformSecret = '';
foreach (file('/www/server/carapi/platform.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'CARAPI_PLATFORM_PASSWORD=')) {
        $platformSecret = substr($line, 26);
    }
}
if ($platformSecret === '') {
    fwrite(STDERR, "platform database password missing\n");
    exit(1);
}

$dbName = 'carapi_modification_detail_test';
$admin = new PDO('pgsql:host=127.0.0.1;dbname=postgres', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('DROP DATABASE IF EXISTS ' . $dbName . ' WITH (FORCE)');
$admin->exec('CREATE DATABASE ' . $dbName);
$drop = static function () use ($admin, $dbName): void {
    $admin->exec('DROP DATABASE IF EXISTS ' . $dbName . ' WITH (FORCE)');
};

$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $mpipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => $dbName,
    'CARAPI_PLATFORM_ADMIN_USER' => 'postgres',
    'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
]);
stream_get_contents($mpipes[1]);
fclose($mpipes[1]);
fclose($mpipes[2]);
if (proc_close($migrate) !== 0) {
    fwrite(STDERR, "migrate failed\n");
    $drop();
    exit(1);
}

$pdo = new PDO('pgsql:host=127.0.0.1;dbname=' . $dbName, 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('GRANT CONNECT ON DATABASE ' . $dbName . ' TO carapi_platform_app');
$pdo->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$pdo->exec('GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA platform TO carapi_platform_app');
$pdo->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');
$pdo->exec('CREATE TABLE public.brands (id bigint PRIMARY KEY, slug text NOT NULL, name text NOT NULL)');
$pdo->exec('CREATE TABLE public.models (id bigint PRIMARY KEY, brand_id bigint NOT NULL, slug text NOT NULL, name text NOT NULL)');
$pdo->exec('CREATE TABLE public.generations (id bigint PRIMARY KEY, model_id bigint NOT NULL, chassis_code text, name text)');
$pdo->exec('CREATE TABLE public.modifications (id bigint PRIMARY KEY, generation_id bigint, model_id bigint NOT NULL, name text NOT NULL, year_start integer, year_end integer, transmission text, engine_volume_cc integer, drive_type text, body_type text)');
$pdo->exec("INSERT INTO public.brands (id, slug, name) VALUES (1, 'brand-a', 'Brand A'), (2, 'brand-b', 'Brand B')");
$pdo->exec("INSERT INTO public.models (id, brand_id, slug, name) VALUES (1, 1, 'model-a', 'Model A'), (2, 1, 'model-b', 'Model B'), (4, 2, 'model-d', 'Model D'), (7, 1, 'model-null', 'Model Null'), (8, 1, 'model-empty', 'Model Empty')");
$pdo->exec("INSERT INTO public.generations (id, model_id, chassis_code, name) VALUES (10, 1, 'ONE', 'Single')");
$pdo->exec("INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc, drive_type, body_type) VALUES (100, 10, 1, 'Single 2.0', 2018, 2020, 'AT 6', 1998, 'FWD', 'Sedan')");
$pdo->exec("INSERT INTO public.generations (id, model_id, chassis_code, name) VALUES (20, 2, 'CAM', 'First'), (21, 2, 'cam', 'Second')");
$pdo->exec("INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc, drive_type, body_type) VALUES (201, 21, 2, 'On sibling', 2016, 2018, 'CVT', 2500, 'AWD', 'Wagon')");
$pdo->exec("INSERT INTO public.generations (id, model_id, chassis_code, name) VALUES (40, 4, 'DUP', 'Dup')");
$pdo->exec("INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc, drive_type, body_type) VALUES (401, 40, 4, 'Same', 2011, 2012, 'MT 5', 1600, 'FWD', 'Sedan'), (402, 40, 4, 'Same', 2011, 2012, 'AT 4', 2000, 'RWD', 'Coupe')");
$pdo->exec("INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc, drive_type, body_type) VALUES (700, NULL, 7, 'No generation', 2014, NULL, 'MT 5', 1400, 'FWD', 'Hatchback')");
$pdo->exec("INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc, drive_type, body_type) VALUES (800, 10, 8, 'Blanks', NULL, NULL, '   ', NULL, '', '')");

$issued = \Carapi\Platform\ApiKeys::issue();
$forbidden = \Carapi\Platform\ApiKeys::issue();
$pdo->exec("INSERT INTO platform.users (first_name, last_name, email, password_hash, email_verified_at) VALUES ('ტესტ', 'მომხმარე', 'detail@example.com', 'x', now())");
$userId = (int) $pdo->query("SELECT id FROM platform.users WHERE email = 'detail@example.com'")->fetchColumn();
$pdo->prepare('INSERT INTO platform.projects (user_id, name) VALUES (:user_id, :name)')->execute(['user_id' => $userId, 'name' => 'დეტალი']);
$projectId = (int) $pdo->query("SELECT id FROM platform.projects WHERE name = 'დეტალი'")->fetchColumn();
$pdo->prepare('INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota) VALUES (:id, 1000, 100000)')->execute(['id' => $projectId]);
$pdo->prepare('INSERT INTO platform.api_keys (project_id, name, key_selector, secret_hash, scope) VALUES (:project_id, :name, :selector, :hash, :scope)')
    ->execute(['project_id' => $projectId, 'name' => 'სერვერი', 'selector' => $issued['selector'], 'hash' => $issued['hash'], 'scope' => 'catalog:read']);
$pdo->exec("INSERT INTO platform.users (first_name, last_name, email, password_hash) VALUES ('დაუდასტურებელი', 'მომხმარე', 'unverified-detail@example.com', 'x')");
$unverifiedId = (int) $pdo->query("SELECT id FROM platform.users WHERE email = 'unverified-detail@example.com'")->fetchColumn();
$pdo->prepare('INSERT INTO platform.projects (user_id, name) VALUES (:user_id, :name)')->execute(['user_id' => $unverifiedId, 'name' => 'დახურული']);
$closedId = (int) $pdo->query("SELECT id FROM platform.projects WHERE name = 'დახურული'")->fetchColumn();
$pdo->prepare('INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota) VALUES (:id, 1000, 100000)')->execute(['id' => $closedId]);
$pdo->prepare('INSERT INTO platform.api_keys (project_id, name, key_selector, secret_hash, scope) VALUES (:project_id, :name, :selector, :hash, :scope)')
    ->execute(['project_id' => $closedId, 'name' => 'დახურული', 'selector' => $forbidden['selector'], 'hash' => $forbidden['hash'], 'scope' => 'catalog:read']);

$plan = $pdo->query('EXPLAIN SELECT mod.id FROM public.modifications mod WHERE mod.id = 100')->fetchAll(PDO::FETCH_COLUMN);
$planText = implode("\n", $plan);

$port = 18085;
$failures = 0;
$check = static function (string $label, bool $ok) use (&$failures): void {
    if ($ok) {
        echo "ok {$label}\n";

        return;
    }
    $failures++;
    echo "fail {$label}\n";
};

/** @return array{0:int,1:string,2:string} */
function detail_raw(int $port, string $target, string $auth): array
{
    $fp = fsockopen('127.0.0.1', $port, $errno, $err, 3);
    if (!is_resource($fp)) {
        return [0, '', ''];
    }
    $header = $auth === '' ? '' : $auth . "\r\n";
    fwrite($fp, "GET {$target} HTTP/1.0\r\nHost: 127.0.0.1\r\nConnection: close\r\n{$header}\r\n");
    $raw = stream_get_contents($fp);
    fclose($fp);
    if (!is_string($raw) || !str_contains($raw, "\r\n\r\n")) {
        return [0, '', ''];
    }
    [$head, $body] = explode("\r\n\r\n", $raw, 2);
    $status = 0;
    if (preg_match('#^HTTP/\S+\s+(\d+)#', $head, $match) === 1) {
        $status = (int) $match[1];
    }

    return [$status, $head, $body];
}

/** @param array<string, string> $extra */
function detail_serve(string $root, int $port, string $dbName, string $platformSecret, array $extra): mixed
{
    $env = [
        'PATH' => getenv('PATH') ?: '/usr/bin',
        'CARAPI_CATALOG_ENABLED' => '1',
        'CARAPI_MODIFICATIONS_ENABLED' => '1',
        'CARAPI_CATALOG_HOST' => '127.0.0.1',
        'CARAPI_CATALOG_PORT' => '5432',
        'CARAPI_CATALOG_DATABASE' => $dbName,
        'CARAPI_CATALOG_USER' => 'postgres',
        'CARAPI_CATALOG_PASSWORD' => '',
        'CARAPI_CATALOG_STATEMENT_TIMEOUT_MS' => '10000',
        'CARAPI_PLATFORM_ENABLED' => '1',
        'CARAPI_PLATFORM_HOST' => '127.0.0.1',
        'CARAPI_PLATFORM_PORT' => '5432',
        'CARAPI_PLATFORM_DATABASE' => $dbName,
        'CARAPI_PLATFORM_USER' => 'carapi_platform_app',
        'CARAPI_PLATFORM_PASSWORD' => $platformSecret,
    ] + $extra;
    $proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'], [
        0 => ['pipe', 'r'],
        1 => ['file', '/tmp/modification-detail-test.log', 'a'],
        2 => ['file', '/tmp/modification-detail-test.err', 'a'],
    ], $pipes, $root, $env);
    if (!is_resource($proc)) {
        return null;
    }
    for ($i = 0; $i < 40; $i++) {
        $probe = @fsockopen('127.0.0.1', $port, $errno, $err, 0.2);
        if (is_resource($probe)) {
            fclose($probe);

            return $proc;
        }
        usleep(100000);
    }
    proc_terminate($proc);
    proc_close($proc);

    return null;
}

$usage = static function (string $route) use ($pdo, $projectId): int {
    $stmt = $pdo->prepare('SELECT used_count FROM platform.daily_usage WHERE project_id = :id AND route = :route');
    $stmt->execute(['id' => $projectId, 'route' => $route]);
    $value = $stmt->fetchColumn();

    return $value === false ? 0 : (int) $value;
};
$monthly = static function () use ($pdo, $projectId): int {
    $stmt = $pdo->prepare('SELECT used_count FROM platform.monthly_usage WHERE project_id = :id');
    $stmt->execute(['id' => $projectId]);
    $value = $stmt->fetchColumn();

    return $value === false ? 0 : (int) $value;
};

$bearer = 'Authorization: Bearer ' . $issued['token'];
$closedBearer = 'Authorization: Bearer ' . $forbidden['token'];
$proc = detail_serve($root, $port, $dbName, $platformSecret, []);
if (!is_resource($proc)) {
    fwrite(STDERR, "disabled server failed\n");
    $drop();
    exit(1);
}
[$status] = detail_raw($port, '/api/v1/modifications/100', $bearer);
$check('flag disabled is 404', $status === 404 && $monthly() === 0 && $usage('modification') === 0);
[$status, $head, $body] = detail_raw($port, '/api/v1/generations/10/modifications', $bearer);
$list = json_decode($body, true);
$check('list still works while details disabled', $status === 200 && ($list['meta']['total'] ?? 0) === 2 && $usage('modifications') === 1 && $usage('modification') === 0);
proc_terminate($proc);
proc_close($proc);

$proc = detail_serve($root, $port, $dbName, $platformSecret, ['CARAPI_MODIFICATION_DETAILS_ENABLED' => '1']);
if (!is_resource($proc)) {
    fwrite(STDERR, "enabled server failed\n");
    $drop();
    exit(1);
}
$stop = static function () use ($proc, $drop): void {
    proc_terminate($proc);
    proc_close($proc);
    $drop();
};

[$status] = detail_raw($port, '/api/v1/modifications/100', '');
$check('unauthorized', $status === 401);

[$status, $head, $body] = detail_raw($port, '/api/v1/modifications/100', $closedBearer);
$closed = json_decode($body, true);
$check('catalog access forbidden', $status === 403 && ($closed['error']['code'] ?? '') === 'forbidden');

[$status] = detail_raw($port, '/api/v1/modifications/0', $bearer);
$check('invalid id', $status === 422);

[$status, $head, $body] = detail_raw($port, '/api/v1/modifications/99999', $bearer);
$missing = json_decode($body, true);
$check('missing id', $status === 404 && ($missing['error']['message'] ?? '') === 'მითითებული მოდიფიკაცია ვერ მოიძებნა.');

[$status, $head, $body] = detail_raw($port, '/api/v1/modifications/100', $bearer);
$one = json_decode($body, true);
$data = is_array($one['data'] ?? null) ? $one['data'] : [];
$check('valid modification', $status === 200
    && ($data['id'] ?? '') === '100'
    && ($data['name'] ?? '') === 'Single 2.0'
    && ($data['engine_volume_cc'] ?? 0) === 1998
    && ($data['transmission'] ?? '') === 'AT 6'
    && ($data['drive_type'] ?? '') === 'FWD'
    && ($data['body_type'] ?? '') === 'Sedan'
    && ($data['year_start'] ?? 0) === 2018
    && ($data['year_end'] ?? 0) === 2020
    && ($data['brand']['id'] ?? '') === '1'
    && ($data['brand']['slug'] ?? '') === 'brand-a'
    && ($data['model']['id'] ?? '') === '1'
    && ($data['generation']['id'] ?? '') === '10'
    && ($data['generation']['name'] ?? '') === 'Single'
    && str_contains($head, 'X-Quota-Limit:')
    && str_contains($head, 'X-Quota-Remaining:'));

[$status, $head, $body] = detail_raw($port, '/api/v1/modifications/201', $bearer);
$sibling = json_decode($body, true);
$check('public generation id', $status === 200 && ($sibling['data']['generation']['id'] ?? '') === '20' && ($sibling['data']['generation']['name'] ?? '') === 'First' && ($sibling['data']['id'] ?? '') === '201');

[$status, $head, $body] = detail_raw($port, '/api/v1/modifications/700', $bearer);
$nullGen = json_decode($body, true);
$check('null generation', $status === 200 && array_key_exists('generation', $nullGen['data'] ?? []) && $nullGen['data']['generation'] === null && ($nullGen['data']['brand']['slug'] ?? '') === 'brand-a' && ($nullGen['data']['model']['slug'] ?? '') === 'model-null');

[$status, $head, $body] = detail_raw($port, '/api/v1/modifications/401', $bearer);
$sameA = json_decode($body, true);
[$statusB, , $bodyB] = detail_raw($port, '/api/v1/modifications/402', $bearer);
$sameB = json_decode($bodyB, true);
$check('duplicate names stay separate', $status === 200 && $statusB === 200 && ($sameA['data']['name'] ?? '') === 'Same' && ($sameB['data']['name'] ?? '') === 'Same' && ($sameA['data']['id'] ?? '') === '401' && ($sameB['data']['id'] ?? '') === '402' && ($sameA['data']['transmission'] ?? '') !== ($sameB['data']['transmission'] ?? ''));

[$status, $head, $body] = detail_raw($port, '/api/v1/modifications/800', $bearer);
$blank = json_decode($body, true);
$blankData = is_array($blank['data'] ?? null) ? $blank['data'] : [];
$check('nullable fields', $status === 200
    && array_key_exists('year_start', $blankData) && $blankData['year_start'] === null
    && array_key_exists('year_end', $blankData) && $blankData['year_end'] === null
    && array_key_exists('transmission', $blankData) && $blankData['transmission'] === null
    && array_key_exists('engine_volume_cc', $blankData) && $blankData['engine_volume_cc'] === null
    && array_key_exists('drive_type', $blankData) && $blankData['drive_type'] === null
    && array_key_exists('body_type', $blankData) && $blankData['body_type'] === null);

$started = microtime(true);
[$status] = detail_raw($port, '/api/v1/modifications/100', $bearer);
$elapsed = microtime(true) - $started;
$check('query time', $status === 200 && $elapsed < 1);
$check('primary key plan', str_contains($planText, 'modifications_pkey'));

[$status, $head, $body] = detail_raw($port, '/api/v1/generations/20/modifications', $bearer);
$listAgain = json_decode($body, true);
$check('list unchanged', $status === 200 && ($listAgain['data'][0]['id'] ?? '') === '201' && ($listAgain['data'][0]['generation_id'] ?? '') === '20' && !array_key_exists('brand', $listAgain['data'][0] ?? []));
$check('route categories', $usage('modification') === 7 && $usage('modifications') === 2 && $monthly() === 9);

[$status] = detail_raw($port, '/api/v1/generations/10/modifications', $bearer);
$check('existing list endpoint', $status === 200);

$stop();
if ($failures > 0) {
    fwrite(STDERR, "failures={$failures}\n");
    exit(1);
}
echo "modification detail tests passed\n";
