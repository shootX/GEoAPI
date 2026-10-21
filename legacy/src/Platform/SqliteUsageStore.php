<?php

declare(strict_types=1);

namespace Carapi\Platform;

use PDO;
use PDOException;

/**
 * File-backed counters for the PHPUnit concurrency test.
 * Production metering stays in PostgresUsageStore.
 * This store is constructed only when the app is running tests.
 */
final class SqliteUsageStore implements UsageStore
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        try {
            $pdo = new PDO('sqlite:'.$path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $pdo->exec('PRAGMA busy_timeout = 15000');
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS limits (
                    project_id INTEGER PRIMARY KEY,
                    per_minute INTEGER NOT NULL,
                    monthly_quota INTEGER NOT NULL,
                    per_day INTEGER NOT NULL
                );
                CREATE TABLE IF NOT EXISTS windows (
                    scope TEXT NOT NULL,
                    subject TEXT NOT NULL,
                    window_start TEXT NOT NULL,
                    hit_count INTEGER NOT NULL,
                    PRIMARY KEY (scope, subject, window_start)
                );
                CREATE TABLE IF NOT EXISTS months (
                    project_id INTEGER NOT NULL,
                    period TEXT NOT NULL,
                    used_count INTEGER NOT NULL,
                    PRIMARY KEY (project_id, period)
                );
                CREATE TABLE IF NOT EXISTS days (
                    project_id INTEGER NOT NULL,
                    day TEXT NOT NULL,
                    route TEXT NOT NULL,
                    used_count INTEGER NOT NULL,
                    PRIMARY KEY (project_id, day, route)
                );
            SQL);
        } catch (PDOException) {
            throw new UnavailableException;
        }
        $this->pdo = $pdo;
    }

    public function allow(int $projectId, int $perMinute = 60, int $perDay = 2000, int $monthlyQuota = 10000): void
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO limits (project_id, per_minute, monthly_quota, per_day)
            VALUES (:id, :minute, :month, :day)
            ON CONFLICT (project_id) DO UPDATE SET
                per_minute = excluded.per_minute,
                monthly_quota = excluded.monthly_quota,
                per_day = excluded.per_day
        SQL);
        $stmt->execute([
            'id' => $projectId,
            'minute' => $perMinute,
            'month' => $monthlyQuota,
            'day' => $perDay,
        ]);
    }

    public function perMinute(int $projectId): ?int
    {
        $limits = $this->limits($projectId);

        return $limits['per_minute'] ?? null;
    }

    public function bumpWindow(string $scope, string $subject, int $limit, int $now): array
    {
        $start = gmdate('Y-m-d H:i', $now);
        $this->pdo->exec('BEGIN IMMEDIATE');
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO windows (scope, subject, window_start, hit_count)
            VALUES (:scope, :subject, :window_start, 1)
            ON CONFLICT (scope, subject, window_start)
            DO UPDATE SET hit_count = hit_count + 1
            RETURNING hit_count
        SQL);
        $stmt->execute(['scope' => $scope, 'subject' => $subject, 'window_start' => $start]);
        $count = (int) $stmt->fetchColumn();
        $stmt->closeCursor();
        $this->pdo->exec('COMMIT');

        return [
            'count' => $count,
            'limit' => $limit,
            'exceeded' => $count > $limit,
            'retry_after' => 60 - ((int) gmdate('s', $now)),
        ];
    }

    public function previewMonth(int $projectId, int $now): ?array
    {
        $limits = $this->limits($projectId);
        if ($limits === null) {
            return null;
        }
        $used = $this->monthUsed($projectId, $now);

        return $this->view($limits['monthly_quota'], $used, $this->nextMonth($now));
    }

    public function previewDay(int $projectId, int $now): ?array
    {
        $limits = $this->limits($projectId);
        if ($limits === null) {
            return null;
        }
        $used = $this->dayUsed($projectId, $now);

        return [
            'limit' => $limits['per_day'],
            'used' => $used,
            'remaining' => max(0, $limits['per_day'] - $used),
            'exhausted' => $used >= $limits['per_day'],
            'reset_at' => $this->nextDay($now),
            'enforced' => true,
        ];
    }

    public function commitSuccess(int $projectId, string $route, int $now): array
    {
        $limits = $this->limits($projectId);
        if ($limits === null) {
            throw new UnavailableException;
        }
        $period = gmdate('Y-m-01', $now);
        $day = gmdate('Y-m-d', $now);
        $this->pdo->exec('BEGIN IMMEDIATE');
        $month = $this->pdo->prepare(<<<'SQL'
            INSERT INTO months (project_id, period, used_count)
            VALUES (:id, :period, 1)
            ON CONFLICT (project_id, period)
            DO UPDATE SET used_count = used_count + 1
            WHERE used_count < :quota
            RETURNING used_count
        SQL);
        $month->execute(['id' => $projectId, 'period' => $period, 'quota' => $limits['monthly_quota']]);
        $used = $month->fetchColumn();
        $month->closeCursor();
        if ($used === false) {
            $this->pdo->exec('ROLLBACK');

            return ['ok' => false, 'reason' => 'month', 'limit' => $limits['monthly_quota'], 'used' => $limits['monthly_quota'], 'remaining' => 0, 'reset_at' => $this->nextMonth($now)];
        }
        $daily = $this->pdo->prepare(<<<'SQL'
            INSERT INTO days (project_id, day, route, used_count)
            VALUES (:id, :day, :route, 1)
            ON CONFLICT (project_id, day, route)
            DO UPDATE SET used_count = used_count + 1
        SQL);
        $daily->execute(['id' => $projectId, 'day' => $day, 'route' => $route]);
        $daily->closeCursor();
        if ($this->dayUsed($projectId, $now) > $limits['per_day']) {
            $this->pdo->exec('ROLLBACK');

            return ['ok' => false, 'reason' => 'day', 'limit' => $limits['per_day'], 'used' => $limits['per_day'], 'remaining' => 0, 'reset_at' => $this->nextDay($now)];
        }
        $this->pdo->exec('COMMIT');
        $used = (int) $used;

        return [
            'ok' => true,
            'reason' => 'ok',
            'limit' => $limits['monthly_quota'],
            'used' => $used,
            'remaining' => max(0, $limits['monthly_quota'] - $used),
            'reset_at' => $this->nextMonth($now),
        ];
    }

    public function stats(int $projectId, int $now): ?array
    {
        $month = $this->previewMonth($projectId, $now);
        if ($month === null) {
            return null;
        }
        $limits = $this->limits($projectId);
        $today = gmmktime(0, 0, 0, (int) gmdate('n', $now), (int) gmdate('j', $now), (int) gmdate('Y', $now));
        $start = $today - (29 * 86400);
        $stmt = $this->pdo->prepare(
            'SELECT day, route, used_count FROM days WHERE project_id = :id AND day >= :start AND day <= :end ORDER BY day, route'
        );
        $stmt->execute([
            'id' => $projectId,
            'start' => gmdate('Y-m-d', $start),
            'end' => gmdate('Y-m-d', $today),
        ]);
        $byDay = [];
        $byRoute = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $day = (string) $row['day'];
            $route = (string) $row['route'];
            $count = (int) $row['used_count'];
            $byDay[$day] = ($byDay[$day] ?? 0) + $count;
            $byRoute[$route] = ($byRoute[$route] ?? 0) + $count;
        }
        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $day = gmdate('Y-m-d', $start + ($i * 86400));
            $days[] = ['day' => $day, 'used' => $byDay[$day] ?? 0];
        }
        $routes = [];
        foreach ($byRoute as $route => $count) {
            $routes[] = ['route' => $route, 'used' => $count];
        }

        return [
            'limit' => $month['limit'],
            'used' => $month['used'],
            'remaining' => $month['remaining'],
            'reset_at' => gmdate('Y-m-d\TH:i:s\Z', $month['reset_at']),
            'per_minute' => $limits['per_minute'] ?? 0,
            'per_day' => $limits['per_day'] ?? null,
            'days' => $days,
            'routes' => $routes,
        ];
    }

    public function monthUsed(int $projectId, int $now): int
    {
        $stmt = $this->pdo->prepare('SELECT used_count FROM months WHERE project_id = :id AND period = :period');
        $stmt->execute(['id' => $projectId, 'period' => gmdate('Y-m-01', $now)]);

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** @return array{per_minute:int,monthly_quota:int,per_day:int}|null */
    private function limits(int $projectId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT per_minute, monthly_quota, per_day FROM limits WHERE project_id = :id');
        $stmt->execute(['id' => $projectId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'per_minute' => (int) $row['per_minute'],
            'monthly_quota' => (int) $row['monthly_quota'],
            'per_day' => (int) $row['per_day'],
        ];
    }

    private function dayUsed(int $projectId, int $now): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(sum(used_count), 0) FROM days WHERE project_id = :id AND day = :day');
        $stmt->execute(['id' => $projectId, 'day' => gmdate('Y-m-d', $now)]);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{limit:int,used:int,remaining:int,exhausted:bool,reset_at:int} */
    private function view(int $limit, int $used, int $reset): array
    {
        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'exhausted' => $used >= $limit,
            'reset_at' => $reset,
        ];
    }

    private function nextMonth(int $now): int
    {
        return (int) gmmktime(0, 0, 0, (int) gmdate('n', $now) + 1, 1, (int) gmdate('Y', $now));
    }

    private function nextDay(int $now): int
    {
        return (int) gmmktime(0, 0, 0, (int) gmdate('n', $now), (int) gmdate('j', $now) + 1, (int) gmdate('Y', $now));
    }
}
