<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$secret = '';
foreach (file('/www/server/carapi/platform.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'CARAPI_PLATFORM_PASSWORD=')) {
        $secret = substr($line, 26);
    }
}
if ($secret === '') {
    fwrite(STDERR, "platform env missing\n");
    exit(1);
}

$admin = new PDO('pgsql:host=127.0.0.1;dbname=postgres', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$drop = static function () use ($admin): void {
    $admin->exec('DROP DATABASE IF EXISTS carapi_admin_overview_test WITH (FORCE)');
};
$drop();
register_shutdown_function($drop);
$admin->exec('CREATE DATABASE carapi_admin_overview_test');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_admin_overview_test',
    'CARAPI_PLATFORM_ADMIN_USER' => 'postgres',
    'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
]);
stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($migrate) !== 0) {
    fwrite(STDERR, "migrate failed\n");
    exit(1);
}

$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_admin_overview_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_admin_overview_test TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE ON TABLE platform.users, platform.projects, platform.api_keys, platform.email_tokens, platform.mail_limits, platform.monthly_usage TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, DELETE ON TABLE platform.login_attempts TO carapi_platform_app');
$db->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');

$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'ok ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures[] = $name;
    }
};

$hash = Carapi\Platform\Passwords::hash('ქართული-პაროლი-12345');
$insertUser = $db->prepare('INSERT INTO platform.users (first_name, last_name, email, password_hash, status, email_verified_at, role) VALUES (:f,:l,:e,:p,:s, now(), :r)');
$insertUser->execute(['f' => 'ადმინი', 'l' => 'ტესტი', 'e' => 'admin-overview@example.com', 'p' => $hash, 's' => 'active', 'r' => 'admin']);
$now = gmmktime(12, 0, 0, 10, 15, 2026);
$empty = (new Carapi\Platform\AdminOverview($db))->collect($now);
$check($empty['stats'] === [
    'users_total' => 1, 'users_active' => 1, 'users_suspended' => 0,
    'projects_total' => 0, 'projects_active' => 0, 'projects_disabled' => 0,
    'keys_total' => 0, 'keys_active' => 0, 'keys_revoked' => 0, 'month_requests' => 0,
] && $empty['projects'] === [] && $empty['period'] === '2026-10-01', 'empty database stats');

$insertUser->execute(['f' => 'ანა', 'l' => 'ერთი', 'e' => 'ana-overview@example.com', 'p' => $hash, 's' => 'active', 'r' => 'user']);
$insertUser->execute(['f' => 'გია', 'l' => 'შეჩერებული', 'e' => 'gia-overview@example.com', 'p' => $hash, 's' => 'suspended', 'r' => 'user']);
$insertUser->execute(['f' => '<b>ნინო</b>', 'l' => 'ტესტი', 'e' => 'nina-overview@example.com', 'p' => $hash, 's' => 'active', 'r' => 'user']);
$ana = (int) $db->query("SELECT id FROM platform.users WHERE email = 'ana-overview@example.com'")->fetchColumn();
$gia = (int) $db->query("SELECT id FROM platform.users WHERE email = 'gia-overview@example.com'")->fetchColumn();
$nina = (int) $db->query("SELECT id FROM platform.users WHERE email = 'nina-overview@example.com'")->fetchColumn();
$project = $db->prepare('INSERT INTO platform.projects (user_id, name, status) VALUES (:u,:n,:s) RETURNING id');
$project->execute(['u' => $ana, 'n' => 'ანას პროექტი', 's' => 'active']);
$anaProject = (int) $project->fetchColumn();
$project->execute(['u' => $gia, 'n' => 'გამორთული', 's' => 'disabled']);
$project->execute(['u' => $nina, 'n' => "o'hara & <script>", 's' => 'active']);
$key = $db->prepare('INSERT INTO platform.api_keys (project_id, name, key_selector, secret_hash, expires_at, revoked_at) VALUES (:p,:n,:s,:h,:e,:r)');
$key->execute(['p' => $anaProject, 'n' => 'აქტიური', 's' => str_repeat('a', 32), 'h' => hash('sha256', 'secret-a'), 'e' => null, 'r' => null]);
$key->execute(['p' => $anaProject, 'n' => 'მომავალი', 's' => str_repeat('b', 32), 'h' => hash('sha256', 'secret-b'), 'e' => '2026-12-01 00:00:00+00', 'r' => null]);
$key->execute(['p' => $anaProject, 'n' => 'ვადაგასული', 's' => str_repeat('c', 32), 'h' => hash('sha256', 'secret-c'), 'e' => '2026-01-01 00:00:00+00', 'r' => null]);
$key->execute(['p' => $anaProject, 'n' => 'გაუქმებული', 's' => str_repeat('d', 32), 'h' => hash('sha256', 'secret-d'), 'e' => null, 'r' => '2026-10-01 00:00:00+00']);
$db->prepare('INSERT INTO platform.monthly_usage (project_id, period, used_count) VALUES (:p,:d,:n)')->execute(['p' => $anaProject, 'd' => '2026-10-01', 'n' => 7]);
$giaProject = (int) $db->query("SELECT id FROM platform.projects WHERE name = 'გამორთული'")->fetchColumn();
$db->prepare('INSERT INTO platform.monthly_usage (project_id, period, used_count) VALUES (:p,:d,:n)')->execute(['p' => $giaProject, 'd' => '2026-10-01', 'n' => 9]);
$db->prepare('INSERT INTO platform.monthly_usage (project_id, period, used_count) VALUES (:p,:d,:n)')->execute(['p' => $anaProject, 'd' => '2026-09-01', 'n' => 50]);
$filled = (new Carapi\Platform\AdminOverview($db))->collect($now);
$check($filled['stats'] === [
    'users_total' => 4, 'users_active' => 3, 'users_suspended' => 1,
    'projects_total' => 3, 'projects_active' => 2, 'projects_disabled' => 1,
    'keys_total' => 4, 'keys_active' => 2, 'keys_revoked' => 1, 'month_requests' => 16,
], 'statistics and utc month');
$check(count($filled['users']) === 4 && count($filled['projects']) === 3, 'recent rows are limited to existing rows');
$check((int) $db->query('SELECT count(*) FROM platform.users')->fetchColumn() === 4, 'quoted project name does not change users');

$sessionPath = sys_get_temp_dir() . '/carapi-admin-overview-sessions';
$port = 18131;
$env = [
    'CARAPI_PLATFORM_ENABLED' => '1',
    'CARAPI_PLATFORM_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_PORT' => '5432',
    'CARAPI_PLATFORM_DATABASE' => 'carapi_admin_overview_test',
    'CARAPI_PLATFORM_USER' => 'carapi_platform_app',
    'CARAPI_PLATFORM_PASSWORD' => $secret,
    'CARAPI_PLATFORM_SESSION_PATH' => $sessionPath,
    'CARAPI_CATALOG_ENABLED' => '0',
    'CARAPI_MAIL_TRANSPORT' => 'fake',
    'CARAPI_APP_URL' => 'http://127.0.0.1:' . $port,
    'CARAPI_USAGE_NOW' => (string) $now,
];
$proc = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'],
    [1 => ['file', '/tmp/carapi-admin-overview.log', 'w'], 2 => ['file', '/tmp/carapi-admin-overview.err', 'w']],
    $unused,
    $root,
    $env
);
$base = 'http://127.0.0.1:' . $port;
$http = static function (string $method, string $path, array $fields = [], ?string $cookie = null) use (&$base): array {
    $ch = curl_init($base . $path);
    $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 10];
    if ($cookie !== null) {
        $opts[CURLOPT_HTTPHEADER] = ['Cookie: carapi_platform=' . $cookie];
    }
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
    preg_match_all('/^Set-Cookie:\s*carapi_platform=([^;]+)/mi', $header, $cookies);

    return ['status' => $status, 'header' => $header, 'body' => substr($raw, $size), 'cookie' => $cookies[1][0] ?? $cookie];
};
$ready = false;
for ($i = 0; $i < 30; $i++) {
    if ($http('GET', '/login')['status'] !== 0) {
        $ready = true;
        break;
    }
    usleep(100000);
}
$boot = is_file('/tmp/carapi-admin-overview.err') ? (string) file_get_contents('/tmp/carapi-admin-overview.err') : '';
if (!$ready || str_contains($boot, 'Failed to listen')) {
    fwrite(STDERR, "overview test server did not bind\n");
    proc_terminate($proc);
    proc_close($proc);
    exit(1);
}

$login = static function (string $email) use ($http): array {
    $page = $http('GET', '/login');
    preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $csrf);

    return $http('POST', '/login', ['_csrf' => $csrf[1] ?? '', 'email' => $email, 'password' => 'ქართული-პაროლი-12345', 'role' => 'admin'], $page['cookie']);
};
$guest = $http('GET', '/admin');
$check($guest['status'] === 302 && str_contains($guest['header'], 'Location: /login') && !str_contains($guest['body'], 'data-stat'), 'guest cannot see overview');
$anaLogin = $login('ana-overview@example.com');
$userPage = $http('GET', '/admin', [], $anaLogin['cookie']);
$check($userPage['status'] === 404 && !str_contains($userPage['body'], 'data-stat'), 'ordinary user receives 404');
$signed = $login('admin-overview@example.com');
$page = $http('GET', '/admin', [], $signed['cookie']);
$stat = static function (string $body, string $key): ?string {
    return preg_match('/data-stat="' . $key . '">([^<]+)</', $body, $match) === 1 ? $match[1] : null;
};
$check($page['status'] === 200 && $stat($page['body'], 'month_requests') === '16' && $stat($page['body'], 'keys_active') === '2' && $stat($page['body'], 'keys_revoked') === '1' && $stat($page['body'], 'users_suspended') === '1', 'overview renders calculated stats');
$check(str_contains($page['body'], 'href="/admin/users"') && str_contains($page['body'], 'href="/admin/projects"') && str_contains($page['body'], 'aria-disabled="true"') && !str_contains($page['body'], 'href="/admin/payments"'), 'users and projects are linked');
$check(str_contains($page['body'], 'o&#039;hara &amp; &lt;script&gt;') && !str_contains($page['body'], "o'hara & <script>") && !str_contains($page['body'], '<b>ნინო</b>') && !str_contains($page['body'], 'password_hash') && !str_contains($page['body'], 'secret-a') && !str_contains($page['body'], str_repeat('a', 32)), 'html is escaped and secrets stay hidden');
$posted = $http('POST', '/admin', ['role' => 'admin'], $signed['cookie']);
$check($posted['status'] === 405, 'post admin performs no action');
$db->exec("UPDATE platform.users SET status = 'suspended' WHERE email = 'admin-overview@example.com'");
$suspended = $http('GET', '/admin', [], $signed['cookie']);
$check($suspended['status'] === 302 && str_contains($suspended['header'], 'Location: /login'), 'suspended admin is denied');

proc_terminate($proc);
proc_close($proc);
if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "admin overview ok\n";
