<?php

declare(strict_types=1);

namespace Carapi\Platform;

/**
 * In-process counters for tests. Production uses PostgresUsageStore.
 */
final class MemoryUsageStore implements UsageStore
{
    private static ?self $shared = null;

    /** @var array<string, int> */
    private array $windows = [];

    /** @var array<string, int> */
    private array $months = [];

    /** @var array<string, int> */
    private array $days = [];

    public ?int $perMinute = 60;

    public int $perDay = 2000;

    public int $monthlyQuota = 10000;

    /** When true, a successful read must not become an unmetered 200. */
    public bool $failCommit = false;

    /** @var array<string, array<string, int>> */
    private array $routes = [];

    public static function shared(): self
    {
        return self::$shared ??= new self;
    }

    public static function reset(): void
    {
        self::$shared = new self;
    }

    public function bumpWindow(string $scope, string $subject, int $limit, int $now): array
    {
        $key = $scope.'|'.$subject.'|'.gmdate('Y-m-d H:i', $now);
        $this->windows[$key] = ($this->windows[$key] ?? 0) + 1;

        return [
            'count' => $this->windows[$key],
            'limit' => $limit,
            'exceeded' => $this->windows[$key] > $limit,
            'retry_after' => 60 - ((int) gmdate('s', $now)),
        ];
    }

    public function previewMonth(int $projectId, int $now): ?array
    {
        $used = $this->months[$this->monthKey($projectId, $now)] ?? 0;

        return $this->view($this->monthlyQuota, $used, $this->nextMonth($now));
    }

    public function previewDay(int $projectId, int $now): ?array
    {
        $used = $this->days[$this->dayKey($projectId, $now)] ?? 0;
        $view = $this->view($this->perDay, $used, $this->nextDay($now));
        $view['enforced'] = true;

        return $view;
    }

    public function commitSuccess(int $projectId, string $route, int $now): array
    {
        if ($this->failCommit) {
            throw new UnavailableException;
        }
        $monthKey = $this->monthKey($projectId, $now);
        $used = $this->months[$monthKey] ?? 0;
        if ($used >= $this->monthlyQuota) {
            return ['ok' => false, 'reason' => 'month', 'limit' => $this->monthlyQuota, 'used' => $this->monthlyQuota, 'remaining' => 0, 'reset_at' => $this->nextMonth($now)];
        }
        $dayKey = $this->dayKey($projectId, $now);
        $dayUsed = ($this->days[$dayKey] ?? 0) + 1;
        if ($dayUsed > $this->perDay) {
            return ['ok' => false, 'reason' => 'day', 'limit' => $this->perDay, 'used' => $this->perDay, 'remaining' => 0, 'reset_at' => $this->nextDay($now)];
        }
        $this->months[$monthKey] = $used + 1;
        $this->days[$dayKey] = $dayUsed;
        $used = $this->months[$monthKey];
        $routeKey = $projectId.'|'.gmdate('Y-m-d', $now);
        $this->routes[$routeKey][$route] = ($this->routes[$routeKey][$route] ?? 0) + 1;

        return [
            'ok' => true,
            'reason' => 'ok',
            'limit' => $this->monthlyQuota,
            'used' => $used,
            'remaining' => max(0, $this->monthlyQuota - $used),
            'reset_at' => $this->nextMonth($now),
        ];
    }

    public function stats(int $projectId, int $now): ?array
    {
        $month = $this->previewMonth($projectId, $now);

        $today = gmmktime(0, 0, 0, (int) gmdate('n', $now), (int) gmdate('j', $now), (int) gmdate('Y', $now));
        $start = $today - (29 * 86400);
        $days = [];
        $byRoute = [];
        for ($i = 0; $i < 30; $i++) {
            $day = gmdate('Y-m-d', $start + ($i * 86400));
            $bucket = $this->routes[$projectId.'|'.$day] ?? [];
            $days[] = ['day' => $day, 'used' => array_sum($bucket)];
            foreach ($bucket as $name => $count) {
                $byRoute[$name] = ($byRoute[$name] ?? 0) + $count;
            }
        }
        $routes = [];
        foreach ($byRoute as $name => $count) {
            $routes[] = ['route' => $name, 'used' => $count];
        }

        return [
            'limit' => $month['limit'],
            'used' => $month['used'],
            'remaining' => $month['remaining'],
            'reset_at' => gmdate('Y-m-d\TH:i:s\Z', $month['reset_at']),
            'per_minute' => $this->perMinute ?? 0,
            'per_day' => $this->perDay,
            'days' => $days,
            'routes' => $routes,
        ];
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

    private function monthKey(int $projectId, int $now): string
    {
        return $projectId.'|'.gmdate('Y-m', $now);
    }

    private function dayKey(int $projectId, int $now): string
    {
        return $projectId.'|'.gmdate('Y-m-d', $now);
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
