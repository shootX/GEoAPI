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
if ($secret === '') {
    fwrite(STDERR, "platform env missing\n");
    exit(1);
}

$admin = new PDO('pgsql:host=127.0.0.1;dbname=postgres', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('DROP DATABASE IF EXISTS carapi_platform_test WITH (FORCE)');
$admin->exec('CREATE DATABASE carapi_platform_test');
$migrate = proc_open(
    [PHP_BINARY, $root . '/bin/migrate.php'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $root,
    [
        'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1',
        'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
        'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_platform_test',
        'CARAPI_PLATFORM_ADMIN_USER' => 'postgres',
        'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
    ]
);
stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($migrate) !== 0) {
    fwrite(STDERR, "migrate test db failed\n");
    exit(1);
}
$grant = new PDO('pgsql:host=127.0.0.1;dbname=carapi_platform_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$grant->exec('GRANT CONNECT ON DATABASE carapi_platform_test TO carapi_platform_app');
$grant->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$grant->exec('GRANT SELECT, INSERT, UPDATE ON TABLE platform.users TO carapi_platform_app');
$grant->exec('GRANT SELECT, INSERT, DELETE ON TABLE platform.login_attempts TO carapi_platform_app');
$grant->exec('GRANT SELECT, INSERT, UPDATE ON TABLE platform.projects, platform.api_keys, platform.email_tokens, platform.mail_limits TO carapi_platform_app');
$grant->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');

$env = [
    'CARAPI_PLATFORM_ENABLED' => '1',
    'CARAPI_PLATFORM_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_PORT' => '5432',
    'CARAPI_PLATFORM_DATABASE' => 'carapi_platform_test',
    'CARAPI_PLATFORM_USER' => 'carapi_platform_app',
    'CARAPI_PLATFORM_PASSWORD' => $secret,
    'CARAPI_PLATFORM_SESSION_PATH' => sys_get_temp_dir() . '/carapi-platform-sessions',
    'CARAPI_PLATFORM_ATTEMPT_LIMIT' => '3',
    'CARAPI_PLATFORM_IP_ATTEMPT_LIMIT' => '100',
    'CARAPI_PLATFORM_IDLE_SECONDS' => '1800',
    'CARAPI_PLATFORM_ABSOLUTE_SECONDS' => '43200',
    'CARAPI_CATALOG_ENABLED' => '0',
];
$port = 18091;
$proc = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'],
    [1 => ['file', '/tmp/platform-auth.log', 'w'], 2 => ['file', '/tmp/platform-auth.err', 'w']],
    $unused,
    $root,
    $env
);
$base = 'http://127.0.0.1:' . $port;
$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'ok ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures[] = $name;
    }
};
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

for ($i = 0; $i < 30; $i++) {
    if ($http('GET', '/login')['status'] !== 0) {
        break;
    }
    usleep(100000);
}

$password = '  ქართული-პაროლი-123';
$get = $http('GET', '/register');
preg_match('/name="_csrf" value="([^"]+)"/', $get['body'], $csrf0);
$withoutTerms = $http('POST', '/register', [
    '_csrf' => $csrf0[1] ?? '',
    'first_name' => 'ნინო',
    'last_name' => 'ბერიძე',
    'email' => 'User@Example.com',
    'password' => $password,
    'password_confirmation' => $password,
], $get['cookie']);
$check($withoutTerms['status'] === 422 && str_contains($withoutTerms['body'], 'წესებზე დათანხმება'), 'terms required');
$get = $http('GET', '/register');
preg_match('/name="_csrf" value="([^"]+)"/', $get['body'], $csrf);
$reg = $http('POST', '/register', [
    '_csrf' => $csrf[1] ?? '',
    'first_name' => 'ნინო',
    'last_name' => 'ბერიძე',
    'email' => 'User@Example.com',
    'password' => $password,
    'password_confirmation' => $password,
    'accept_terms' => '1',
], $get['cookie']);
$check($reg['status'] === 302 && str_contains($reg['header'], '/account'), 'register redirects');
$check($reg['cookie'] !== $get['cookie'], 'session id changes');
$check(!str_contains($reg['body'], $password), 'password not echoed');
$account = $http('GET', '/account', [], $reg['cookie']);
$check($account['status'] === 200 && str_contains($account['body'], 'ნინო') && str_contains($account['body'], 'user@example.com'), 'account shows user');
$check(!str_contains($account['body'], 'პირადი ნომერი') && !str_contains($account['body'], 'ფოტოს ძებნა'), 'no old photo fields');

$again = $http('GET', '/register');
preg_match('/name="_csrf" value="([^"]+)"/', $again['body'], $csrf2);
$dup = $http('POST', '/register', [
    '_csrf' => $csrf2[1] ?? '',
    'first_name' => 'ნინო',
    'last_name' => 'ბერიძე',
    'email' => 'USER@example.com',
    'password' => $password,
    'password_confirmation' => $password,
    'accept_terms' => '1',
], $again['cookie']);
$check($dup['status'] === 422 && str_contains($dup['body'], 'უკვე რეგისტრირებულია'), 'duplicate email case');

$loginPage = $http('GET', '/login');
preg_match('/name="_csrf" value="([^"]+)"/', $loginPage['body'], $csrf3);
$bad = $http('POST', '/login', ['_csrf' => $csrf3[1] ?? '', 'email' => 'missing@example.com', 'password' => $password], $loginPage['cookie']);
$bad2 = $http('POST', '/login', ['_csrf' => $csrf3[1] ?? '', 'email' => 'user@example.com', 'password' => 'wrong-password-value'], $loginPage['cookie']);
$check($bad['status'] === 401 && $bad2['status'] === 401 && str_contains($bad['body'], 'ელფოსტა ან პაროლი არასწორია') && str_contains($bad2['body'], 'ელფოსტა ან პაროლი არასწორია'), 'generic login error');
$noCsrf = $http('POST', '/login', ['email' => 'user@example.com', 'password' => $password], $loginPage['cookie']);
$check($noCsrf['status'] === 419, 'csrf required');

$fresh = $http('GET', '/login');
preg_match('/name="_csrf" value="([^"]+)"/', $fresh['body'], $csrf4);
$ok = $http('POST', '/login', ['_csrf' => $csrf4[1] ?? '', 'email' => ' user@example.com ', 'password' => $password], $fresh['cookie']);
$check($ok['status'] === 302, 'login with unicode password');
$me = $http('GET', '/auth/me', [], $ok['cookie'], ['Accept: application/json']);
$check($me['status'] === 200 && str_contains($me['body'], 'user@example.com') && !str_contains($me['body'], 'password_hash'), 'auth me');

$stay = $http('GET', '/logout', [], $ok['cookie']);
$still = $http('GET', '/account', [], $ok['cookie']);
$check($stay['status'] === 405 && $still['status'] === 200, 'get logout does not sign out');
$page = $http('GET', '/account', [], $ok['cookie']);
preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $csrf5);
$out = $http('POST', '/logout', ['_csrf' => $csrf5[1] ?? ''], $ok['cookie']);
$after = $http('GET', '/account', [], $out['cookie'] ?? $ok['cookie']);
$check($out['status'] === 302 && ($after['status'] === 302 || str_contains($after['header'], '/login')), 'logout blocks account');

$grant->exec("UPDATE platform.users SET status = 'suspended' WHERE lower(email) = 'user@example.com'");
$suspended = $http('GET', '/account', [], $ok['cookie']);
$check($suspended['status'] === 302 && str_contains($suspended['header'], '/login'), 'suspended session rejected');
$grant->exec("UPDATE platform.users SET status = 'active' WHERE lower(email) = 'user@example.com'");

for ($n = 0; $n < 3; $n++) {
    $jar = $http('GET', '/login');
    preg_match('/name="_csrf" value="([^"]+)"/', $jar['body'], $token);
    $http('POST', '/login', ['_csrf' => $token[1] ?? '', 'email' => 'user@example.com', 'password' => 'wrong-password-value'], $jar['cookie']);
}
$limited = $http('GET', '/login');
preg_match('/name="_csrf" value="([^"]+)"/', $limited['body'], $token2);
$blocked = $http('POST', '/login', ['_csrf' => $token2[1] ?? '', 'email' => 'user@example.com', 'password' => $password], $limited['cookie']);
$check($blocked['status'] === 429, 'limit survives new cookie');

proc_terminate($proc);
proc_close($proc);

$env['CARAPI_PLATFORM_IDLE_SECONDS'] = '1';
$env['CARAPI_PLATFORM_SESSION_PATH'] = sys_get_temp_dir() . '/carapi-platform-sessions-2';
$port2 = 18092;
$proc2 = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port2, '-t', $root . '/public', $root . '/public/index.php'],
    [1 => ['file', '/tmp/platform-auth2.log', 'w'], 2 => ['file', '/tmp/platform-auth2.err', 'w']],
    $unused2,
    $root,
    $env
);
$base = 'http://127.0.0.1:' . $port2;
usleep(200000);
$start = $http('GET', '/login');
preg_match('/name="_csrf" value="([^"]+)"/', $start['body'], $token3);
$signed = $http('POST', '/login', ['_csrf' => $token3[1] ?? '', 'email' => 'user@example.com', 'password' => $password], $start['cookie']);
sleep(2);
$expired = $http('GET', '/account', [], $signed['cookie']);
$check($expired['status'] === 302, 'idle session expires');
proc_terminate($proc2);
proc_close($proc2);

$env['CARAPI_PLATFORM_PORT'] = '1';
$env['CARAPI_PLATFORM_CONNECT_TIMEOUT'] = '1';
$port3 = 18093;
$base = 'http://127.0.0.1:' . $port3;
$proc3 = proc_open(
    [PHP_BINARY, '-S', '127.0.0.1:' . $port3, '-t', $root . '/public', $root . '/public/index.php'],
    [1 => ['file', '/tmp/platform-auth3.log', 'w'], 2 => ['file', '/tmp/platform-auth3.err', 'w']],
    $unused3,
    $root,
    $env
);
usleep(200000);
$gate = $http('GET', '/login');
preg_match('/name="_csrf" value="([^"]+)"/', $gate['body'], $token4);
$down = $http('POST', '/login', ['_csrf' => $token4[1] ?? '', 'email' => 'user@example.com', 'password' => $password], $gate['cookie']);
$check($down['status'] === 503 && str_contains($down['body'], 'დროებით მიუწვდომელია') && !str_contains($down['body'], 'SQLSTATE'), 'database error is generic');
proc_terminate($proc3);
proc_close($proc3);
$check((is_file($sqlite) ? filemtime($sqlite) . ':' . filesize($sqlite) : 'absent') === $sqliteBefore, 'sqlite not created');
$live = (int) (new PDO('pgsql:host=127.0.0.1;dbname=carbase', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]))->query("SELECT count(*) FROM platform.users WHERE email LIKE '%@example.com'")->fetchColumn();
$check($live === 0, 'working carbase has no test users');
$admin->exec('DROP DATABASE IF EXISTS carapi_platform_test WITH (FORCE)');

if ($failures !== []) {
    fwrite(STDERR, count($failures) . " failed\n");
    exit(1);
}
echo "all passed\n";
