<?php

declare(strict_types=1);

$root = dirname(__DIR__);
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
    $admin->exec('DROP DATABASE IF EXISTS carapi_role_migration_test WITH (FORCE)');
    $admin->exec('DROP DATABASE IF EXISTS carapi_admin_auth_test WITH (FORCE)');
};
$drop();
register_shutdown_function($drop);

$admin->exec('CREATE DATABASE carapi_role_migration_test');
$schema = new PDO('pgsql:host=127.0.0.1;dbname=carapi_role_migration_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
foreach (['001_platform_users.sql', '002_projects_api_keys.sql', '003_email_tokens.sql', '004_usage_limits.sql', '005_widget_keys.sql', '006_terms_acceptance.sql'] as $file) {
    $schema->exec(file_get_contents($root . '/migrations/' . $file) ?: '');
}
$schema->exec("INSERT INTO platform.users (first_name, last_name, email, password_hash) VALUES ('ძველი', 'მომხმარებელი', 'legacy@example.com', 'x')");
$schema->exec(file_get_contents($root . '/migrations/007_user_roles.sql') ?: '');
$legacyRole = (string) $schema->query("SELECT role FROM platform.users WHERE email = 'legacy@example.com'")->fetchColumn();
$default = (string) $schema->query("SELECT column_default FROM information_schema.columns WHERE table_schema = 'platform' AND table_name = 'users' AND column_name = 'role'")->fetchColumn();
$admins = (int) $schema->query("SELECT count(*) FROM platform.users WHERE role = 'admin'")->fetchColumn();
$rejected = false;
try {
    $schema->exec("UPDATE platform.users SET role = 'root' WHERE email = 'legacy@example.com'");
} catch (PDOException $e) {
    $rejected = $e->getCode() === '23514';
}
$stillUser = (string) $schema->query("SELECT role FROM platform.users WHERE email = 'legacy@example.com'")->fetchColumn();

$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'ok ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures[] = $name;
    }
};
$check($legacyRole === 'user' && $stillUser === 'user', 'existing user remains user');
$check(str_contains($default, 'user'), 'default role is user');
$check($admins === 0, 'migration grants no admin');
$check($rejected, 'invalid role rejected');

$admin->exec('CREATE DATABASE carapi_admin_auth_test');
$migrate = proc_open(
    [PHP_BINARY, $root . '/bin/migrate.php'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $root,
    [
        'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1',
        'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
        'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_admin_auth_test',
        'CARAPI_PLATFORM_ADMIN_USER' => 'postgres',
        'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
    ]
);
$migrated = stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
$check(proc_close($migrate) === 0 && str_contains((string) $migrated, 'applied 007_user_roles'), 'isolated migration applies');

$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_admin_auth_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_admin_auth_test TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE ON TABLE platform.users TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, DELETE ON TABLE platform.login_attempts TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE ON TABLE platform.projects, platform.api_keys, platform.email_tokens, platform.mail_limits TO carapi_platform_app');
$db->exec('GRANT SELECT ON TABLE platform.monthly_usage TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE ON TABLE platform.project_limits, platform.widgets TO carapi_platform_app');
$db->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');

$sessionPath = sys_get_temp_dir() . '/carapi-admin-sessions';
$port = 18120;
$env = [
    'CARAPI_PLATFORM_ENABLED' => '1',
    'CARAPI_PLATFORM_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_PORT' => '5432',
    'CARAPI_PLATFORM_DATABASE' => 'carapi_admin_auth_test',
    'CARAPI_PLATFORM_USER' => 'carapi_platform_app',
    'CARAPI_PLATFORM_PASSWORD' => $secret,
    'CARAPI_PLATFORM_SESSION_PATH' => $sessionPath,
    'CARAPI_CATALOG_ENABLED' => '0',
    'CARAPI_MAIL_TRANSPORT' => 'fake',
    'CARAPI_APP_URL' => 'http://127.0.0.1:' . $port,
];
$proc = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'],
    [1 => ['file', '/tmp/carapi-admin-auth.log', 'w'], 2 => ['file', '/tmp/carapi-admin-auth.err', 'w']],
    $unused,
    $root,
    $env
);
$base = 'http://127.0.0.1:' . $port;
$http = static function (string $method, string $path, array $fields = [], ?string $cookie = null, array $headers = []) use (&$base): array {
    $ch = curl_init($base . $path);
    $req = $headers;
    if ($cookie !== null) {
        $req[] = 'Cookie: carapi_platform=' . $cookie;
    }
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $req,
        CURLOPT_TIMEOUT => 10,
    ];
    if ($method === 'POST') {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($fields);
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $header = substr($raw, 0, $headerSize);
    preg_match_all('/^Set-Cookie:\s*carapi_platform=([^;]+)/mi', $header, $cookies);

    return ['status' => $status, 'header' => $header, 'body' => substr($raw, $headerSize), 'cookie' => $cookies[1][0] ?? $cookie];
};
$ready = false;
for ($i = 0; $i < 30; $i++) {
    if ($http('GET', '/login')['status'] !== 0) {
        $ready = true;
        break;
    }
    usleep(100000);
}
$boot = is_file('/tmp/carapi-admin-auth.err') ? (string) file_get_contents('/tmp/carapi-admin-auth.err') : '';
if (!$ready || str_contains($boot, 'Address already in use') || str_contains($boot, 'Failed to listen')) {
    fwrite(STDERR, "admin test server did not bind\n");
    proc_terminate($proc);
    proc_close($proc);
    exit(1);
}

$password = 'ქართული-პაროლი-12345';
$register = static function (string $email) use ($http, $password): array {
    $page = $http('GET', '/register');
    preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $csrf);

    return $http('POST', '/register', [
        '_csrf' => $csrf[1] ?? '',
        'first_name' => 'ნინო',
        'last_name' => 'ბერიძე',
        'email' => $email,
        'password' => $password,
        'password_confirmation' => $password,
        'accept_terms' => '1',
        'role' => 'admin',
    ], $page['cookie']);
};
$nino = $register('nino-admin@example.com');
$roleAfterRegister = (string) $db->query("SELECT role FROM platform.users WHERE email = 'nino-admin@example.com'")->fetchColumn();
$check($nino['status'] === 302 && $roleAfterRegister === 'user', 'registration cannot set admin');

$guest = $http('GET', '/admin');
$check($guest['status'] === 302 && str_contains($guest['header'], 'Location: /login') && !str_contains($guest['body'], 'ადმინისტრატორის პანელი'), 'guest cannot access admin');

$userAdmin = $http('GET', '/admin', [], $nino['cookie']);
$check($userAdmin['status'] === 404 && !str_contains($userAdmin['body'], 'ადმინისტრატორის პანელი'), 'authenticated user receives 404');
$sessionFile = $sessionPath . '/sess_' . rawurldecode((string) $nino['cookie']);
$session = is_file($sessionFile) ? (string) file_get_contents($sessionFile) : '';
$check($session !== '' && !str_contains($session, 'role'), 'role is not stored in session');

$me = $http('GET', '/auth/me', [], $nino['cookie'], ['Accept: application/json']);
$check($me['status'] === 200 && !str_contains($me['body'], 'password_hash') && !str_contains($me['body'], '"role"'), 'auth me hides role and password');

$db->exec("UPDATE platform.users SET status = 'suspended' WHERE email = 'nino-admin@example.com'");
$suspended = $http('GET', '/admin', [], $nino['cookie']);
$check($suspended['status'] === 302 && str_contains($suspended['header'], 'Location: /login'), 'suspended user cannot access admin');
$db->exec("UPDATE platform.users SET status = 'active' WHERE email = 'nino-admin@example.com'");

$login = static function (string $email, array $extra = []) use ($http, $password): array {
    $page = $http('GET', '/login');
    preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $csrf);

    return $http('POST', '/login', $extra + ['_csrf' => $csrf[1] ?? '', 'email' => $email, 'password' => $password], $page['cookie']);
};
$signed = $login('nino-admin@example.com', ['role' => 'admin']);
$roleAfterLogin = (string) $db->query("SELECT role FROM platform.users WHERE email = 'nino-admin@example.com'")->fetchColumn();
$check($signed['status'] === 302 && str_contains($signed['header'], 'Location: /account') && $roleAfterLogin === 'user', 'login cannot set admin');

$posted = $http('POST', '/admin', ['role' => 'admin', 'action' => 'promote'], $signed['cookie']);
$check($posted['status'] === 404 && $roleAfterLogin === (string) $db->query("SELECT role FROM platform.users WHERE email = 'nino-admin@example.com'")->fetchColumn(), 'client input cannot set admin');

$bia = $register('bia-admin@example.com');
$check((string) $db->query("SELECT role FROM platform.users WHERE email = 'bia-admin@example.com'")->fetchColumn() === 'user', 'second registration stays user');

$db->exec("UPDATE platform.users SET role = 'admin' WHERE email = 'nino-admin@example.com'");
$adminLogin = $login('nino-admin@example.com');
$check($adminLogin['status'] === 302 && str_contains($adminLogin['header'], 'Location: /admin'), 'admin login opens the admin panel');
$skip = $http('GET', '/login', [], $adminLogin['cookie']);
$check($skip['status'] === 302 && str_contains($skip['header'], 'Location: /admin'), 'signed-in admin skips the login form');
$opened = $http('GET', '/admin', [], $signed['cookie']);
$check(
    $opened['status'] === 200
    && str_contains($opened['body'], 'CarAPI ადმინი')
    && str_contains($opened['body'], 'მიმოხილვა')
    && str_contains($opened['body'], 'მართვის ფუნქციები დაემატება შემდეგ ეტაპებზე.')
    && str_contains($opened['body'], 'bia-admin@example.com')
    && !str_contains($opened['body'], 'secret_hash')
    && !str_contains($opened['body'], 'carapi_sk_')
    && !str_contains($opened['body'], 'password_hash'),
    'admin can access placeholder without sensitive data'
);

$counts = static function (PDO $pdo): string {
    return (string) $pdo->query('SELECT (SELECT count(*) FROM platform.users) || \':\' || (SELECT count(*) FROM platform.projects) || \':\' || (SELECT count(*) FROM platform.api_keys)')->fetchColumn();
};
$before = $counts($db);
$action = $http('POST', '/admin', ['role' => 'user', 'action' => 'suspend'], $signed['cookie']);
$check($action['status'] === 405 && str_contains($action['header'], 'Allow: GET') && $counts($db) === $before && (string) $db->query("SELECT role FROM platform.users WHERE email = 'nino-admin@example.com'")->fetchColumn() === 'admin', 'post admin performs no action');

$db->exec("UPDATE platform.users SET role = 'user' WHERE email = 'nino-admin@example.com'");
$downgraded = $http('GET', '/admin', [], $signed['cookie']);
$check($downgraded['status'] === 404 && !str_contains($downgraded['body'], 'ადმინისტრატორის პანელი'), 'role downgrade removes access');

$db->exec("UPDATE platform.users SET role = 'admin' WHERE email = 'nino-admin@example.com'");
$upgraded = $http('GET', '/admin', [], $signed['cookie']);
$check($upgraded['status'] === 200 && $signed['cookie'] === $upgraded['cookie'], 'role upgrade is recognized without a new session');
$db->exec("UPDATE platform.users SET role = 'user' WHERE email = 'nino-admin@example.com'");

$page = $http('GET', '/account', [], $signed['cookie']);
preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $csrf);
$made = $http('POST', '/account', ['_csrf' => $csrf[1] ?? '', 'action' => 'create_project', 'name' => 'ნინოს პროექტი'], $signed['cookie']);
$list = $http('GET', '/account', [], $signed['cookie']);
preg_match('#/account/projects/(\d+)#', $list['body'], $project);
$foreign = $http('GET', '/account/projects/' . ($project[1] ?? '0'), [], $bia['cookie']);
$biaPage = $http('GET', '/account', [], $bia['cookie']);
$check($made['status'] === 302 && $foreign['status'] === 404 && !str_contains($biaPage['body'], 'ნინოს პროექტი'), 'project ownership unchanged');

$guestPost = $http('POST', '/admin', ['role' => 'admin']);
$check($guestPost['status'] === 302 && str_contains($guestPost['header'], 'Location: /login'), 'guest post does not act');

proc_terminate($proc);
proc_close($proc);

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "admin auth ok\n";
