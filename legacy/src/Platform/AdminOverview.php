<?php

declare(strict_types=1);

namespace Geoapi\Platform;

use PDO;

final class AdminOverview
{
    private const RECENT_LIMIT = 5;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{
     *   period:string,
     *   stats:array<string,int>,
     *   users:list<array{name:string,email:string,status:string,created_at:string}>,
     *   projects:list<array{name:string,email:string,status:string,created_at:string}>
     * }
     */
    public function collect(int $now): array
    {
        $period = gmdate('Y-m-01', $now);
        $instant = gmdate('Y-m-d H:i:sP', $now);
        $stats = $this->pdo->prepare(<<<'SQL'
            SELECT
                (SELECT count(*) FROM platform.users) AS users_total,
                (SELECT count(*) FROM platform.users WHERE status = 'active') AS users_active,
                (SELECT count(*) FROM platform.users WHERE status = 'suspended') AS users_suspended,
                (SELECT count(*) FROM platform.projects) AS projects_total,
                (SELECT count(*) FROM platform.projects WHERE status = 'active') AS projects_active,
                (SELECT count(*) FROM platform.projects WHERE status = 'disabled') AS projects_disabled,
                (SELECT count(*) FROM platform.api_keys) AS keys_total,
                (SELECT count(*) FROM platform.api_keys WHERE revoked_at IS NULL AND (expires_at IS NULL OR expires_at > :now)) AS keys_active,
                (SELECT count(*) FROM platform.api_keys WHERE revoked_at IS NOT NULL) AS keys_revoked,
                (SELECT COALESCE(sum(used_count), 0) FROM platform.monthly_usage WHERE period = :period) AS month_requests
        SQL);
        $stats->execute(['now' => $instant, 'period' => $period]);
        $row = $stats->fetch() ?: [];
        $counts = [];
        foreach ([
            'users_total', 'users_active', 'users_suspended',
            'projects_total', 'projects_active', 'projects_disabled',
            'keys_total', 'keys_active', 'keys_revoked', 'month_requests',
        ] as $key) {
            $counts[$key] = (int) ($row[$key] ?? 0);
        }

        $users = $this->pdo->query(<<<'SQL'
            SELECT first_name, last_name, email, status,
                   to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS created_at
            FROM platform.users
            ORDER BY created_at DESC, id DESC
            LIMIT 5
        SQL);
        $projects = $this->pdo->query(<<<'SQL'
            SELECT p.name, p.status, u.email,
                   to_char(p.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS created_at
            FROM platform.projects p
            JOIN platform.users u ON u.id = p.user_id
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT 5
        SQL);

        return [
            'period' => $period,
            'stats' => $counts,
            'users' => $this->people($users->fetchAll() ?: []),
            'projects' => $this->work($projects->fetchAll() ?: []),
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{name:string,email:string,status:string,created_at:string}>
     */
    private function people(array $rows): array
    {
        $items = [];
        foreach (array_slice($rows, 0, self::RECENT_LIMIT) as $row) {
            $items[] = [
                'name' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
                'email' => (string) $row['email'],
                'status' => (string) $row['status'],
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $items;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{name:string,email:string,status:string,created_at:string}>
     */
    private function work(array $rows): array
    {
        $items = [];
        foreach (array_slice($rows, 0, self::RECENT_LIMIT) as $row) {
            $items[] = [
                'name' => (string) $row['name'],
                'email' => (string) $row['email'],
                'status' => (string) $row['status'],
                'created_at' => (string) $row['created_at'],
            ];
        }

        return $items;
    }
}
