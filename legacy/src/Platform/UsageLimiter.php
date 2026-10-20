<?php

declare(strict_types=1);

namespace Carapi\Platform;

final class UsageLimiter
{
    public function __construct(private UsageStore $store)
    {
    }

    public static function fromEnv(): self
    {
        return new self(new PostgresUsageStore(Connection::pdo()));
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

    /** @return array{exceeded:bool,retry_after:int,missing:bool} */
    public function hitProject(int $projectId, int $now): array
    {
        if (!$this->store instanceof PostgresUsageStore) {
            throw new UnavailableException();
        }
        $limit = $this->store->perMinute($projectId);
        if ($limit === null) {
            return ['exceeded' => true, 'retry_after' => 60, 'missing' => true];
        }
        $hit = $this->store->bumpWindow('project', (string) $projectId, $limit, $now);

        return ['exceeded' => $hit['exceeded'], 'retry_after' => $hit['retry_after'], 'missing' => false];
    }

    /** @return array{limit:int,used:int,remaining:int,exhausted:bool,reset_at:int}|null */
    public function previewMonth(int $projectId, int $now): ?array
    {
        return $this->store->previewMonth($projectId, $now);
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
