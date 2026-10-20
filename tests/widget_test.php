<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$secret = '';
$catalogSecret = '';
$catalogUser = 'carapi_catalog_reader';
foreach (file('/www/server/carapi/platform.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'CARAPI_PLATFORM_PASSWORD=')) $secret = substr($line, 26);
}
foreach (file('/www/server/carapi/catalog.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'CARAPI_CATALOG_PASSWORD=')) $catalogSecret = substr($line, 26);
    if (str_starts_with($line, 'CARAPI_CATALOG_USER=')) $catalogUser = substr($line, 20);
}
require $root . '/vendor/autoload.php';
$admin = new PDO('pgsql:host=127.0.0.1;dbname=postgres', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('DROP DATABASE IF EXISTS carapi_widget_test WITH (FORCE)');
$admin->exec('CREATE DATABASE carapi_widget_test');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_widget_test', 'CARAPI_PLATFORM_ADMIN_USER' => 'postgres', 'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
]);
stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); proc_close($migrate);
$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_widget_test TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE ON ALL TABLES IN SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');
$hash = \Carapi\Platform\Passwords::hash('ქართული-პაროლი-12345');
$db->prepare("INSERT INTO platform.users (first_name,last_name,email,password_hash,email_verified_at) VALUES ('ანა','ერთი','widget@example.com',:p,now())")->execute(['p' => $hash]);
$userId = (int) $db->query('SELECT id FROM platform.users')->fetchColumn();
$db->prepare('INSERT INTO platform.projects (user_id, name) VALUES (:id, :name)')->execute(['id' => $userId, 'name' => 'ვიჯეტი']);
$projectId = (int) $db->query('SELECT id FROM platform.projects')->fetchColumn();
$db->prepare('INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota) VALUES (:id, 60, 3)')->execute(['id' => $projectId]);
$key = \Carapi\Platform\WidgetRepository::newKey();
$db->prepare("INSERT INTO platform.widgets (project_id, enabled, public_key, allowed_origins) VALUES (:id, true, :key, :origins::jsonb)")
    ->execute(['id' => $projectId, 'key' => $key, 'origins' => json_encode(['http://127.0.0.1:18099', 'http://127.0.0.1:18098'])]);
$env = [
    'CARAPI_WIDGET_ENABLED' => '1', 'CARAPI_CATALOG_ENABLED' => '1', 'CARAPI_PLATFORM_ENABLED' => '1',
    'CARAPI_CATALOG_HOST' => '127.0.0.1', 'CARAPI_CATALOG_PORT' => '5432', 'CARAPI_CATALOG_DATABASE' => 'carbase',
    'CARAPI_CATALOG_USER' => $catalogUser, 'CARAPI_CATALOG_PASSWORD' => $catalogSecret,
    'CARAPI_PLATFORM_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_PORT' => '5432', 'CARAPI_PLATFORM_DATABASE' => 'carapi_widget_test',
    'CARAPI_PLATFORM_USER' => 'carapi_platform_app', 'CARAPI_PLATFORM_PASSWORD' => $secret,
    'CARAPI_IP_PER_MINUTE' => '1000',
];
$port = 18098;
$base = 'http://127.0.0.1:' . $port;
$proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'], [1 => ['file', '/tmp/widget.log', 'w'], 2 => ['file', '/tmp/widget.err', 'w']], $u, $root, $env);
$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'ok ' : 'FAIL ') . $name . "\n";
    if (!$ok) $failures[] = $name;
};
$call = static function (string $method, string $path, ?string $origin, ?string $body = null) use ($base): array {
    $ch = curl_init($base . $path);
    $headers = ['Content-Type: application/json'];
    if ($origin !== null) $headers[] = 'Origin: ' . $origin;
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body, CURLOPT_TIMEOUT => 20]);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['status' => $status, 'header' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
};
usleep(300000);
$path = '/widget/v1/' . $key . '/brands';
$check($call('POST', $path, null, '{}')['status'] === 403, 'missing origin rejected');
$check($call('POST', $path, 'null', '{}')['status'] === 403, 'null origin rejected');
$bad = $call('POST', $path, 'https://evil.example', '{}');
$check($bad['status'] === 403 && !str_contains($bad['header'], 'evil.example'), 'bad origin is not reflected');
$pre = $call('OPTIONS', $path, 'http://127.0.0.1:18099');
$check($pre['status'] === 204 && str_contains($pre['header'], 'Access-Control-Allow-Origin: http://127.0.0.1:18099'), 'preflight');
$ok = $call('POST', $path, 'http://127.0.0.1:18098', '{"per_page":1}');
$check($ok['status'] === 200 && str_contains($ok['body'], '"data"') && !str_contains($ok['body'], 'carapi_sk_'), 'allowed post');
$used = (int) $db->query('SELECT used_count FROM platform.monthly_usage')->fetchColumn();
$check($used === 1, 'widget spends project quota');
$db->prepare('UPDATE platform.widgets SET public_key = :key WHERE project_id = :id')->execute(['key' => \Carapi\Platform\WidgetRepository::newKey(), 'id' => $projectId]);
$check($call('POST', $path, 'http://127.0.0.1:18098', '{}')['status'] === 404, 'rotated key rejected');
$demo = $call('POST', '/widget/v1/demo/brands', 'http://127.0.0.1:18098', '{"per_page":1}');
$check($demo['status'] === 200 && str_contains($demo['body'], '"data"') && !str_contains($demo['header'], 'Access-Control-Allow-Origin'), 'demo same origin');
$check($call('POST', '/widget/v1/demo/brands', 'https://evil.example', '{}')['status'] === 403, 'demo foreign origin rejected');
$check($call('POST', '/widget/v1/demo/brands', null, '{}')['status'] === 403, 'demo missing origin rejected');
$usedAfterDemo = (int) $db->query('SELECT coalesce(sum(used_count),0) FROM platform.monthly_usage')->fetchColumn();
$check($usedAfterDemo === 1, 'demo does not spend quota');
file_put_contents('/tmp/carapi-widget-key.txt', $db->query('SELECT public_key FROM platform.widgets')->fetchColumn());
file_put_contents('/tmp/carapi-widget-base.txt', $base);
proc_terminate($proc);
if ($failures !== []) {
    proc_close($proc);
    $admin->exec('DROP DATABASE IF EXISTS carapi_widget_test WITH (FORCE)');
    exit(1);
}
echo "curl passed\n";
echo $base . "\n";
echo trim((string) file_get_contents('/tmp/carapi-widget-key.txt')) . "\n";
