<?php

declare(strict_types=1);

namespace Geoapi\Platform;

use PDO;

final class AdminUsage
{
    /**
     * @param array<string, mixed> $query
     * @return array{
     *   period:string,month_total:int,window_total:int,project_count:int,route_count:int,
     *   filter:?int,filter_missing:bool,filter_name:string,chart_total:int,
     *   days:list<array{day:string,used:int}>,
     *   routes:list<array{route:string,used:int}>,
     *   top:list<array{id:int,name:string,email:string,used:int,percent:string}>
     * }
     */
    public function collect(PDO $pdo, array $query, int $now): array
    {
        [$start, $end] = self::window($now);
        $period = gmdate('Y-m-01', $now);
        $month = $pdo->prepare('SELECT COALESCE(sum(used_count), 0) FROM platform.monthly_usage WHERE period = :period');
        $month->bindValue('period', $period);
        $month->execute();
        $totals = $pdo->prepare(<<<'SQL'
            SELECT COALESCE(sum(used_count), 0) AS used,
                   count(DISTINCT project_id) AS projects,
                   count(DISTINCT route) AS routes
            FROM platform.daily_usage
            WHERE day >= :start AND day <= :end
        SQL);
        $totals->bindValue('start', $start);
        $totals->bindValue('end', $end);
        $totals->execute();
        $summary = $totals->fetch() ?: [];
        $windowTotal = (int) ($summary['used'] ?? 0);

        $filter = self::projectId($query['project'] ?? null);
        $filterMissing = false;
        $filterName = '';
        $scope = null;
        if ($filter !== null) {
            $project = $pdo->prepare('SELECT name FROM platform.projects WHERE id = :id');
            $project->bindValue('id', $filter, PDO::PARAM_INT);
            $project->execute();
            $name = $project->fetchColumn();
            if (!is_string($name)) {
                $filterMissing = true;
                $filter = null;
            } else {
                $filterName = $name;
                $scope = (int) $project->queryString === 0 ? null : $filter;
                $scope = $filter;
            }
        }

        return [
            'period' => substr($period, 0, 7),
            'month_total' => (int) $month->fetchColumn(),
            'window_total' => $windowTotal,
            'project_count' => (int) ($summary['projects'] ?? 0),
            'route_count' => (int) ($summary['routes'] ?? 0),
            'filter' => $filterMissing ? null : $scope,
            'filter_missing' => $filterMissing,
            'filter_name' => $filterName,
            'chart_total' => $filterMissing ? 0 : ($scope === null ? $windowTotal : $this->scopedTotal($pdo, $start, $end, $scope)),
            'days' => $filterMissing ? self::emptyDays($start) : $this->days($pdo, $start, $end, $scope),
            'routes' => $filterMissing ? [] : $this->routes($pdo, $start, $end, $scope),
            'top' => $this->top($pdo, $start, $end, $windowTotal),
        ];
    }

    public static function projectId(mixed $value): ?int
    {
        $text = is_int($value) ? (string) $value : (is_string($value) ? $value : '');
        if (preg_match('/^[1-9][0-9]*$/', $text) !== 1) {
            return null;
        }

        return (int) $text;
    }

    /** @return array{0:string,1:string} */
    public static function window(int $now): array
    {
        $today = gmmktime(0, 0, 0, (int) gmdate('n', $now), (int) gmdate('j', $now), (int) gmdate('Y', $now));

        return [gmdate('Y-m-d', $today - (29 * 86400)), gmdate('Y-m-d', $today)];
    }

    /** @return list<array{day:string,used:int}> */
    private function days(PDO $pdo, string $start, string $end, ?int $projectId): array
    {
        $sql = 'SELECT day::text AS day, sum(used_count) AS used FROM platform.daily_usage WHERE day >= :start AND day <= :end';
        if ($projectId !== null) {
            $sql .= ' AND project_id = :project';
        }
        $sql .= ' GROUP BY day';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('start', $start);
        $stmt->bindValue('end', $end);
        if ($projectId !== null) {
            $stmt->bindValue('project', $projectId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $byDay = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $byDay[(string) $row['day']] = (int) $row['used'];
        }
        $days = [];
        foreach (self::dates($start) as $day) {
            $days[] = ['day' => $day, 'used' => $byDay[$day] ?? 0];
        }

        return $days;
    }

    /** @return list<array{route:string,used:int}> */
    private function routes(PDO $pdo, string $start, string $end, ?int $projectId): array
    {
        $sql = 'SELECT route, sum(used_count) AS used FROM platform.daily_usage WHERE day >= :start AND day <= :end';
        if ($projectId !== null) {
            $sql .= ' AND project_id = :project';
        }
        $sql .= ' GROUP BY route ORDER BY used DESC, route ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue('start', $start);
        $stmt->bindValue('end', $end);
        if ($projectId !== null) {
            $stmt->bindValue('project', $projectId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $routes = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $routes[] = ['route' => (string) $row['route'], 'used' => (int) $row['used']];
        }

        return $routes;
    }

    /** @return list<array{id:int,name:string,email:string,used:int,percent:string}> */
    private function top(PDO $pdo, string $start, string $end, int $windowTotal): array
    {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT p.id, p.name, u.email, sum(d.used_count) AS used
            FROM platform.daily_usage d
            JOIN platform.projects p ON p.id = d.project_id
            JOIN platform.users u ON u.id = p.user_id
            WHERE d.day >= :start AND d.day <= :end
            GROUP BY p.id, p.name, u.email
            HAVING sum(d.used_count) > 0
            ORDER BY used DESC, p.id ASC
            LIMIT 10
        SQL);
        $stmt->bindValue('start', $start);
        $stmt->bindValue('end', $end);
        $stmt->execute();
        $top = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $used = (int) $row['used'];
            $top[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
                'used' => $used,
                'percent' => self::percent($used, $windowTotal),
            ];
        }

        return $top;
    }

    private function scopedTotal(PDO $pdo, string $start, string $end, int $projectId): int
    {
        $stmt = $pdo->prepare(
            'SELECT COALESCE(sum(used_count), 0) FROM platform.daily_usage WHERE project_id = :project AND day >= :start AND day <= :end'
        );
        $stmt->bindValue('project', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('start', $start);
        $stmt->bindValue('end', $end);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /** @return list<array{day:string,used:int}> */
    private static function emptyDays(string $start): array
    {
        $days = [];
        foreach (self::dates($start) as $day) {
            $days[] = ['day' => $day, 'used' => 0];
        }

        return $days;
    }

    /** @return list<string> */
    private static function dates(string $start): array
    {
        $origin = strtotime($start . ' 00:00:00 UTC');
        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $days[] = gmdate('Y-m-d', (int) $origin + ($i * 86400));
        }

        return $days;
    }

    private static function percent(int $used, int $total): string
    {
        if ($total <= 0 || $used <= 0) {
            return '0';
        }
        $tenths = intdiv($used * 1000, $total);
        $whole = intdiv($tenths, 10);
        $fraction = $tenths % 10;

        return $fraction === 0 ? (string) $whole : $whole . '.' . $fraction;
    }
}
