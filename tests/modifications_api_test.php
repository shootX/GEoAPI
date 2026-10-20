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

$dbName = 'carapi_modifications_test';
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
$migrateCode = proc_close($migrate);
if ($migrateCode !== 0) {
    fwrite(STDERR, "migrate failed\n");
    $drop();
    exit(1);
}

$pdo = new PDO('pgsql:host=127.0.0.1;dbname=' . $dbName, 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('GRANT CONNECT ON DATABASE ' . $dbName . ' TO carapi_platform_app');
$pdo->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$pdo->exec('GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA platform TO carapi_platform_app');
$pdo->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');
$pdo->exec('CREATE TABLE public.generations (id bigint PRIMARY KEY, model_id bigint NOT NULL, chassis_code text, name text)');
$pdo->exec('CREATE TABLE public.modifications (id bigint PRIMARY KEY, generation_id bigint, model_id bigint NOT NULL, name text NOT NULL, year_start integer, year_end integer, transmission text, engine_volume_cc integer)');
$pdo->exec("INSERT INTO public.generations (id, model_id, chassis_code, name) VALUES (10, 1, 'ONE', 'Single')");
$pdo->exec("INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc) VALUES (100, 10, 1, 'Single 2.0', 2018, 2020, 'AT 6', 1998)");
$pdo->exec("INSERT INTO public.generations (id, model_id, chassis_code, name) VALUES (20, 2, 'CAM', 'First'), (21, 2, 'cam', 'Second')");
$pdo->exec("INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc) VALUES (200, 20, 2, 'On public', 2015, 2017, 'MT 6', 1800), (201, 21, 2, 'On sibling', 2016, 2018, 'CVT', 2500)");
$pdo->exec("INSERT INTO public.generations (id, model_id, chassis_code, name) VALUES (40, 4, 'DUP', 'Dup')");
$pdo->exec("INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc) VALUES (401, 40, 4, 'Same', 2011, 2012, 'MT 5', 1600), (402, 40, 4, 'Same', 2011, 2012, 'AT 4', 2000)");
$pdo->exec("INSERT INTO public.generations (id, model_id, chassis_code, name) VALUES (50, 5, 'EMPTY', 'Empty')");
$pdo->exec("INSERT INTO public.generations (id, model_id, chassis_code, name) VALUES (60, 6, 'PAGE', 'Page')");
$pdo->exec("INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc) VALUES (601, 60, 6, 'A', 2010, 2010, 'MT 5', 1000), (602, 60, 6, 'B', 2011, 2011, 'MT 5', 1100), (603, 60, 6, 'C', 2012, 2012, 'MT 5', 1200)");
$big = $pdo->prepare('INSERT INTO public.generations (id, model_id, chassis_code, name) VALUES (:id, 3, :code, :name)');
for ($i = 0; $i < 72; $i++) {
    $big->execute(['id' => 1000 + $i, 'code' => $i % 2 === 0 ? 'BIG' : 'big', 'name' => 'Row ' . $i]);
}
$pdo->exec("INSERT INTO public.modifications (id, generation_id, model_id, name, year_start, year_end, transmission, engine_volume_cc) VALUES (9000, 1071, 3, 'Last sibling', 2020, NULL, 'AT 8', 2400)");

$issued = \Carapi\Platform\ApiKeys::issue();
$pdo->exec("INSERT INTO platform.users (first_name, last_name, email, password_hash, email_verified_at) VALUES ('ტესტ', 'მომხმარე', 'mods@example.com', 'x', now())");
$userId = (int) $pdo->query('SELECT id FROM platform.users LIMIT 1')->fetchColumn();
$pdo->prepare('INSERT INTO platform.projects (user_id, name) VALUES (:user_id, :name)')->execute(['user_id' => $userId, 'name' => 'მოდიფიკაციები']);
$projectId = (int) $pdo->query('SELECT id FROM platform.projects LIMIT 1')->fetchColumn();
$pdo->prepare('INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota) VALUES (:id, 1000, 100000)')->execute(['id' => $projectId]);
$pdo->prepare('INSERT INTO platform.api_keys (project_id, name, key_selector, secret_hash) VALUES (:project_id, :name, :selector, :hash)')
    ->execute(['project_id' => $projectId, 'name' => 'სერვერი', 'selector' => $issued['selector'], 'hash' => $issued['hash']]);

$port = 18084;
$proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'], [
    0 => ['pipe', 'r'],
    1 => ['file', '/tmp/modifications-api-test.log', 'w'],
    2 => ['file', '/tmp/modifications-api-test.err', 'w'],
], $pipes, $root, [
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
]);
if (!is_resource($proc)) {
    fwrite(STDERR, "server failed\n");
    $drop();
    exit(1);
}
$stop = static function () use ($proc, &$pipes, $drop): void {
    if (isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
    }
    proc_terminate($proc);
    proc_close($proc);
    $drop();
};
$bearer = 'Authorization: Bearer ' . $issued['token'];
$ready = false;
for ($i = 0; $i < 40; $i++) {
    $probe = @fsockopen('127.0.0.1', $port, $errno, $err, 0.2);
    if (is_resource($probe)) {
        fclose($probe);
        $ready = true;
        break;
    }
    usleep(100000);
}
if (!$ready) {
    fwrite(STDERR, "server did not listen\n");
    $stop();
    exit(1);
}

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
function mods_raw(int $port, string $target, string $auth): array
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

[$status] = mods_raw($port, '/api/v1/generations/10/modifications', '');
$check('unauthorized', $status === 401);

[$status] = mods_raw($port, '/api/v1/generations/0/modifications', $bearer);
$check('invalid id', $status === 422);

[$status, $head, $body] = mods_raw($port, '/api/v1/generations/10/modifications', $bearer);
$single = json_decode($body, true);
$check('single group', $status === 200 && is_array($single) && ($single['meta']['total'] ?? 0) === 1 && ($single['data'][0]['id'] ?? '') === '100' && ($single['data'][0]['engine_volume_cc'] ?? 0) === 1998 && str_contains($head, 'X-Quota-Limit:'));

[$status, $head, $body] = mods_raw($port, '/api/v1/generations/20/modifications', $bearer);
$multi = json_decode($body, true);
$ids = array_column(is_array($multi['data'] ?? null) ? $multi['data'] : [], 'id');
$check('sibling included', $status === 200 && $ids === ['200', '201'] && ($multi['data'][0]['generation_id'] ?? '') === '20' && ($multi['data'][1]['generation_id'] ?? '') === '20');

[$status] = mods_raw($port, '/api/v1/generations/21/modifications', $bearer);
$check('non canonical', $status === 404);

[$status] = mods_raw($port, '/api/v1/generations/99999/modifications', $bearer);
$check('missing generation', $status === 404);

[$status, $head, $body] = mods_raw($port, '/api/v1/generations/40/modifications', $bearer);
$dups = json_decode($body, true);
$dupIds = array_column(is_array($dups['data'] ?? null) ? $dups['data'] : [], 'id');
$check('duplicate names stay separate', $status === 200 && $dupIds === ['401', '402'] && ($dups['data'][0]['name'] ?? '') === 'Same' && ($dups['data'][1]['name'] ?? '') === 'Same');

[$status, $head, $body] = mods_raw($port, '/api/v1/generations/50/modifications', $bearer);
$empty = json_decode($body, true);
$check('empty modifications', $status === 200 && ($empty['data'] ?? null) === [] && ($empty['meta']['total'] ?? -1) === 0);

[$status, $head, $body] = mods_raw($port, '/api/v1/generations/60/modifications?page=2&per_page=1', $bearer);
$page = json_decode($body, true);
$check('pagination', $status === 200 && ($page['meta']['page'] ?? 0) === 2 && ($page['meta']['total'] ?? 0) === 3 && ($page['data'][0]['id'] ?? '') === '602');

$started = microtime(true);
[$status, $head, $body] = mods_raw($port, '/api/v1/generations/1000/modifications', $bearer);
$large = json_decode($body, true);
$elapsed = microtime(true) - $started;
$yearEnd = is_array($large['data'][0] ?? null) && array_key_exists('year_end', $large['data'][0]) ? $large['data'][0]['year_end'] : 'missing';
$check('largest group', $status === 200 && ($large['meta']['total'] ?? 0) === 1 && ($large['data'][0]['id'] ?? '') === '9000' && ($large['data'][0]['generation_id'] ?? '') === '1000' && $yearEnd === null);
$check('query time', $elapsed < 1);

[$status] = mods_raw($port, '/api/v1/generations/1071/modifications', $bearer);
$check('largest sibling rejected', $status === 404);

$stop();
if ($failures > 0) {
    fwrite(STDERR, "failures={$failures}\n");
    exit(1);
}
echo "modifications api tests passed\n";
