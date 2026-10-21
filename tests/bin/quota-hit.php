<?php

declare(strict_types=1);
use App\Catalog\CatalogApi;
use Carapi\Catalog\Connection;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/**
 * One catalog request against a shared sqlite quota file.
 * Prints a single JSON object: {"status":200,"code":null}
 * The parent test owns the quota file. This process does not open Postgres.
 */
$sqlite = $argv[1] ?? '';
if ($sqlite === '' || str_contains($sqlite, "\0")) {
    fwrite(STDERR, "missing quota file\n");
    exit(1);
}

foreach ([
    'APP_ENV' => 'testing',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'CARAPI_CATALOG_ENABLED' => '1',
    'CARAPI_MODIFICATIONS_ENABLED' => '1',
    'CARAPI_MODIFICATION_DETAILS_ENABLED' => '1',
    'CARAPI_CATALOG_TEST_PROJECT' => '7',
    'CARAPI_USAGE_SQLITE' => $sqlite,
    'CARAPI_USAGE_MEMORY' => '0',
] as $name => $value) {
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
    putenv($name.'='.$value);
}

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

try {
    ob_start();
    $kernel->call('migrate', ['--force' => true, '--quiet' => true]);
    ob_end_clean();
    DB::statement("ATTACH DATABASE ':memory:' AS platform");
    DB::statement(
        'CREATE TABLE IF NOT EXISTS platform.projects (id integer primary key, user_id integer not null, status text not null default \'active\')'
    );
    DB::table('platform.projects')->updateOrInsert(
        ['id' => 7],
        ['user_id' => 3, 'status' => 'active']
    );
    $productId = (int) DB::table('api_products')->where('slug', 'cars')->value('id');
    $planId = (int) DB::table('api_plans')->where('code', 'cars-included')->value('id');
    DB::table('api_entitlements')->insert([
        'legacy_user_id' => 3,
        'api_product_id' => $productId,
        'api_plan_id' => $planId,
        'status' => 'active',
        'starts_at' => now(),
        'source' => 'test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $pdo = new PDO('sqlite::memory:', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec(<<<'SQL'
        CREATE TABLE brands (
            id integer primary key,
            slug text not null,
            name text not null,
            synonyms text,
            is_lcv_or_commercial integer,
            logo_url_light text,
            logo_url_dark text,
            updated_at text
        );
        INSERT INTO brands (id, slug, name, synonyms, is_lcv_or_commercial, updated_at)
        VALUES (1, 'audi', 'Audi', '[]', 0, '2026-01-01T00:00:00Z');
    SQL);
    Connection::use($pdo);
    $_GET = ['limit' => '1'];
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
    $result = (new CatalogApi)->handle('/api/v1/brands');
    echo json_encode([
        'status' => $result['status'],
        'code' => $result['body']['error']['code'] ?? null,
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage()."\n");
    exit(1);
}
