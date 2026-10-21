<?php

declare(strict_types=1);

namespace Carapi\Platform;

final class UsageLimiter
{
    public function __construct(private UsageStore $store) {}

    public static function fromEnv(): self
    {
        if (self::testing()) {
            $sqlite = self::sqlitePath();
            if ($sqlite !== null) {
                return new self(new SqliteUsageStore($sqlite));
            }
            if (self::memoryFlag()) {
                return new self(MemoryUsageStore::shared());
            }
        }

        return new self(new PostgresUsageStore(Connection::pdo()));
    }

    private static function testing(): bool
    {
        if (! function_exists('app')) {
            return false;
        }
        try {
            return app()->environment('testing');
        } catch (\Throwable) {
            return false;
        }
    }

    private static function memoryFlag(): bool
    {
        $flag = carapi_env('CARAPI_USAGE_MEMORY');

        return is_string($flag) && in_array(strtolower(trim($flag)), ['1', 'true', 'on', 'yes'], true);
    }

    private static function sqlitePath(): ?string
    {
        $path = carapi_env('CARAPI_USAGE_SQLITE');
        if (! is_string($path)) {
            return null;
        }
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '://')) {
            return null;
        }

        return $path;
    }

    public static function now(): int
    {
        $raw = carapi_env('CARAPI_USAGE_NOW');

        return is_string($raw) && preg_match('/^[0-9]+$/', $raw) ? (int) $raw : time();
    }

    public static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        return is_string($ip) && $ip !== '' ? $ip : '0.0.0.0';
    }

    public static function ipLimit(): int
    {
        $raw = carapi_env('CARAPI_IP_PER_MINUTE');

        return is_string($raw) && preg_match('/^[0-9]+$/', $raw) ? (int) $raw : 300;
    }

    /** @return array{exceeded:bool,retry_after:int} */
    public function hitIp(string $ip, int $now): array
    {
        $hit = $this->store->bumpWindow('ip', $ip, self::ipLimit(), $now);

        return ['exceeded' => $hit['exceeded'], 'retry_after' => $hit['retry_after']];
    }

    /** @return array{exceeded:bool,retry_after:int,missing:bool,limit:int,count:int,reset_at:int} */
    public function hitProject(int $projectId, int $now): array
    {
        $limit = $this->perMinute($projectId);
        if ($limit === null) {
            return ['exceeded' => true, 'retry_after' => 60, 'missing' => true, 'limit' => 0, 'count' => 0, 'reset_at' => $now + 60];
        }
        $hit = $this->store->bumpWindow('project', (string) $projectId, $limit, $now);
        $reset = $now - ((int) gmdate('s', $now)) + 60;

        return [
            'exceeded' => $hit['exceeded'],
            'retry_after' => $hit['retry_after'],
            'missing' => false,
            'limit' => $limit,
            'count' => $hit['count'],
            'reset_at' => $reset,
        ];
    }

    private function perMinute(int $projectId): ?int
    {
        if ($this->store instanceof PostgresUsageStore) {
            return $this->store->perMinute($projectId);
        }
        if ($this->store instanceof MemoryUsageStore) {
            return $this->store->perMinute;
        }
        if ($this->store instanceof SqliteUsageStore) {
            return $this->store->perMinute($projectId);
        }

        throw new UnavailableException;
    }

    /** @return array{limit:int,used:int,remaining:int,exhausted:bool,reset_at:int}|null */
    public function previewMonth(int $projectId, int $now): ?array
    {
        return $this->store->previewMonth($projectId, $now);
    }

    /** @return array{limit:int,used:int,remaining:int,exhausted:bool,reset_at:int,enforced:bool}|null */
    public function previewDay(int $projectId, int $now): ?array
    {
        return $this->store->previewDay($projectId, $now);
    }

    /** @return array{ok:bool,limit:int,used:int,remaining:int,reset_at:int} */
    public function commitSuccess(int $projectId, string $route, int $now): array
    {
        return $this->store->commitSuccess($projectId, $route, $now);
    }

    /** @return array{limit:int,used:int,remaining:int,reset_at:string,per_minute:int,days:list<array{day:string,used:int}>,routes:list<array{route:string,used:int}>}|null */
    public function storeStats(int $projectId): ?array
    {
        return $this->store->stats($projectId, self::now());
    }
}
