<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$secret = '';
$catalogSecret = '';
foreach (file('/www/server/carapi/platform.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'CARAPI_PLATFORM_PASSWORD=')) {
        $secret = substr($line, 26);
    }
}
foreach (file('/www/server/carapi/catalog.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'CARAPI_CATALOG_PASSWORD=')) {
        $catalogSecret = substr($line, 26);
    }
    if (str_starts_with($line, 'CARAPI_CATALOG_USER=')) {
        $catalogUser = substr($line, 20);
    }
}
require $root . '/vendor/autoload.php';
$admin = new PDO('pgsql:host=127.0.0.1;dbname=postgres', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('DROP DATABASE IF EXISTS carapi_usage_test WITH (FORCE)');
$admin->exec('CREATE DATABASE carapi_usage_test');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_usage_test', 'CARAPI_PLATFORM_ADMIN_USER' => 'postgres', 'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
]);
stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($migrate);
$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_usage_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_usage_test TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');
$hash = \Carapi\Platform\Passwords::hash('ქართული-პაროლი-12345');
$db->prepare('INSERT INTO platform.users (first_name, last_name, email, password_hash, email_verified_at) VALUES (:f,:l,:e,:p, now())')->execute(['f' => 'ანა', 'l' => 'ერთი', 'e' => 'ana-usage@example.com', 'p' => $hash]);
$db->prepare('INSERT INTO platform.users (first_name, last_name, email, password_hash, email_verified_at) VALUES (:f,:l,:e,:p, now())')->execute(['f' => 'ბია', 'l' => 'ორი', 'e' => 'bia-usage@example.com', 'p' => $hash]);
$ids = $db->query('SELECT id, email FROM platform.users ORDER BY id')->fetchAll();
$make = static function (int $userId, string $name) use ($db): array {
    $db->prepare('INSERT INTO platform.projects (user_id, name) VALUES (:id, :name)')->execute(['id' => $userId, 'name' => $name]);
    $projectId = (int) $db->query('SELECT id FROM platform.projects ORDER BY id DESC LIMIT 1')->fetchColumn();
    $db->prepare('INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota) VALUES (:id, 60, 5)')->execute(['id' => $projectId]);
    $issued = \Carapi\Platform\ApiKeys::issue();
    $db->prepare('INSERT INTO platform.api_keys (project_id, name, key_selector, secret_hash) VALUES (:p, :n, :s, :h)')->execute(['p' => $projectId, 'n' => $name, 's' => $issued['selector'], 'h' => $issued['hash']]);

    return ['project' => $projectId, 'token' => $issued['token']];
};
$a = $make((int) $ids[0]['id'], 'ა');
$a2 = \Carapi\Platform\ApiKeys::issue();
$db->prepare('INSERT INTO platform.api_keys (project_id, name, key_selector, secret_hash) VALUES (:p, :n, :s, :h)')->execute(['p' => $a['project'], 'n' => 'მეორე', 's' => $a2['selector'], 'h' => $a2['hash']]);
$b = $make((int) $ids[1]['id'], 'ბ');
$now = 1700000060;
$port = 18097;
$env = [
    'CARAPI_CATALOG_ENABLED' => '1', 'CARAPI_CATALOG_HOST' => '127.0.0.1', 'CARAPI_CATALOG_PORT' => '5432', 'CARAPI_CATALOG_DATABASE' => 'carbase',
    'CARAPI_CATALOG_USER' => $catalogUser ?? 'carapi_catalog_reader', 'CARAPI_CATALOG_PASSWORD' => $catalogSecret,
    'CARAPI_PLATFORM_ENABLED' => '1', 'CARAPI_PLATFORM_HOST' => '127.0.0.1', 'CARAPI_PLATFORM_PORT' => '5432',
    'CARAPI_PLATFORM_DATABASE' => 'carapi_usage_test', 'CARAPI_PLATFORM_USER' => 'carapi_platform_app', 'CARAPI_PLATFORM_PASSWORD' => $secret,
    'CARAPI_PLATFORM_SESSION_PATH' => '/tmp/carapi-usage-sessions', 'CARAPI_USAGE_NOW' => (string) $now, 'CARAPI_IP_PER_MINUTE' => '1000',
];
$base = 'http://127.0.0.1:' . $port;
$proc = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $root . '/public', $root . '/public/index.php'], [1 => ['file', '/tmp/usage.log', 'w'], 2 => ['file', '/tmp/usage.err', 'w']], $u, $root, $env);
$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'ok ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures[] = $name;
    }
};
$hit = static function (string $token, string $path = '/api/v1/brands?per_page=1') use ($base): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token], CURLOPT_TIMEOUT => 20]);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    return ['status' => $status, 'header' => substr($raw, 0, $size), 'body' => substr($raw, $size)];
};
usleep(300000);
$check($hit($a['token'])['status'] === 200 && $hit($a2['token'])['status'] === 200, 'two keys share project quota');
$used = (int) $db->query('SELECT used_count FROM platform.monthly_usage WHERE project_id = ' . $a['project'])->fetchColumn();
$check($used === 2, 'shared usage is 2');
$check($hit($b['token'])['status'] === 200, 'other project independent');
$other = (int) $db->query('SELECT used_count FROM platform.monthly_usage WHERE project_id = ' . $b['project'])->fetchColumn();
$check($other === 1, 'other project usage is 1');
$empty = $hit($a['token'], '/api/v1/models/999999999999/generations');
$check($empty['status'] === 404, 'unknown model stays 404');
$usedAfter404 = (int) $db->query('SELECT used_count FROM platform.monthly_usage WHERE project_id = ' . $a['project'])->fetchColumn();
$check($usedAfter404 === 2, '404 does not spend quota');
$db->exec('UPDATE platform.project_limits SET monthly_quota = 2 WHERE project_id = ' . $a['project']);
$last = $hit($a['token']);
$check($last['status'] === 429 && str_contains($last['body'], 'MONTHLY_QUOTA_EXCEEDED') && str_contains($last['header'], 'Retry-After:'), 'quota 429');
$still = (int) $db->query('SELECT used_count FROM platform.monthly_usage WHERE project_id = ' . $a['project'])->fetchColumn();
$check($still === 2, 'rejected quota does not increment');
$db->exec('DELETE FROM platform.monthly_usage WHERE project_id = ' . $a['project']);
$db->exec('UPDATE platform.project_limits SET monthly_quota = 1, per_minute = 100 WHERE project_id = ' . $a['project']);
$procs = [];
for ($i = 0; $i < 6; $i++) {
    $cmd = 'curl -s -o /tmp/usage-body-' . $i . ' -D /tmp/usage-head-' . $i . ' -H ' . escapeshellarg('Authorization: Bearer ' . $a['token']) . ' ' . escapeshellarg($base . '/api/v1/brands?per_page=1');
    $procs[] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pp);
}
$codes = [];
foreach ($procs as $i => $child) {
    if (is_resource($child)) {
        proc_close($child);
    }
    $head = (string) @file_get_contents('/tmp/usage-head-' . $i);
    preg_match('#HTTP/\S+\s+(\d+)#', $head, $m);
    $codes[] = (int) ($m[1] ?? 0);
}
$okCount = count(array_filter($codes, static fn (int $code): bool => $code === 200));
$check($okCount === 1 && in_array(429, $codes, true), 'parallel requests allow one success');
$parallelUsed = (int) $db->query('SELECT used_count FROM platform.monthly_usage WHERE project_id = ' . $a['project'])->fetchColumn();
$check($parallelUsed === 1, 'parallel usage stored once');
$today = gmmktime(0, 0, 0, (int) gmdate('n', $now), (int) gmdate('j', $now), (int) gmdate('Y', $now));
$start = gmdate('Y-m-d', $today - (29 * 86400));
$end = gmdate('Y-m-d', $today);
$outside = gmdate('Y-m-d', $today - (30 * 86400));
$db->prepare('UPDATE platform.monthly_usage SET used_count = 4 WHERE project_id = :id')->execute(['id' => $a['project']]);
$db->prepare('UPDATE platform.project_limits SET monthly_quota = 10, per_minute = 37 WHERE project_id = :id')->execute(['id' => $a['project']]);
$db->exec('DELETE FROM platform.daily_usage WHERE project_id IN (' . (int) $a['project'] . ',' . (int) $b['project'] . ')');
$seed = $db->prepare('INSERT INTO platform.daily_usage (project_id, day, route, used_count) VALUES (:id, :day, :route, :used)');
$evil = '<script>alert(1)</script>';
foreach ([
    [$outside, 'brands', 9],
    [$start, 'brands', 1],
    ['2023-11-01', 'models', 3],
    ['2023-11-01', 'widget:brands', 2],
    ['2023-11-02', $evil, 1],
    [$end, 'brands', 4],
    [$end, 'widget:brands', 2],
] as [$day, $route, $used]) {
    $seed->execute(['id' => $a['project'], 'day' => $day, 'route' => $route, 'used' => $used]);
}
$open = static function (string $path, string $cookie = '') use ($base): array {
    $ch = curl_init($base . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIE => $cookie === '' ? '' : 'carapi_platform=' . $cookie, CURLOPT_TIMEOUT => 20]);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $header = substr($raw, 0, $size);
    preg_match('/^Set-Cookie:\s*carapi_platform=([^;]+)/mi', $header, $cookieMatch);

    return ['status' => $status, 'header' => $header, 'body' => substr($raw, $size), 'cookie' => $cookieMatch[1] ?? $cookie];
};
$signIn = static function (string $email) use ($open, $base): array {
    $login = $open('/login');
    preg_match('/name="_csrf" value="([^"]+)"/', $login['body'], $csrf);
    $ch = curl_init($base . '/login');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['_csrf' => $csrf[1] ?? '', 'email' => $email, 'password' => 'ქართული-პაროლი-12345']),
        CURLOPT_COOKIE => 'carapi_platform=' . $login['cookie'],
        CURLOPT_TIMEOUT => 20,
    ]);
    $raw = (string) curl_exec($ch);
    $size = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $header = substr($raw, 0, $size);
    preg_match('/^Set-Cookie:\s*carapi_platform=([^;]+)/mi', $header, $cookieMatch);

    return ['cookie' => $cookieMatch[1] ?? $login['cookie'], 'body' => substr($raw, $size)];
};
$guest = $open('/account/projects/' . $a['project']);
$check($guest['status'] === 302 && str_contains($guest['header'], 'Location: /login'), 'usage dashboard requires a session');
$ana = $signIn('ana-usage@example.com');
$bia = $signIn('bia-usage@example.com');
$mine = $open('/account/projects/' . $a['project'], $ana['cookie']);
$usageAt = strpos($mine['body'], 'id="usage"');
$usage = $usageAt === false ? '' : substr($mine['body'], $usageAt, strpos($mine['body'], 'id="overview"') - $usageAt);
preg_match_all('/data-day="(\d{4}-\d{2}-\d{2})"/', $usage, $days);
$check($mine['status'] === 200 && count($days[1]) === 30 && $days[1][0] === $start && $days[1][29] === $end && !in_array($outside, $days[1], true), 'dashboard lists exactly 30 UTC days');
$check(str_contains($usage, 'data-day="2023-10-17"') && str_contains($usage, '<td>2023-10-17</td><td>0</td>'), 'missing UTC day is zero');
$check(str_contains($usage, '<td>' . $start . '</td><td>1</td>') && str_contains($usage, '<td>2023-11-01</td><td>5</td>') && str_contains($usage, '<td>' . $end . '</td><td>6</td>'), 'daily totals include every route');
$check(str_contains($usage, '<td class="mono">brands</td><td>5</td>') && str_contains($usage, '<td class="mono">widget:brands</td><td>4</td>') && strpos($usage, 'widget:brands') > strpos($usage, '>brands<'), 'widget routes stay separate and sort by usage');
$check(str_contains($mine['body'], 'id="usage-month-used">4<') && str_contains($mine['body'], 'მოთხოვნების ლიმიტი 10') && str_contains($mine['body'], 'id="usage-month-remaining">6<'), 'monthly counter is not the daily sum');
$check(str_contains($usage, 'წუთში 37 მოთხოვნა.'), 'minute limit comes from project limits');
$check(str_contains($usage, 'usage-chart') && str_contains($usage, 'usage-days') && str_contains($usage, 'usage-routes') && !str_contains($usage, 'tb2') && substr_count($usage, 'usage-col') === 30, 'mobile analytics markup is present');
$check(str_contains($usage, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($usage, $evil), 'route labels are escaped');
$check(!str_contains($mine['body'], $a['token']) && !str_contains($mine['body'], $a2['token']), 'dashboard does not reveal API keys');
$hidden = $open('/account/projects/' . $a['project'], $bia['cookie']);
$theirs = $open('/account/projects/' . $b['project'], $bia['cookie']);
$check($hidden['status'] === 404 && str_contains($theirs['body'], 'id="usage-empty"') && str_contains($theirs['body'], 'წუთში 60 მოთხოვნა.') && !str_contains($theirs['body'], 'widget:brands') && !str_contains($theirs['body'], 'alert(1)'), 'other project usage stays isolated');
$check(str_contains($theirs['body'], 'id="usage-month-used">1<') && str_contains($theirs['body'], 'id="usage-month-remaining">4<'), 'empty daily usage keeps the monthly counter');
proc_terminate($proc);
proc_close($proc);
$admin->exec('DROP DATABASE IF EXISTS carapi_usage_test WITH (FORCE)');
if ($failures !== []) {
    exit(1);
}
echo "all passed\n";
