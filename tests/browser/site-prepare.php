<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$admin = new PDO('pgsql:host=127.0.0.1;dbname=postgres', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$admin->exec('DROP DATABASE IF EXISTS carapi_site_browser WITH (FORCE)');
$admin->exec('CREATE DATABASE carapi_site_browser');
$migrate = proc_open([PHP_BINARY, $root . '/bin/migrate.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, [
    'CARAPI_PLATFORM_ADMIN_HOST' => '127.0.0.1',
    'CARAPI_PLATFORM_ADMIN_PORT' => '5432',
    'CARAPI_PLATFORM_ADMIN_DATABASE' => 'carapi_site_browser',
    'CARAPI_PLATFORM_ADMIN_USER' => 'postgres',
    'CARAPI_PLATFORM_ADMIN_PASSWORD' => '',
]);
stream_get_contents($pipes[1]);
stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($migrate) !== 0) {
    fwrite(STDERR, "migrate failed\n");
    exit(1);
}
$db = new PDO('pgsql:host=127.0.0.1;dbname=carapi_site_browser', 'postgres', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('GRANT CONNECT ON DATABASE carapi_site_browser TO carapi_platform_app');
$db->exec('GRANT USAGE ON SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA platform TO carapi_platform_app');
$db->exec('GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA platform TO carapi_platform_app');
echo "ok\n";
