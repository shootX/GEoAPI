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
    $admin->exec('DROP DATABASE IF EXISTS carapi_admin_users_test WITH (FORCE)');
};
$drop();
register_shutdown_function($drop);
$admin->exec('CREATE DATABASE carapi_admin_users_test');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_admin_users_test',
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

$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_admin_users_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_admin_users_test TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE ON TABLE platform.users, platform.projects, platform.api_keys, platform.email_tokens, platform.mail_limits, platform.monthly_usage TO carapi_platform_app');
$db->exec('GRANT SELECT ON TABLE platform.project_limits TO carapi_platform_app');
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
$marker = 'password-hash-marker-8f3a';
$insert = $db->prepare('INSERT INTO platform.users (first_name, last_name, email, password_hash, status, email_verified_at, last_login_at, created_at, role) VALUES (:f,:l,:e,:p,:s,:v,:login,:created,:r) RETURNING id');
$insert->execute(['f' => 'ადმინი', 'l' => 'ტესტი', 'e' => 'admin-users@example.com', 'p' => $hash, 's' => 'active', 'v' => '2026-10-01 00:00:00+00', 'login' => '2026-10-18 12:00:00+00', 'created' => '2026-10-18 00:00:00+00', 'r' => 'admin']);
$adminId = (int) $insert->fetchColumn();
$insert->execute(['f' => 'Ana', 'l' => 'Beridze', 'e' => 'ana-users@example.com', 'p' => $hash, 's' => 'active', 'v' => '2026-10-02 00:00:00+00', 'login' => '2026-10-17 08:00:00+00', 'created' => '2026-10-17 00:00:00+00', 'r' => 'user']);
$ana = (int) $insert->fetchColumn();
$insert->execute(['f' => 'გია', 'l' => 'შეჩერებული', 'e' => 'gia-users@example.com', 'p' => $hash, 's' => 'suspended', 'v' => null, 'login' => null, 'created' => '2026-10-16 00:00:00+00', 'r' => 'user']);
$gia = (int) $insert->fetchColumn();
$insert->execute(['f' => '<b>ნინო</b>', 'l' => 'ტესტი', 'e' => 'nina-users@example.com', 'p' => $marker, 's' => 'active', 'v' => null, 'login' => null, 'created' => '2026-10-15 00:00:00+00', 'r' => 'user']);
$nina = (int) $insert->fetchColumn();
$same = $db->prepare('INSERT INTO platform.users (first_name, last_name, email, password_hash, created_at, role) VALUES (:f,:l,:e,:p,:c,\'user\') RETURNING id');
$same->execute(['f' => 'პირველი', 'l' => 'თანმიმდევრობა', 'e' => 'order-a@example.com', 'p' => $marker, 'c' => '2026-08-01 00:00:00+00']);
$orderA = (int) $same->fetchColumn();
$same->execute(['f' => 'მეორე', 'l' => 'თანმიმდევრობა', 'e' => 'order-b@example.com', 'p' => $marker, 'c' => '2026-08-01 00:00:00+00']);
$orderB = (int) $same->fetchColumn();
for ($n = 1; $n <= 20; $n++) {
    $insert->execute([
        'f' => 'ფილერი',
        'l' => (string) $n,
        'e' => sprintf('page-user-%02d@example.com', $n),
        'p' => $marker,
        's' => 'active',
        'v' => null,
        'login' => null,
        'created' => sprintf('2026-01-%02d 00:00:00+00', $n),
        'r' => 'user',
    ]);
}

$project = $db->prepare('INSERT INTO platform.projects (user_id, name, status) VALUES (:u,:n,:s) RETURNING id');
$limits = $db->prepare('INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota) VALUES (:id, 60, :quota)');
$usage = $db->prepare('INSERT INTO platform.monthly_usage (project_id, period, used_count) VALUES (:id,:period,:used)');
$project->execute(['u' => $ana, 'n' => 'ანას პირველი', 's' => 'active']);
$anaFirst = (int) $project->fetchColumn();
$limits->execute(['id' => $anaFirst, 'quota' => 500]);
$usage->execute(['id' => $anaFirst, 'period' => '2026-10-01', 'used' => 40]);
$usage->execute(['id' => $anaFirst, 'period' => '2026-09-01', 'used' => 99]);
$project->execute(['u' => $ana, 'n' => 'ანას მეორე', 's' => 'disabled']);
$anaSecond = (int) $project->fetchColumn();
$limits->execute(['id' => $anaSecond, 'quota' => 10000]);
$project->execute(['u' => $gia, 'n' => 'გიას საიდუმლო', 's' => 'active']);
$giaProject = (int) $project->fetchColumn();
$limits->execute(['id' => $giaProject, 'quota' => 10000]);
$usage->execute(['id' => $giaProject, 'period' => '2026-10-01', 'used' => 3]);
$project->execute(['u' => $nina, 'n' => "o'hara & <script>", 's' => 'active']);
$ninaProject = (int) $project->fetchColumn();
$limits->execute(['id' => $ninaProject, 'quota' => 10000]);
$db->prepare('INSERT INTO platform.api_keys (project_id, name, key_selector, secret_hash) VALUES (:p,:n,:s,:h)')
    ->execute(['p' => $anaFirst, 'n' => 'საიდუმლო', 's' => 'carapi_sk_marker8f3a', 'h' => 'secret-hash-marker-8f3a']);

$now = gmmktime(12, 0, 0, 10, 15, 2026);
$directory = new Carapi\Platform\AdminUsers($db);
$all = $directory->search([]);
$check($all['total'] === 26 && $all['per'] === 20 && $all['page'] === 1 && count($all['rows']) === 20, 'default page size and total');
$check($all['rows'][0]['id'] === $adminId && $all['rows'][0]['projects'] === 0 && $all['rows'][1]['projects'] === 2, 'project counts follow registration order');
$wide = $directory->search(['per' => '500']);
$check($wide['per'] === 100 && $wide['total'] === 26, 'page size is capped at 100');
$ids = array_column($wide['rows'], 'id');
$posA = array_search($orderA, $ids, true);
$posB = array_search($orderB, $ids, true);
$check($posB !== false && $posA !== false && $posB < $posA, 'equal timestamps keep the higher id first');
$previous = null;
$stable = true;
foreach ($wide['rows'] as $row) {
    if ($previous !== null && ($row['created_at'] > $previous['created_at'] || ($row['created_at'] === $previous['created_at'] && $row['id'] > $previous['id']))) {
        $stable = false;
    }
    $previous = $row;
}
$check($stable, 'ordering is created time then id');
$emailHit = $directory->search(['q' => 'ANA-USERS@EXAMPLE.COM']);
$check($emailHit['total'] === 1 && $emailHit['rows'][0]['id'] === $ana, 'email search is case-insensitive');
$nameHit = $directory->search(['q' => 'beridze']);
$check($nameHit['total'] === 1 && $nameHit['rows'][0]['email'] === 'ana-users@example.com', 'name search is case-insensitive');
$giaHit = $directory->search(['q' => 'შეჩერებული']);
$check($giaHit['total'] === 1 && $giaHit['rows'][0]['id'] === $gia, 'search matches a last name');
$check($directory->search(['q' => '%'])['total'] === 0, 'a percent sign is not a wildcard');
$check($directory->search(['status' => 'suspended'])['total'] === 1 && $directory->search(['status' => 'active'])['total'] === 25, 'status filter');
$check($directory->search(['role' => 'admin'])['total'] === 1 && $directory->search(['role' => 'user'])['total'] === 25, 'role filter');
$check($directory->search(['status' => 'deleted', 'role' => 'root'])['total'] === 26, 'invalid filters are ignored');
$page2 = $directory->search(['page' => '2']);
$check($page2['page'] === 2 && count($page2['rows']) === 6 && $page2['rows'][5]['email'] === 'page-user-01@example.com', 'second page keeps the oldest rows');
$anaDetail = $directory->find($ana, $now);
$check($anaDetail !== null && count($anaDetail['projects']) === 2 && $anaDetail['projects'][0]['used'] === 40 && $anaDetail['projects'][0]['limit'] === 500 && $anaDetail['projects'][0]['remaining'] === 460 && $anaDetail['projects'][1]['used'] === 0 && $anaDetail['projects'][1]['remaining'] === 10000, 'monthly usage ignores the previous month');
$giaDetail = $directory->find($gia, $now);
$check($giaDetail !== null && count($giaDetail['projects']) === 1 && $giaDetail['projects'][0]['id'] === $giaProject && $anaDetail['projects'][0]['id'] === $anaFirst, 'projects stay with their owner');
$check($directory->find(0, $now) === null && $directory->find(999999, $now) === null, 'missing users are empty');

$sessionPath = sys_get_temp_dir() . '/carapi-admin-users-sessions';
$port = 18144;
$env = [
    'CARAPI_PLATFORM_ENABLED' => '1',
    'CARAPI_PLATFORM_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_PORT' => '5432',
    'CARAPI_PLATFORM_DATABASE' => 'carapi_admin_users_test',
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
    [1 => ['file', '/tmp/carapi-admin-users.log', 'w'], 2 => ['file', '/tmp/carapi-admin-users.err', 'w']],
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
$boot = is_file('/tmp/carapi-admin-users.err') ? (string) file_get_contents('/tmp/carapi-admin-users.err') : '';
if (!$ready || str_contains($boot, 'Failed to listen')) {
    fwrite(STDERR, "users test server did not bind\n");
    proc_terminate($proc);
    proc_close($proc);
    exit(1);
}

$login = static function (string $email) use ($http): array {
    $page = $http('GET', '/login');
    preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $csrf);

    return $http('POST', '/login', ['_csrf' => $csrf[1] ?? '', 'email' => $email, 'password' => 'ქართული-პაროლი-12345', 'role' => 'admin'], $page['cookie']);
};
$guest = $http('GET', '/admin/users');
$check($guest['status'] === 302 && str_contains($guest['header'], 'Location: /login') && !str_contains($guest['body'], 'ana-users@example.com'), 'guest cannot access user list');
$anaLogin = $login('ana-users@example.com');
$ordinary = $http('GET', '/admin/users', [], $anaLogin['cookie']);
$check($ordinary['status'] === 404 && !str_contains($ordinary['body'], 'ana-users@example.com'), 'ordinary user receives 404');
$signed = $login('admin-users@example.com');
$list = $http('GET', '/admin/users', [], $signed['cookie']);
$check($list['status'] === 200 && str_contains($list['body'], 'data-total="26"') && str_contains($list['body'], 'href="/admin/users/' . $ana . '"') && str_contains($list['body'], 'aria-current="page"'), 'admin can view user list');
$detail = $http('GET', '/admin/users/' . $ana, [], $signed['cookie']);
$check($detail['status'] === 200 && str_contains($detail['body'], 'data-user="' . $ana . '"') && str_contains($detail['body'], 'data-used="40"') && str_contains($detail['body'], 'data-limit="500"') && str_contains($detail['body'], 'data-remaining="460"') && str_contains($detail['body'], 'href="/admin/users"'), 'admin can view user details');
$check($http('GET', '/admin/users/0', [], $signed['cookie'])['status'] === 404 && $http('GET', '/admin/users/nope', [], $signed['cookie'])['status'] === 404, 'invalid user id returns 404');
$check($http('GET', '/admin/users/999999', [], $signed['cookie'])['status'] === 404, 'missing user returns 404');
$found = $http('GET', '/admin/users?q=' . rawurlencode('ANA-USERS@EXAMPLE.COM'), [], $signed['cookie']);
$check($found['status'] === 200 && str_contains($found['body'], 'data-total="1"') && str_contains($found['body'], 'ana-users@example.com'), 'rendered email search');
$byName = $http('GET', '/admin/users?q=' . rawurlencode('შეჩერებული'), [], $signed['cookie']);
$check(str_contains($byName['body'], 'gia-users@example.com') && !str_contains($byName['body'], 'ana-users@example.com'), 'rendered name search');
$active = $http('GET', '/admin/users?status=active', [], $signed['cookie']);
$suspendedOnly = $http('GET', '/admin/users?status=suspended', [], $signed['cookie']);
$check(str_contains($active['body'], 'data-total="25"') && !str_contains($active['body'], 'gia-users@example.com') && str_contains($suspendedOnly['body'], 'data-total="1"'), 'rendered status filter');
$admins = $http('GET', '/admin/users?role=admin', [], $signed['cookie']);
$check(str_contains($admins['body'], 'data-total="1"') && str_contains($admins['body'], 'admin-users@example.com') && !str_contains($admins['body'], 'ana-users@example.com'), 'rendered role filter');
$next = $http('GET', '/admin/users?status=active&page=2', [], $signed['cookie']);
$check(str_contains($next['body'], 'data-page="2"') && str_contains($next['body'], 'status=active') && str_contains($next['body'], 'page-user-01@example.com'), 'pagination keeps the filter');
$escaped = $http('GET', '/admin/users/' . $nina, [], $signed['cookie']);
$check(str_contains($list['body'], '&lt;b&gt;ნინო&lt;/b&gt;') && !str_contains($list['body'], '<b>ნინო</b>') && str_contains($escaped['body'], 'o&#039;hara &amp; &lt;script&gt;') && !str_contains($escaped['body'], "o'hara & <script>"), 'html is escaped');
$hidden = $list['body'] . $detail['body'] . $escaped['body'];
$check(!str_contains($hidden, $marker) && !str_contains($hidden, 'password_hash') && !str_contains($hidden, 'secret-hash-marker-8f3a') && !str_contains($hidden, 'carapi_sk_marker8f3a'), 'hashes and key secrets stay hidden');
$check(!str_contains($detail['body'], 'გიას საიდუმლო') && str_contains($detail['body'], 'ანას პირველი'), 'detail does not leak another user project');
$before = (int) $db->query('SELECT count(*) FROM platform.users')->fetchColumn();
$posted = $http('POST', '/admin/users', ['role' => 'admin', 'status' => 'suspended'], $signed['cookie']);
$postedDetail = $http('POST', '/admin/users/' . $ana, ['status' => 'suspended'], $signed['cookie']);
$after = (int) $db->query('SELECT count(*) FROM platform.users')->fetchColumn();
$check($posted['status'] === 405 && $postedDetail['status'] === 405 && $after === $before && (string) $db->query("SELECT status FROM platform.users WHERE id = {$ana}")->fetchColumn() === 'active', 'post does not modify users');
$overview = $http('GET', '/admin', [], $signed['cookie']);
$check($overview['status'] === 200 && str_contains($overview['body'], 'data-stat="users_total"') && str_contains($overview['body'], 'href="/admin/users"'), 'overview still works');
$db->exec("UPDATE platform.users SET status = 'suspended' WHERE id = {$adminId}");
$denied = $http('GET', '/admin/users', [], $signed['cookie']);
$check($denied['status'] === 302 && str_contains($denied['header'], 'Location: /login'), 'suspended admin cannot access');

proc_terminate($proc);
proc_close($proc);

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "admin users ok\n";
