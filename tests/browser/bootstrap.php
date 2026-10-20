<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

$platformSecret = '';
$catalogSecret = '';
$catalogUser = 'carapi_catalog_reader';
foreach (file('/www/server/carapi/platform.env', FILE_IGNORE_NEW_LINES) as $line) {
    if (str_starts_with($line, 'CARAPI_PLATFORM_PASSWORD=')) {
        $platformSecret = substr($line, 26);
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

$admin = new PDO('pgsql:host=127.0.0.1;dbname=postgres', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('DROP DATABASE IF EXISTS carapi_widget_browser WITH (FORCE)');
$admin->exec('CREATE DATABASE carapi_widget_browser');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_widget_browser',
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

$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_widget_browser', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_widget_browser TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');

$catalog = new PDO('pgsql:host=127.0.0.1;dbname=carbase', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$big = $catalog->query(<<<'SQL'
    SELECT b.slug, count(*) AS n
    FROM public.models m
    JOIN public.brands b ON b.id = m.brand_id
    GROUP BY b.slug
    ORDER BY n DESC
    LIMIT 1
SQL)->fetch();
$empty = $catalog->query(<<<'SQL'
    SELECT b.slug AS brand_slug, m.id::text AS model_id, m.name
    FROM public.models m
    JOIN public.brands b ON b.id = m.brand_id
    WHERE NOT EXISTS (SELECT 1 FROM public.generations g WHERE g.model_id = m.id)
    ORDER BY b.slug, m.id
    LIMIT 1
SQL)->fetch();
$withGen = $catalog->query(<<<'SQL'
    SELECT b.slug AS brand_slug, m.id::text AS model_id, g.id::text AS generation_id
    FROM public.generations g
    JOIN public.models m ON m.id = g.model_id
    JOIN public.brands b ON b.id = m.brand_id
    ORDER BY b.slug, m.id, g.id
    LIMIT 1
SQL)->fetch();

$password = 'ქართული-პაროლი-12345';
$hash = Carapi\Platform\Passwords::hash($password);
$db->prepare("INSERT INTO platform.users (first_name, last_name, email, password_hash, email_verified_at) VALUES ('ანა','ბრაუზერი','browser-widget@example.com', :p, now())")->execute(['p' => $hash]);
$userId = (int) $db->query('SELECT id FROM platform.users')->fetchColumn();
$db->prepare('INSERT INTO platform.projects (user_id, name) VALUES (:id, :name)')->execute(['id' => $userId, 'name' => 'ბრაუზერი']);
$projectId = (int) $db->query('SELECT id FROM platform.projects')->fetchColumn();
$db->prepare('INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota) VALUES (:id, 1000, 100000)')->execute(['id' => $projectId]);
$pk = Carapi\Platform\WidgetRepository::newKey();
$issued = Carapi\Platform\ApiKeys::issue();
$origins = json_encode(['http://127.0.0.1:18110', 'http://127.0.0.1:18111'], JSON_UNESCAPED_SLASHES);
$db->prepare('INSERT INTO platform.widgets (project_id, enabled, public_key, allowed_origins) VALUES (:id, true, :key, :origins::jsonb)')
    ->execute(['id' => $projectId, 'key' => $pk, 'origins' => $origins]);
$db->prepare('INSERT INTO platform.api_keys (project_id, name, key_selector, secret_hash) VALUES (:p, :n, :s, :h)')
    ->execute(['p' => $projectId, 'n' => 'სერვერი', 's' => $issued['selector'], 'h' => $issued['hash']]);

$out = [
    'password' => $password,
    'email' => 'browser-widget@example.com',
    'project_id' => $projectId,
    'public_key' => $pk,
    'server_key' => $issued['token'],
    'brand_slug' => $big['slug'],
    'brand_count' => (int) $big['n'],
    'empty_brand_slug' => $empty['brand_slug'],
    'empty_model_id' => $empty['model_id'],
    'gen_brand_slug' => $withGen['brand_slug'],
    'gen_model_id' => $withGen['model_id'],
    'generation_id' => $withGen['generation_id'],
    'platform_secret' => $platformSecret,
    'catalog_secret' => $catalogSecret,
    'catalog_user' => $catalogUser,
];
file_put_contents('/tmp/carapi-browser-fixture.json', json_encode($out));
echo "ok\n";
