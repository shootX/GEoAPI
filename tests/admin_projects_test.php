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
    $admin->exec('DROP DATABASE IF EXISTS carapi_admin_projects_test WITH (FORCE)');
};
$drop();
register_shutdown_function($drop);
$admin->exec('CREATE DATABASE carapi_admin_projects_test');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_admin_projects_test',
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

$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_admin_projects_test', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_admin_projects_test TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE ON TABLE platform.users, platform.projects, platform.api_keys, platform.email_tokens, platform.mail_limits, platform.monthly_usage TO carapi_platform_app');
$db->exec('GRANT SELECT ON TABLE platform.project_limits, platform.daily_usage, platform.widgets TO carapi_platform_app');
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
$marker = 'password-hash-marker-projects';
$user = $db->prepare('INSERT INTO platform.users (first_name, last_name, email, password_hash, status, email_verified_at, role) VALUES (:f,:l,:e,:p,:s,:v,:r) RETURNING id');
$user->execute(['f' => 'ადმინი', 'l' => 'ტესტი', 'e' => 'admin-projects@example.com', 'p' => $hash, 's' => 'active', 'v' => '2026-10-01 00:00:00+00', 'r' => 'admin']);
$adminId = (int) $user->fetchColumn();
$user->execute(['f' => 'Ana', 'l' => 'Beridze', 'e' => 'ana-projects@example.com', 'p' => $hash, 's' => 'active', 'v' => '2026-10-02 00:00:00+00', 'r' => 'user']);
$ana = (int) $user->fetchColumn();
$user->execute(['f' => 'გია', 'l' => 'მფლობელი', 'e' => 'gia-projects@example.com', 'p' => $hash, 's' => 'active', 'v' => null, 'r' => 'user']);
$gia = (int) $user->fetchColumn();
$user->execute(['f' => '<b>ნინო</b>', 'l' => 'ტესტი', 'e' => 'nina-projects@example.com', 'p' => $marker, 's' => 'suspended', 'v' => null, 'r' => 'user']);
$nina = (int) $user->fetchColumn();
$user->execute(['f' => 'ფილერი', 'l' => 'მფლობელი', 'e' => 'filler-projects@example.com', 'p' => $marker, 's' => 'active', 'v' => null, 'r' => 'user']);
$filler = (int) $user->fetchColumn();

$project = $db->prepare('INSERT INTO platform.projects (user_id, name, website_url, status, created_at) VALUES (:u,:n,:w,:s,:c) RETURNING id');
$project->execute(['u' => $ana, 'n' => 'Crosstrek X', 'w' => 'https://ana.example', 's' => 'active', 'c' => '2026-10-17 00:00:00+00']);
$anaProject = (int) $project->fetchColumn();
$project->execute(['u' => $gia, 'n' => 'გიას პროექტი', 'w' => null, 's' => 'disabled', 'c' => '2026-10-16 00:00:00+00']);
$giaProject = (int) $project->fetchColumn();
$project->execute(['u' => $nina, 'n' => "o'hara & <script>", 'w' => null, 's' => 'active', 'c' => '2026-10-15 00:00:00+00']);
$ninaProject = (int) $project->fetchColumn();
$project->execute(['u' => $filler, 'n' => 'order-a', 'w' => null, 's' => 'active', 'c' => '2026-08-01 00:00:00+00']);
$orderA = (int) $project->fetchColumn();
$project->execute(['u' => $filler, 'n' => 'order-b', 'w' => null, 's' => 'active', 'c' => '2026-08-01 00:00:00+00']);
$orderB = (int) $project->fetchColumn();
for ($n = 1; $n <= 18; $n++) {
    $project->execute(['u' => $filler, 'n' => sprintf('page-project-%02d', $n), 'w' => null, 's' => 'active', 'c' => sprintf('2026-01-%02d 00:00:00+00', $n)]);
}
$db->prepare('INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota) VALUES (:id, 15, 500)')->execute(['id' => $anaProject]);
$db->prepare('INSERT INTO platform.monthly_usage (project_id, period, used_count) VALUES (:id,:period,:used)')->execute(['id' => $anaProject, 'period' => '2026-10-01', 'used' => 40]);
$db->prepare('INSERT INTO platform.monthly_usage (project_id, period, used_count) VALUES (:id,:period,:used)')->execute(['id' => $anaProject, 'period' => '2026-09-01', 'used' => 99]);
$daily = $db->prepare('INSERT INTO platform.daily_usage (project_id, day, route, used_count) VALUES (:id,:day,:route,:used)');
$daily->execute(['id' => $anaProject, 'day' => '2026-10-15', 'route' => 'brands', 'used' => 3]);
$daily->execute(['id' => $anaProject, 'day' => '2026-10-15', 'route' => 'models', 'used' => 1]);
$daily->execute(['id' => $anaProject, 'day' => '2026-10-14', 'route' => 'brands', 'used' => 2]);
$daily->execute(['id' => $anaProject, 'day' => '2026-09-16', 'route' => 'brands', 'used' => 3]);
$daily->execute(['id' => $anaProject, 'day' => '2026-09-15', 'route' => 'brands', 'used' => 77]);
$key = $db->prepare('INSERT INTO platform.api_keys (project_id, name, key_selector, secret_hash, expires_at, revoked_at) VALUES (:p,:n,:s,:h,:e,:r) RETURNING id');
$key->execute(['p' => $anaProject, 'n' => 'აქტიური', 's' => str_repeat('a', 32), 'h' => 'secret-hash-marker-projects', 'e' => null, 'r' => null]);
$activeKey = (int) $key->fetchColumn();
$key->execute(['p' => $anaProject, 'n' => 'გაუქმებული', 's' => str_repeat('b', 32), 'h' => 'secret-hash-marker-projects', 'e' => '2026-12-01 00:00:00+00', 'r' => '2026-10-01 00:00:00+00']);
$revokedKey = (int) $key->fetchColumn();
$key->execute(['p' => $anaProject, 'n' => 'ვადაგასული', 's' => str_repeat('c', 32), 'h' => 'secret-hash-marker-projects', 'e' => '2026-01-01 00:00:00+00', 'r' => null]);
$expiredKey = (int) $key->fetchColumn();
$key->execute(['p' => $anaProject, 'n' => 'ორივე', 's' => str_repeat('e', 32), 'h' => 'secret-hash-marker-projects', 'e' => '2026-01-01 00:00:00+00', 'r' => '2026-02-01 00:00:00+00']);
$bothKey = (int) $key->fetchColumn();
$db->prepare('INSERT INTO platform.widgets (project_id, enabled, public_key) VALUES (:id, true, :key)')->execute(['id' => $anaProject, 'key' => 'carapi_pk_should_not_appear_9f']);

$now = gmmktime(12, 0, 0, 10, 15, 2026);
$directory = new Carapi\Platform\AdminProjects($db);
$all = $directory->search([], $now);
$check($all['total'] === 23 && $all['per'] === 20 && count($all['rows']) === 20 && $all['rows'][0]['id'] === $anaProject && $all['rows'][0]['email'] === 'ana-projects@example.com' && $all['rows'][0]['used'] === 40 && $all['rows'][0]['limit'] === 500 && $all['rows'][0]['remaining'] === 460, 'list total, owner and monthly quota');
$check($directory->search(['per' => '500'], $now)['per'] === 100, 'page size is capped');
$wide = $directory->search(['per' => '100'], $now);
$ids = array_column($wide['rows'], 'id');
$check(array_search($orderB, $ids, true) < array_search($orderA, $ids, true), 'equal timestamps keep the higher id first');
$check($directory->search(['q' => 'crosstrek x'], $now)['rows'][0]['id'] === $anaProject, 'project name search is case-insensitive');
$check($directory->search(['q' => 'ANA-PROJECTS@EXAMPLE.COM'], $now)['total'] === 1, 'owner email search is case-insensitive');
$check($directory->search(['q' => 'beridze'], $now)['rows'][0]['id'] === $anaProject, 'owner name search is case-insensitive');
$check($directory->search(['q' => '%'], $now)['total'] === 0, 'a percent sign is not a wildcard');
$check($directory->search(['status' => 'disabled'], $now)['total'] === 1 && $directory->search(['status' => 'active'], $now)['total'] === 22, 'status filter');
$check($directory->search(['status' => 'suspended'], $now)['total'] === 23, 'invalid status is ignored');
$page2 = $directory->search(['page' => '2'], $now);
$check($page2['page'] === 2 && count($page2['rows']) === 3 && $page2['rows'][2]['name'] === 'page-project-01', 'second page keeps the oldest rows');
$detail = $directory->find($anaProject, $now);
$dayUsed = [];
foreach ($detail['days'] ?? [] as $day) {
    $dayUsed[$day['day']] = $day['used'];
}
$keyStatus = [];
foreach ($detail['keys'] ?? [] as $item) {
    $keyStatus[$item['id']] = $item['status'];
}
$check($detail !== null && $detail['website'] === 'https://ana.example' && $detail['owner_id'] === $ana && $detail['per_minute'] === 15 && $detail['used'] === 40 && $detail['remaining'] === 460 && count($detail['days']) === 30, 'detail usage and minute limit');
$check(($dayUsed['2026-10-15'] ?? -1) === 4 && ($dayUsed['2026-10-14'] ?? -1) === 2 && ($dayUsed['2026-09-16'] ?? -1) === 3 && !isset($dayUsed['2026-09-15']), 'thirty day window sums routes and drops the older day');
$check($keyStatus[$activeKey] === 'active' && $keyStatus[$revokedKey] === 'revoked' && $keyStatus[$expiredKey] === 'expired' && $keyStatus[$bothKey] === 'revoked', 'revoked and expired keys are not active');
$check($directory->find($giaProject, $now)['owner_id'] === $gia && $directory->find(0, $now) === null && $directory->find(999999, $now) === null, 'owner attribution and missing projects');

$sessionPath = sys_get_temp_dir() . '/carapi-admin-projects-sessions';
$port = 18155;
$env = [
    'CARAPI_PLATFORM_ENABLED' => '1',
    'CARAPI_PLATFORM_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_PORT' => '5432',
    'CARAPI_PLATFORM_DATABASE' => 'carapi_admin_projects_test',
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
    [1 => ['file', '/tmp/carapi-admin-projects.log', 'w'], 2 => ['file', '/tmp/carapi-admin-projects.err', 'w']],
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
$boot = is_file('/tmp/carapi-admin-projects.err') ? (string) file_get_contents('/tmp/carapi-admin-projects.err') : '';
if (!$ready || str_contains($boot, 'Failed to listen')) {
    fwrite(STDERR, "projects test server did not bind\n");
    proc_terminate($proc);
    proc_close($proc);
    exit(1);
}

$login = static function (string $email) use ($http): array {
    $page = $http('GET', '/login');
    preg_match('/name="_csrf" value="([^"]+)"/', $page['body'], $csrf);

    return $http('POST', '/login', ['_csrf' => $csrf[1] ?? '', 'email' => $email, 'password' => 'ქართული-პაროლი-12345', 'role' => 'admin'], $page['cookie']);
};
$guest = $http('GET', '/admin/projects');
$check($guest['status'] === 302 && str_contains($guest['header'], 'Location: /login') && !str_contains($guest['body'], 'Crosstrek'), 'guest cannot access project list');
$anaLogin = $login('ana-projects@example.com');
$ordinary = $http('GET', '/admin/projects', [], $anaLogin['cookie']);
$check($ordinary['status'] === 404 && !str_contains($ordinary['body'], 'Crosstrek'), 'ordinary user receives 404');
$signed = $login('admin-projects@example.com');
$list = $http('GET', '/admin/projects', [], $signed['cookie']);
$check($list['status'] === 200 && str_contains($list['body'], 'data-total="23"') && str_contains($list['body'], 'href="/admin/projects/' . $anaProject . '"') && str_contains($list['body'], 'aria-current="page"'), 'admin can view project list');
$page = $http('GET', '/admin/projects/' . $anaProject, [], $signed['cookie']);
$check($page['status'] === 200 && str_contains($page['body'], 'data-minute="15"') && str_contains($page['body'], 'data-remaining="460"') && str_contains($page['body'], 'href="/admin/users/' . $ana . '"') && str_contains($page['body'], 'href="/admin/projects"'), 'admin can view project details');
$check($http('GET', '/admin/projects/0', [], $signed['cookie'])['status'] === 404 && $http('GET', '/admin/projects/nope', [], $signed['cookie'])['status'] === 404, 'invalid project id returns 404');
$check($http('GET', '/admin/projects/999999', [], $signed['cookie'])['status'] === 404, 'missing project returns 404');
$byName = $http('GET', '/admin/projects?q=' . rawurlencode('CROSSTREK X'), [], $signed['cookie']);
$byEmail = $http('GET', '/admin/projects?q=' . rawurlencode('gia-projects@example.com'), [], $signed['cookie']);
$byOwner = $http('GET', '/admin/projects?q=' . rawurlencode('Beridze'), [], $signed['cookie']);
$check(str_contains($byName['body'], 'data-total="1"') && str_contains($byName['body'], 'Crosstrek X') && str_contains($byEmail['body'], 'გიას პროექტი') && str_contains($byOwner['body'], 'ana-projects@example.com'), 'rendered search');
$disabled = $http('GET', '/admin/projects?status=disabled', [], $signed['cookie']);
$check(str_contains($disabled['body'], 'data-total="1"') && str_contains($disabled['body'], 'გიას პროექტი') && !str_contains($disabled['body'], 'Crosstrek'), 'rendered status filter');
$next = $http('GET', '/admin/projects?status=active&page=2', [], $signed['cookie']);
$check(str_contains($next['body'], 'data-page="2"') && str_contains($next['body'], 'status=active') && str_contains($next['body'], 'page-project-01'), 'pagination keeps the filter');
$escaped = $http('GET', '/admin/projects/' . $ninaProject, [], $signed['cookie']);
$check(str_contains($list['body'], '&lt;b&gt;ნინო&lt;/b&gt;') && !str_contains($list['body'], '<b>ნინო</b>') && str_contains($escaped['body'], 'o&#039;hara &amp; &lt;script&gt;') && !str_contains($escaped['body'], "o'hara & <script>"), 'html is escaped');
$hidden = $list['body'] . $page['body'] . $escaped['body'];
$check(!str_contains($hidden, $marker) && !str_contains($hidden, 'password_hash') && !str_contains($hidden, 'secret-hash-marker-projects') && !str_contains($hidden, 'secret_hash') && !str_contains($hidden, str_repeat('a', 32)) && !str_contains($hidden, str_repeat('b', 32)) && !str_contains($hidden, 'carapi_pk_should_not_appear_9f') && str_contains($page['body'], 'carapi_sk_aaaa…aaaa') && str_contains($page['body'], 'data-key-status="expired"') && str_contains($page['body'], 'data-key-status="revoked"') && str_contains($page['body'], 'data-day-used="4"') && !str_contains($page['body'], 'data-day-used="77"'), 'secrets stay hidden and key status is explicit');
$check(!str_contains($page['body'], 'გიას პროექტი'), 'detail does not include another project');
$before = (string) $db->query("SELECT status FROM platform.projects WHERE id = {$anaProject}")->fetchColumn();
$posted = $http('POST', '/admin/projects', ['status' => 'disabled'], $signed['cookie']);
$postedDetail = $http('POST', '/admin/projects/' . $anaProject, ['status' => 'disabled'], $signed['cookie']);
$check($posted['status'] === 405 && $postedDetail['status'] === 405 && (string) $db->query("SELECT status FROM platform.projects WHERE id = {$anaProject}")->fetchColumn() === $before, 'post does not modify projects');
$users = $http('GET', '/admin/users', [], $signed['cookie']);
$overview = $http('GET', '/admin', [], $signed['cookie']);
$check($users['status'] === 200 && str_contains($users['body'], 'ana-projects@example.com') && $overview['status'] === 200 && str_contains($overview['body'], 'data-stat="users_total"') && str_contains($overview['body'], 'href="/admin/projects"'), 'users and overview still work');
$db->exec("UPDATE platform.users SET status = 'suspended' WHERE id = {$adminId}");
$denied = $http('GET', '/admin/projects', [], $signed['cookie']);
$check($denied['status'] === 302 && str_contains($denied['header'], 'Location: /login'), 'suspended admin cannot access');

proc_terminate($proc);
proc_close($proc);

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
echo "admin projects ok\n";
