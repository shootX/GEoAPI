<?php

declare(strict_types=1);

namespace Carapi\Platform;

use PDO;

final class PostgresUsageStore implements UsageStore
{
    public function __construct(private PDO $pdo)
    {
    }

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

        return $this->monthView($limits['monthly_quota'], $used, $now);
    }

    public function commitSuccess(int $projectId, string $route, int $now): array
    {
        $limits = $this->limits($projectId);
        if ($limits === null) {
            throw new UnavailableException();
        }
        $period = gmdate('Y-m-01', $now);
        $day = gmdate('Y-m-d', $now);
        $this->pdo->beginTransaction();
        $month = $this->pdo->prepare(<<<'SQL'
            INSERT INTO platform.monthly_usage (project_id, period, used_count)
            VALUES (:id, :period, 1)
            ON CONFLICT (project_id, period)
            DO UPDATE SET used_count = platform.monthly_usage.used_count + 1
            WHERE platform.monthly_usage.used_count < :quota
            RETURNING used_count
        SQL);
        $month->execute(['id' => $projectId, 'period' => $period, 'quota' => $limits['monthly_quota']]);
        $used = $month->fetchColumn();
        if ($used === false) {
            $this->pdo->rollBack();

            return ['ok' => false, 'limit' => $limits['monthly_quota'], 'used' => $limits['monthly_quota'], 'remaining' => 0, 'reset_at' => $this->nextMonth($now)];
        }
        $daily = $this->pdo->prepare(<<<'SQL'
            INSERT INTO platform.daily_usage (project_id, day, route, used_count)
            VALUES (:id, :day, :route, 1)
            ON CONFLICT (project_id, day, route)
            DO UPDATE SET used_count = platform.daily_usage.used_count + 1
        SQL);
        $daily->execute(['id' => $projectId, 'day' => $day, 'route' => $route]);
        $this->pdo->commit();
        $used = (int) $used;

        return [
            'ok' => true,
            'limit' => $limits['monthly_quota'],
            'used' => $used,
            'remaining' => max(0, $limits['monthly_quota'] - $used),
            'reset_at' => $this->nextMonth($now),
        ];
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
        $view = $this->monthView($limits['monthly_quota'], $used, $now);

        return [
            'limit' => $view['limit'],
            'used' => $view['used'],
            'remaining' => $view['remaining'],
            'reset_at' => gmdate('Y-m-d\TH:i:s\Z', $view['reset_at']),
            'per_minute' => $limits['per_minute'],
            'days' => $days,
            'routes' => $routes,
        ];
    }

    public function perMinute(int $projectId): ?int
    {
        $limits = $this->limits($projectId);

        return $limits['per_minute'] ?? null;
    }

    /** @return array{per_minute:int,monthly_quota:int}|null */
    private function limits(int $projectId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT per_minute, monthly_quota FROM platform.project_limits WHERE project_id = :id'
        );
        $stmt->execute(['id' => $projectId]);
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        return ['per_minute' => (int) $row['per_minute'], 'monthly_quota' => (int) $row['monthly_quota']];
    }

    /** @return array{limit:int,used:int,remaining:int,exhausted:bool,reset_at:int} */
    private function monthView(int $limit, int $used, int $now): array
    {
        return [
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            'exhausted' => $used >= $limit,
            'reset_at' => $this->nextMonth($now),
        ];
    }

    private function nextMonth(int $now): int
    {
        return (int) gmmktime(0, 0, 0, (int) gmdate('n', $now) + 1, 1, (int) gmdate('Y', $now));
    }
}
