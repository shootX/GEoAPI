<?php

declare(strict_types=1);

namespace Tests\Feature;

use Geoapi\Platform\PostgresUsageStore;
use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

/**
 * Runs against a real PostgreSQL (CI service) so CHECK constraints are covered.
 * Skipped when PG_TEST_HOST is not set (local SQLite runs).
 */
final class PostgresConstraintsTest extends TestCase
{
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        parent::setUp();
        $host = getenv('PG_TEST_HOST');
        if (! is_string($host) || $host === '') {
            $this->markTestSkipped('PG_TEST_HOST not set');
        }
        config(['database.connections.pg_ci' => [
            'driver' => 'pgsql', 'host' => $host, 'port' => (int) (getenv('PG_TEST_PORT') ?: 5432),
            'database' => getenv('PG_TEST_DB') ?: 'postgres', 'username' => getenv('PG_TEST_USER') ?: 'postgres',
            'password' => getenv('PG_TEST_PASSWORD') ?: '', 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'disable',
        ]]);
        $this->pdo = DB::connection('pg_ci')->getPdo();
        $this->pdo->exec('DROP SCHEMA IF EXISTS platform CASCADE');
        $this->pdo->exec('CREATE SCHEMA platform');
        // Same shape and original CHECK as production before 2026_12_31_940000.
        $this->pdo->exec("CREATE TABLE platform.rate_windows (scope text NOT NULL CONSTRAINT rate_windows_scope_check CHECK (scope = ANY (ARRAY['project'::text, 'ip'::text])), subject text NOT NULL, window_start timestamptz NOT NULL, hit_count integer NOT NULL DEFAULT 0, PRIMARY KEY (scope, subject, window_start))");
    }

    protected function tearDown(): void
    {
        $this->pdo?->exec('DROP SCHEMA IF EXISTS platform CASCADE');
        parent::tearDown();
    }

    public function test_workspace_scope_needs_the_migration_and_then_counts(): void
    {
        $store = new PostgresUsageStore($this->pdo);
        try {
            $store->bumpWindow('workspace', '1', 60, time());
            $this->fail('old constraint should reject workspace scope');
        } catch (PDOException $e) {
            $this->assertSame('23514', $e->getCode());
        }

        $previous = DB::getDefaultConnection();
        DB::setDefaultConnection('pg_ci');
        try {
            (require base_path('database/migrations/2026_12_31_940000_rate_windows_workspace_scope.php'))->up();
        } finally {
            DB::setDefaultConnection($previous);
        }

        $now = time();
        $this->assertSame(1, $store->bumpWindow('workspace', '1', 60, $now)['count']);
        $this->assertSame(2, $store->bumpWindow('workspace', '1', 60, $now)['count']);
        $this->assertSame(1, $store->bumpWindow('project', '7', 60, $now)['count']);
        $this->assertSame(1, $store->bumpWindow('ip', '127.0.0.1', 60, $now)['count']);

        $this->expectException(PDOException::class);
        $store->bumpWindow('bogus', '1', 60, $now);
    }
}
