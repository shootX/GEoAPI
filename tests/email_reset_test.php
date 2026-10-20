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
$mailFile = sys_get_temp_dir() . '/carapi-mail.log';
@unlink($mailFile);
$admin = new PDO('pgsql:host=127.0.0.1;dbname=postgres', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('DROP DATABASE IF EXISTS carapi_email_test WITH (FORCE)');
$admin->exec('CREATE DATABASE carapi_email_test');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_email_test', 'CARAPI_PLATFORM_ADMIN_USER' => 'postgres', 'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
]);
stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($migrate);
$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_email_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_email_test TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');
$port = 18096;
$env = [
    'CARAPI_PLATFORM_ENABLED' => '1', 'CARAPI_PLATFORM_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_PORT' => '5432',
    'CARAPI_PLATFORM_DATABASE' => 'carapi_email_test', 'CARAPI_PLATFORM_USER' => 'carapi_platform_app', 'CARAPI_PLATFORM_PASSWORD' => $secret,
    'CARAPI_PLATFORM_SESSION_PATH' => '/tmp/carapi-email-sessions', 'CARAPI_CATALOG_ENABLED' => '0',
    'CARAPI_MAIL_TRANSPORT' => 'fake', 'CARAPI_MAIL_FAKE_PATH' => $mailFile, 'CARAPI_APP_URL' => 'http://127.0.0.1:' . $port,
];
$base = 'http://127.0.0.1:' . $port;
$proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'], [1 => ['file', '/tmp/email.log', 'w'], 2 => ['file', '/tmp/email.err', 'w']], $u, $root, $env);
$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'ok ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures[] = $name;
    }
};
$http = static function (string $method, string $path, array $fields = [], ?string $cookie = null, array $extra = []) use (&$base): array {
    $ch = curl_init($base . $path);
    $headers = array_merge($cookie ? ['Cookie: carapi_platform=' . $cookie] : [], $extra);
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
    preg_match('/^Set-Cookie:\s*carapi_platform=([^;]+)/mi', $header, $match);

    return ['status' => $status, 'header' => $header, 'body' => substr($raw, $size), 'cookie' => $match[1] ?? $cookie];
};
usleep(250000);
$password = 'ქართული-პაროლი-12345';
$page = $http('GET', '/register', [], null, ['Host: evil.example']);
preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $csrf);
$http('POST', '/register', ['_csrf' => $csrf[1] ?? '', 'first_name' => 'ანა', 'last_name' => 'ერთი', 'email' => 'ana@example.com', 'password' => $password, 'password_confirmation' => $password, 'accept_terms' => '1'], $page['cookie'], ['Host: evil.example']);
$mail = str_replace('\\/', '/', is_file($mailFile) ? (string) file_get_contents($mailFile) : '');
preg_match('#email/verify\?token=([A-Za-z0-9_-]+)#', $mail, $token);
$check(str_contains($mail, 'ana@example.com') && isset($token[1]) && $token[1] !== '' && !str_contains($mail, 'evil.example'), 'register mail uses app url');
$before = (string) $db->query("SELECT used_at IS NULL FROM platform.email_tokens WHERE purpose = 'verify_email'")->fetchColumn();
$get = $http('GET', '/email/verify?token=' . $token[1]);
$afterGet = (string) $db->query("SELECT used_at IS NULL FROM platform.email_tokens WHERE purpose = 'verify_email' AND revoked_at IS NULL")->fetchColumn();
$check($before === '1' && $afterGet === '1' && $get['status'] === 200, 'get does not consume token');
preg_match('/name="_csrf" value="([^"]+)"/', $get['body'], $csrf2);
$noCsrf = $http('POST', '/email/verify', ['token' => $token[1]], $get['cookie']);
$check($noCsrf['status'] === 200 && str_contains($noCsrf['body'], 'სესია ამოიწურა'), 'verify csrf required');
$ok = $http('POST', '/email/verify', ['_csrf' => $csrf2[1] ?? '', 'token' => $token[1]], $get['cookie']);
$verified = $db->query("SELECT email_verified_at IS NOT NULL FROM platform.users WHERE email = 'ana@example.com'")->fetchColumn();
$check(str_contains($ok['body'], 'დადასტურდა') && $verified === true, 'email verified without auto login');
$used = $http('POST', '/email/verify', ['_csrf' => $csrf2[1] ?? '', 'token' => $token[1]], $get['cookie']);
$check(str_contains($used['body'], 'არასწორია'), 'used token rejected');
$login = $http('GET', '/login');
preg_match('/name="_csrf" value="([^"]+)"/', $login['body'], $csrf3);
$signed = $http('POST', '/login', ['_csrf' => $csrf3[1] ?? '', 'email' => 'ana@example.com', 'password' => $password], $login['cookie']);
$account = $http('GET', '/account', [], $signed['cookie']);
preg_match('/name="_csrf" value="([^"]+)"/', $account['body'], $csrf4);
$http('POST', '/account', ['_csrf' => $csrf4[1] ?? '', 'action' => 'create_project', 'name' => 'პროექტი'], $signed['cookie']);
$list = $http('GET', '/account', [], $signed['cookie']);
preg_match('#/account/projects/(\d+)#', $list['body'], $pid);
preg_match('/name="_csrf" value="([^"]+)"/', $http('GET', '/account/projects/' . ($pid[1] ?? '0'), [], $signed['cookie'])['body'], $csrf5);
$keyPage = $http('POST', '/account/projects/' . ($pid[1] ?? '0'), ['_csrf' => $csrf5[1] ?? '', 'action' => 'create_key', 'name' => 'სერვერი'], $signed['cookie']);
$check(str_contains($keyPage['body'], 'carapi_sk_'), 'verified user creates key');
$keysBefore = (int) $db->query('SELECT count(*) FROM platform.api_keys')->fetchColumn();
$missingPage = $http('GET', '/forgot-password');
preg_match('/name="_csrf" value="([^"]+)"/', $missingPage['body'], $csrfMissing);
$forgotMissing = $http('POST', '/forgot-password', ['_csrf' => $csrfMissing[1] ?? '', 'email' => 'missing@example.com'], $missingPage['cookie']);
$knownPage = $http('GET', '/forgot-password');
preg_match('/name="_csrf" value="([^"]+)"/', $knownPage['body'], $csrf6);
$forgotKnown = $http('POST', '/forgot-password', ['_csrf' => $csrf6[1] ?? '', 'email' => 'ana@example.com'], $knownPage['cookie']);
$check($forgotMissing['status'] === $forgotKnown['status'] && str_contains($forgotMissing['body'], 'თუ მითითებული ელფოსტით') && str_contains($forgotKnown['body'], 'თუ მითითებული ელფოსტით'), 'same reset response');
$mail = str_replace('\\/', '/', (string) file_get_contents($mailFile));
preg_match_all('#reset-password\?token=([A-Za-z0-9_-]+)#', $mail, $resets);
$resetToken = $resets[1][array_key_last($resets[1])] ?? '';
$form = $http('GET', '/reset-password?token=' . $resetToken);
preg_match('/name="_csrf" value="([^"]+)"/', $form['body'], $csrf7);
$newPassword = 'ახალი-პაროლი-123456';
$reset = $http('POST', '/reset-password', ['_csrf' => $csrf7[1] ?? '', 'token' => $resetToken, 'password' => $newPassword, 'password_confirmation' => $newPassword], $form['cookie']);
$check(str_contains($reset['body'], 'პაროლი შეიცვალა'), 'password changed');
$old = $http('GET', '/account', [], $signed['cookie']);
$check($old['status'] === 302, 'old session rejected');
$keysAfter = (int) $db->query('SELECT count(*) FROM platform.api_keys WHERE revoked_at IS NULL')->fetchColumn();
$status = (string) $db->query("SELECT status FROM platform.users WHERE email = 'ana@example.com'")->fetchColumn();
$check($keysAfter === $keysBefore && $status === 'active', 'keys and status kept');
$check((is_file($sqlite) ? filemtime($sqlite) . ':' . filesize($sqlite) : 'absent') === $sqliteBefore, 'sqlite unchanged');
$live = (int) (new PDO('pgsql:host=127.0.0.1;dbname=carbase', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]))->query('SELECT count(*) FROM platform.users')->fetchColumn();
$check($live === 0, 'working users unchanged');
proc_terminate($proc);
proc_close($proc);
$admin->exec('DROP DATABASE IF EXISTS carapi_email_test WITH (FORCE)');
@unlink($mailFile);
if ($failures !== []) {
    exit(1);
}
echo "all passed\n";
