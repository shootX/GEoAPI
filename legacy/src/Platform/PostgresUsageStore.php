<?php

declare(strict_types=1);

namespace Geoapi\Platform;

use PDO;
use PDOException;

final class PostgresUsageStore implements UsageStore
{
    private ?bool $perDayColumn = null;

    private string|false|null $planMode = null;

    private bool $usageSavepoint = false;

    public function __construct(private PDO $pdo) {}

    public function bumpWindow(string $scope, string $subject, int $limit, int $now): array
    {
        $start = gmdate('Y-m-d H:i:00+00', $now);
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO platform.rate_windows (scope, subject, window_start, hit_count)
            VALUES (:scope, :subject, :window_start, 1)
            ON CONFLICT (scope, subject, window_start)
            DO UPDATE SET hit_count = platform.rate_windows.hit_count + 1
            RETURNING hit_count
        SQL);
        $stmt->execute(['scope' => $scope, 'subject' => $subject, 'window_start' => $start]);
        $count = (int) $stmt->fetchColumn();

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
        $period = gmdate('Y-m-01', $now);
        $stmt = $this->pdo->prepare(
            'SELECT used_count FROM platform.monthly_usage WHERE project_id = :id AND period = :period'
        );
        $stmt->execute(['id' => $projectId, 'period' => $period]);
        $used = (int) ($stmt->fetchColumn() ?: 0);

        return $this->monthView($limits['monthly_quota'], $used, $now, (bool) ($limits['unlimited'] ?? false));
    }

    public function previewDay(int $projectId, int $now): ?array
    {
        $limits = $this->limits($projectId);
        if ($limits === null) {
            return null;
        }
        if ($limits['per_day'] === null) {
            return [
                'limit' => 0,
                'used' => 0,
                'remaining' => 0,
                'exhausted' => false,
                'reset_at' => $this->nextDay($now),
                'enforced' => false,
            ];
        }
        $used = $this->dayUsed($projectId, $now);
        $view = [
            'limit' => $limits['per_day'],
            'used' => $used,
            'remaining' => max(0, $limits['per_day'] - $used),
            'exhausted' => $used >= $limits['per_day'],
            'reset_at' => $this->nextDay($now),
            'enforced' => true,
        ];

        return $view;
    }

    public function commitSuccess(int $projectId, string $route, int $now): array
    {
        $limits = $this->limits($projectId);
        if ($limits === null) {
            throw new UnavailableException;
        }
        $period = gmdate('Y-m-01', $now);
        $day = gmdate('Y-m-d', $now);
        $this->beginUsage();
        $unlimited = (bool) ($limits['unlimited'] ?? false);
        if ($unlimited) {
            $month = $this->pdo->prepare(<<<'SQL'
                INSERT INTO platform.monthly_usage (project_id, period, used_count)
                VALUES (:id, :period, 1)
                ON CONFLICT (project_id, period)
                DO UPDATE SET used_count = platform.monthly_usage.used_count + 1
                RETURNING used_count
            SQL);
            $month->execute(['id' => $projectId, 'period' => $period]);
        } else {
            $month = $this->pdo->prepare(<<<'SQL'
                INSERT INTO platform.monthly_usage (project_id, period, used_count)
                VALUES (:id, :period, 1)
                ON CONFLICT (project_id, period)
                DO UPDATE SET used_count = platform.monthly_usage.used_count + 1
                WHERE platform.monthly_usage.used_count < :quota
                RETURNING used_count
            SQL);
            $month->execute(['id' => $projectId, 'period' => $period, 'quota' => $limits['monthly_quota']]);
        }
        $used = $month->fetchColumn();
        $month->closeCursor();
        unset($month);
        if ($used === false) {
            $this->rollBackUsage();

            return ['ok' => false, 'reason' => 'month', 'limit' => $limits['monthly_quota'], 'used' => $limits['monthly_quota'], 'remaining' => 0, 'reset_at' => $this->nextMonth($now)];
        }
        $daily = $this->pdo->prepare(<<<'SQL'
            INSERT INTO platform.daily_usage (project_id, day, route, used_count)
            VALUES (:id, :day, :route, 1)
            ON CONFLICT (project_id, day, route)
            DO UPDATE SET used_count = platform.daily_usage.used_count + 1
        SQL);
        $daily->execute(['id' => $projectId, 'day' => $day, 'route' => $route]);
        $daily->closeCursor();
        unset($daily);
        if ($limits['per_day'] !== null) {
            $dayUsed = $this->dayUsed($projectId, $now);
            if ($dayUsed > $limits['per_day']) {
                $this->rollBackUsage();

                return ['ok' => false, 'reason' => 'day', 'limit' => $limits['per_day'], 'used' => $limits['per_day'], 'remaining' => 0, 'reset_at' => $this->nextDay($now)];
            }
        }
        $this->commitUsage();
        $used = (int) $used;

        return [
            'ok' => true,
            'limit' => $limits['monthly_quota'],
            'used' => $used,
            'remaining' => $unlimited ? $used : max(0, $limits['monthly_quota'] - $used),
            'reset_at' => $this->nextMonth($now),
            'unlimited' => $unlimited,
        ];
    }

    public function recordDaily(int $projectId, string $route, int $now): void
    {
        $daily = $this->pdo->prepare(<<<'SQL'
            INSERT INTO platform.daily_usage (project_id, day, route, used_count)
            VALUES (:id, :day, :route, 1)
            ON CONFLICT (project_id, day, route)
            DO UPDATE SET used_count = platform.daily_usage.used_count + 1
        SQL);
        $daily->execute(['id' => $projectId, 'day' => gmdate('Y-m-d', $now), 'route' => $route]);
        $daily->closeCursor();
    }

    public function stats(int $projectId, int $now): ?array
    {
        $limits = $this->limits($projectId);
        if ($limits === null) {
            return null;
        }
        $period = gmdate('Y-m-01', $now);
        $stmt = $this->pdo->prepare(
            'SELECT used_count FROM platform.monthly_usage WHERE project_id = :id AND period = :period'
        );
        $stmt->execute(['id' => $projectId, 'period' => $period]);
        $used = (int) ($stmt->fetchColumn() ?: 0);
        $today = gmmktime(0, 0, 0, (int) gmdate('n', $now), (int) gmdate('j', $now), (int) gmdate('Y', $now));
        $start = $today - (29 * 86400);
        $daily = $this->pdo->prepare(
            'SELECT day::text AS day, route, used_count FROM platform.daily_usage WHERE project_id = :id AND day >= :start AND day <= :end ORDER BY day, route'
        );
        $daily->execute([
            'id' => $projectId,
            'start' => gmdate('Y-m-d', $start),
            'end' => gmdate('Y-m-d', $today),
        ]);
        $byDay = [];
        $byRoute = [];
        foreach ($daily->fetchAll() ?: [] as $row) {
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
        usort($routes, static function (array $left, array $right): int {
            if ($left['used'] === $right['used']) {
                return strcmp($left['route'], $right['route']);
            }

            return $right['used'] <=> $left['used'];
        });
        $view = $this->monthView($limits['monthly_quota'], $used, $now, (bool) ($limits['unlimited'] ?? false));

        return [
            'limit' => $view['limit'],
            'used' => $view['used'],
            'remaining' => $view['remaining'],
            'reset_at' => gmdate('Y-m-d\TH:i:s\Z', $view['reset_at']),
            'per_minute' => $limits['per_minute'],
            'per_day' => $limits['per_day'],
            'days' => $days,
            'routes' => $routes,
        ];
    }

    public function perMinute(int $projectId): ?int
    {
        $limits = $this->limits($projectId);

        return $limits['per_minute'] ?? null;
    }

    /** @return array{per_minute:int,monthly_quota:int,per_day:?int,unlimited:bool}|null */
    private function limits(int $projectId): ?array
    {
        if ($this->planMode !== false) {
            $withDay = $this->planMode !== 'noday';
            $sql = $withDay
                ? 'SELECT per_minute, monthly_quota, per_day, plan_code, plan_ends_at, unlimited_month FROM platform.project_limits WHERE project_id = :id'
                : 'SELECT per_minute, monthly_quota, plan_code, plan_ends_at, unlimited_month FROM platform.project_limits WHERE project_id = :id';
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute(['id' => $projectId]);
                $row = $stmt->fetch();
                $this->planMode = $withDay ? 'full' : 'noday';
                $this->perDayColumn = $withDay;
                if ($row === false) {
                    return null;
                }

                return $this->shaped($row, $withDay);
            } catch (PDOException $e) {
                if ($withDay && $this->missingColumn($e, 'per_day') && ! $this->missingColumn($e, 'plan_code')) {
                    $this->planMode = 'noday';

                    return $this->limits($projectId);
                }
                if ($this->missingColumn($e, 'plan_code') || $this->missingColumn($e, 'plan_ends_at') || $this->missingColumn($e, 'unlimited_month') || $this->missingColumn($e, 'per_day')) {
                    $this->planMode = false;
                } else {
                    throw $e;
                }
            }
        }
        if ($this->perDayColumn !== false) {
            try {
                $stmt = $this->pdo->prepare(
                    'SELECT per_minute, monthly_quota, per_day FROM platform.project_limits WHERE project_id = :id'
                );
                $stmt->execute(['id' => $projectId]);
                $row = $stmt->fetch();
                $this->perDayColumn = true;
                if ($row === false) {
                    return null;
                }

                return [
                    'per_minute' => (int) $row['per_minute'],
                    'monthly_quota' => (int) $row['monthly_quota'],
                    'per_day' => (int) $row['per_day'],
                    'unlimited' => false,
                ];
            } catch (PDOException $e) {
                if ($this->perDayColumn === true || ($e->getCode() !== '42703' && ! str_contains($e->getMessage(), 'per_day'))) {
                    throw $e;
                }
                $this->perDayColumn = false;
            }
        }
        $stmt = $this->pdo->prepare(
            'SELECT per_minute, monthly_quota FROM platform.project_limits WHERE project_id = :id'
        );
        $stmt->execute(['id' => $projectId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'per_minute' => (int) $row['per_minute'],
            'monthly_quota' => (int) $row['monthly_quota'],
            'per_day' => null,
            'unlimited' => false,
        ];
    }

    /** @param  array<string, mixed>  $row
     * @return array{per_minute:int,monthly_quota:int,per_day:?int,unlimited:bool}
     */
    private function shaped(array $row, bool $withDay): array
    {
        $plan = isset($row['plan_code']) && $row['plan_code'] !== null && $row['plan_code'] !== '' ? (string) $row['plan_code'] : null;
        $ends = $row['plan_ends_at'] ?? null;
        $expired = is_string($ends) && $ends !== '' && strtotime($ends) !== false && strtotime($ends) <= time();
        $paid = $plan !== null && ! in_array($plan, ['free', 'cars-included'], true);
        $unlimited = $this->truthy($row['unlimited_month'] ?? false);
        $quota = (int) $row['monthly_quota'];
        if ($paid && $expired) {
            $quota = 100;
            $unlimited = false;
        }

        return [
            'per_minute' => (int) $row['per_minute'],
            'monthly_quota' => $quota,
            'per_day' => $withDay ? (int) $row['per_day'] : null,
            'unlimited' => $unlimited,
        ];
    }

    /**
     * A caller that already holds a transaction still has to count. The savepoint
     * rolls back only this write. Cursors are closed first because SQLite will
     * not release a savepoint while a statement is open.
     */
    private function beginUsage(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->exec('SAVEPOINT usage_commit');
            $this->usageSavepoint = true;

            return;
        }
        $this->usageSavepoint = false;
        $this->pdo->beginTransaction();
    }

    private function commitUsage(): void
    {
        if ($this->usageSavepoint) {
            $this->pdo->exec('RELEASE SAVEPOINT usage_commit');
            $this->usageSavepoint = false;

            return;
        }
        $this->pdo->commit();
    }

    private function rollBackUsage(): void
    {
        if ($this->usageSavepoint) {
            $this->pdo->exec('ROLLBACK TO SAVEPOINT usage_commit');
            $this->pdo->exec('RELEASE SAVEPOINT usage_commit');
            $this->usageSavepoint = false;

            return;
        }
        $this->pdo->rollBack();
    }

    private function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }

    private function missingColumn(PDOException $e, string $column): bool
    {
        return $e->getCode() === '42703' || str_contains($e->getMessage(), $column);
    }

    private function dayUsed(int $projectId, int $now): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(sum(used_count), 0) FROM platform.daily_usage WHERE project_id = :id AND day = :day'
        );
        $stmt->execute(['id' => $projectId, 'day' => gmdate('Y-m-d', $now)]);
        $used = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        return $used;
    }

    /** @return array{limit:int,used:int,remaining:int,exhausted:bool,reset_at:int,unlimited:bool} */
    private function monthView(int $limit, int $used, int $now, bool $unlimited = false): array
    {
        if ($unlimited) {
            return [
                'limit' => $limit,
                'used' => $used,
                'remaining' => $used,
                'exhausted' => false,
                'reset_at' => $this->nextMonth($now),
                'unlimited' => true,
            ];
        }

        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'exhausted' => $used >= $limit,
            'reset_at' => $this->nextMonth($now),
            'unlimited' => false,
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
