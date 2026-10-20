<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$sqlite = $root . '/data/carapi.sqlite';
$sqliteBefore = is_file($sqlite) ? filemtime($sqlite) . ':' . filesize($sqlite) : 'absent';
$secret = '';
foreach (file('/www/server/carapi/platform.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'CARAPI_PLATFORM_PASSWORD=')) {
        $secret = substr($line, 26);
    }
}
require $root . '/vendor/autoload.php';
$admin = new PDO('pgsql:host=127.0.0.1;dbname=postgres', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('DROP DATABASE IF EXISTS carapi_projects_test WITH (FORCE)');
$admin->exec('CREATE DATABASE carapi_projects_test');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_projects_test', 'CARAPI_PLATFORM_ADMIN_USER' => 'postgres', 'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
]);
stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($migrate);
$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_projects_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_projects_test TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');
$password = 'ქართული-პაროლი-12345';
$hash = \Carapi\Platform\Passwords::hash($password);
$db->prepare('INSERT INTO platform.users (first_name, last_name, email, password_hash, email_verified_at) VALUES (:f,:l,:e,:p, now())')->execute(['f' => 'ანა', 'l' => 'ერთი', 'e' => 'ana@example.com', 'p' => $hash]);
$db->prepare('INSERT INTO platform.users (first_name, last_name, email, password_hash) VALUES (:f,:l,:e,:p)')->execute(['f' => 'ბია', 'l' => 'ორი', 'e' => 'bia@example.com', 'p' => $hash]);
$env = [
    'CARAPI_PLATFORM_ENABLED' => '1', 'CARAPI_PLATFORM_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_PORT' => '5432',
    'CARAPI_PLATFORM_DATABASE' => 'carapi_projects_test', 'CARAPI_PLATFORM_USER' => 'carapi_platform_app', 'CARAPI_PLATFORM_PASSWORD' => $secret,
    'CARAPI_PLATFORM_SESSION_PATH' => '/tmp/carapi-projects-sessions', 'CARAPI_CATALOG_ENABLED' => '0',
];
$port = 18095;
$base = 'http://127.0.0.1:' . $port;
$proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'], [1 => ['file', '/tmp/projects.log', 'w'], 2 => ['file', '/tmp/projects.err', 'w']], $u, $root, $env);
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
$ana = $login('ana@example.com');
$bia = $login('bia@example.com');
$page = $http('GET', '/account', [], $ana['cookie']);
preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $csrf);
$made = $http('POST', '/account', ['_csrf' => $csrf[1] ?? '', 'action' => 'create_project', 'name' => 'ანას პროექტი', 'website_url' => 'https://ana.example'], $ana['cookie']);
$check($made['status'] === 302, 'verified user creates project');
$list = $http('GET', '/account', [], $ana['cookie']);
preg_match('#/account/projects/(\d+)#', $list['body'], $id);
$projectId = $id[1] ?? '0';
$foreign = $http('GET', '/account/projects/' . $projectId, [], $bia['cookie']);
$check($foreign['status'] === 404, 'other user cannot open project');
$biaPage = $http('GET', '/account', [], $bia['cookie']);
$check(!str_contains($biaPage['body'], 'ანას პროექტი'), 'projects are private');
preg_match('/name="_csrf" value="([^"]+)"/', $biaPage['body'], $biaCsrf);
$biaCreate = $http('POST', '/account', ['_csrf' => $biaCsrf[1] ?? '', 'action' => 'create_project', 'name' => 'ბიას პროექტი'], $bia['cookie']);
preg_match('#/account/projects/(\d+)#', $http('GET', '/account', [], $bia['cookie'])['body'], $biaId);
$noKey = $http('POST', '/account/projects/' . ($biaId[1] ?? '0'), ['_csrf' => $biaCsrf[1] ?? '', 'action' => 'create_key', 'name' => 'სერვერი'], $bia['cookie']);
$check($noKey['status'] === 403, 'unverified cannot create key');
$own = $http('GET', '/account/projects/' . $projectId, [], $ana['cookie']);
preg_match('/name="_csrf" value="([^"]+)"/', $own['body'], $anaCsrf);
$noCsrf = $http('POST', '/account/projects/' . $projectId, ['action' => 'create_key', 'name' => 'სერვერი'], $ana['cookie']);
$check($noCsrf['status'] === 419, 'csrf required');
$created = $http('POST', '/account/projects/' . $projectId, ['_csrf' => $anaCsrf[1] ?? '', 'action' => 'create_key', 'name' => 'სერვერი'], $ana['cookie']);
preg_match('/id="new-key" readonly value="([^"]+)"/', $created['body'], $token);
$check($created['status'] === 200 && str_contains($created['body'], 'მხოლოდ ერთხელ გამოჩნდება') && str_starts_with($token[1] ?? '', 'carapi_sk_'), 'full key shown once');
$again = $http('GET', '/account/projects/' . $projectId, [], $ana['cookie']);
$check(!str_contains($again['body'], $token[1] ?? 'missing') && !str_contains($again['body'], 'secret_hash'), 'key hidden afterwards');
$stored = (string) $db->query('SELECT secret_hash FROM platform.api_keys LIMIT 1')->fetchColumn();
$check($stored !== '' && !str_contains($stored, $token[1] ?? 'missing'), 'database stores hash only');
$steal = $http('POST', '/account/projects/' . $projectId, ['_csrf' => $biaCsrf[1] ?? '', 'action' => 'revoke_key', 'key_id' => '1'], $bia['cookie']);
$check($steal['status'] === 404, 'other user cannot revoke key');
$db->exec('UPDATE platform.projects SET status = \'disabled\' WHERE id = ' . (int) $projectId);
$db->exec('UPDATE platform.projects SET status = \'active\' WHERE id = ' . (int) $projectId);
$db->exec('UPDATE platform.api_keys SET revoked_at = now() WHERE project_id = ' . (int) $projectId);
$revokedStill = (string) $db->query('SELECT revoked_at FROM platform.api_keys WHERE project_id = ' . (int) $projectId)->fetchColumn();
$check($revokedStill !== '', 're-enable does not restore revoked key');
$sqliteAfter = is_file($sqlite) ? filemtime($sqlite) . ':' . filesize($sqlite) : 'absent';
$check($sqliteAfter === $sqliteBefore, 'sqlite not used');
$live = (int) (new PDO('pgsql:host=127.0.0.1;dbname=carbase', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]))->query('SELECT count(*) FROM platform.api_keys')->fetchColumn();
$check($live === 0, 'working carbase has no test keys');
proc_terminate($proc);
proc_close($proc);
$admin->exec('DROP DATABASE IF EXISTS carapi_projects_test WITH (FORCE)');
if ($failures !== []) {
    exit(1);
}
echo "all passed\n";
