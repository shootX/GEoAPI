<?php

declare(strict_types=1);

use App\Billing\V2\Ledger;
use App\Billing\V2\Workspace;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

/**
 * Shared-file last unit. argv: sqlite path, "init" or "hit", workspace id (hit only).
 * Prints {"ok":true|false} for hit, or {"workspace":N} for init.
 */
$sqlite = $argv[1] ?? '';
$mode = $argv[2] ?? '';
if ($sqlite === '' || str_contains($sqlite, "\0") || ! in_array($mode, ['init', 'hit'], true)) {
    fwrite(STDERR, "usage: billing-v2-hit.php <sqlite> init|hit [workspace]\n");
    exit(1);
}

foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(str_repeat('a', 32)),
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $sqlite,
    'DB_URL' => '',
    'BILLING_V2' => 'true',
    'BILLING_V2_BUSINESS_RPM' => 'false',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'HASH_DRIVER' => 'bcrypt',
    'BCRYPT_ROUNDS' => '4',
] as $name => $value) {
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
    putenv($name.'='.$value);
}

if (! is_file($sqlite)) {
    touch($sqlite);
}

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_TIMEOUT, 8);
$pdo->exec('PRAGMA busy_timeout = 8000');

if ($mode === 'init') {
    ob_start();
    $kernel->call('migrate', ['--force' => true, '--quiet' => true]);
    ob_end_clean();
    DB::statement('PRAGMA journal_mode = WAL');
    $user = User::factory()->create([
        'email' => 'v2-race@example.com',
        'legacy_platform_user_id' => 3,
    ]);
    $now = Workspace::stamp();
    $id = Workspace::create((int) $user->id, 3, 'signup', [
        'plan_code' => 'free',
        'interval' => 'month',
        'monthly_quota' => 1,
        'rpm' => 15,
        'included_projects' => 1,
        'period_start' => $now,
        'period_end' => Workspace::plusMonth($now),
        'term_end' => Workspace::plusMonth($now),
        'shared_quota' => true,
        'source' => 'signup',
    ]);
    DB::table('billing_period_usage')->insert([
        'workspace_id' => $id,
        'period_start' => $now,
        'used_count' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    DB::table('billing_project_period_usage')->insert([
        'workspace_id' => $id,
        'project_id' => 7,
        'period_start' => $now,
        'used_count' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    echo json_encode(['workspace' => $id], JSON_THROW_ON_ERROR);

    exit(0);
}

$workspaceId = (int) ($argv[3] ?? 0);
$result = Ledger::consume($workspaceId, 7, time());
echo json_encode(['ok' => (bool) ($result['ok'] ?? false)], JSON_THROW_ON_ERROR);
