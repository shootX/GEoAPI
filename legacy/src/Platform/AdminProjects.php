<?php

declare(strict_types=1);

namespace Carapi\Platform;

use PDO;

final class AdminProjects
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;
    private const MAX_PAGE = 100000;
    private const MAX_QUERY = 80;
    private const DEFAULT_QUOTA = 10000;
    private const DEFAULT_PER_MINUTE = 60;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{
     *   q:string,status:string,page:int,per:int,total:int,pages:int,
     *   rows:list<array{id:int,name:string,owner_id:int,owner:string,email:string,status:string,created_at:string,used:int,limit:int,remaining:int}>
     * }
     */
    public function search(array $query, int $now): array
    {
        $q = $this->text($query['q'] ?? null);
        $status = $this->choice($query['status'] ?? null, ['active', 'disabled']);
        $per = $this->bounded($query['per'] ?? null, self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE);
        $page = $this->bounded($query['page'] ?? null, 1, self::MAX_PAGE);
        $period = gmdate('Y-m-01', $now);

        $where = [];
        $params = ['period' => $period];
        if ($q !== '') {
            $where[] = '(strpos(lower(p.name), :q) > 0 OR strpos(lower(u.email), :q) > 0 OR strpos(lower(u.first_name), :q) > 0 OR strpos(lower(u.last_name), :q) > 0)';
            $params['q'] = mb_strtolower($q, 'UTF-8');
        }
        if ($status !== '') {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }
        $sqlWhere = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $from = ' FROM platform.projects p JOIN platform.users u ON u.id = p.user_id';

        $count = $this->pdo->prepare('SELECT count(*)' . $from . $sqlWhere);
        $countParams = $params;
        unset($countParams['period']);
        $count->execute($countParams);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $per));
        if ($page > $pages) {
            $page = $pages;
        }

        $stmt = $this->pdo->prepare(<<<SQL
            SELECT p.id, p.name, p.status,
                   to_char(p.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS created_at,
                   u.id AS owner_id, u.first_name, u.last_name, u.email,
                   COALESCE(l.monthly_quota, :quota) AS monthly_quota,
                   COALESCE(m.used_count, 0) AS used_count
            {$from}
            LEFT JOIN platform.project_limits l ON l.project_id = p.id
            LEFT JOIN platform.monthly_usage m ON m.project_id = p.id AND m.period = :period
            {$sqlWhere}
            ORDER BY p.created_at DESC, p.id DESC
            LIMIT :limit OFFSET :offset
        SQL);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue('quota', self::DEFAULT_QUOTA, PDO::PARAM_INT);
        $stmt->bindValue('limit', $per, PDO::PARAM_INT);
        $stmt->bindValue('offset', ($page - 1) * $per, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $limit = (int) $row['monthly_quota'];
            $used = (int) $row['used_count'];
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'owner_id' => (int) $row['owner_id'],
                'owner' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
                'email' => (string) $row['email'],
                'status' => (string) $row['status'],
                'created_at' => (string) $row['created_at'],
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
            ];
        }

        return [
            'q' => $q,
            'status' => $status,
            'page' => $page,
            'per' => $per,
            'total' => $total,
            'pages' => $pages,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *   id:int,name:string,website:string,status:string,created_at:string,updated_at:string,
     *   owner_id:int,owner:string,email:string,owner_status:string,verified:bool,
     *   used:int,limit:int,remaining:int,per_minute:int,
     *   days:list<array{day:string,used:int}>,
     *   keys:list<array{id:int,masked:string,scope:string,created_at:string,expires_at:string,revoked_at:string,last_used_at:string,status:string}>
     * }|null
     */
    public function find(int $id, int $now): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT p.id, p.name, p.website_url, p.status,
                   to_char(p.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS created_at,
                   to_char(p.updated_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS updated_at,
                   u.id AS owner_id, u.first_name, u.last_name, u.email, u.status AS owner_status,
                   CASE WHEN u.email_verified_at IS NULL THEN 0 ELSE 1 END AS verified,
                   COALESCE(l.monthly_quota, :quota) AS monthly_quota,
                   COALESCE(l.per_minute, :minute) AS per_minute,
                   COALESCE(m.used_count, 0) AS used_count
            FROM platform.projects p
            JOIN platform.users u ON u.id = p.user_id
            LEFT JOIN platform.project_limits l ON l.project_id = p.id
            LEFT JOIN platform.monthly_usage m ON m.project_id = p.id AND m.period = :period
            WHERE p.id = :id
            LIMIT 1
        SQL);
        $stmt->bindValue('quota', self::DEFAULT_QUOTA, PDO::PARAM_INT);
        $stmt->bindValue('minute', self::DEFAULT_PER_MINUTE, PDO::PARAM_INT);
        $stmt->bindValue('period', gmdate('Y-m-01', $now));
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $limit = (int) $row['monthly_quota'];
        $used = (int) $row['used_count'];

        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'website' => trim((string) ($row['website_url'] ?? '')),
            'status' => (string) $row['status'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
            'owner_id' => (int) $row['owner_id'],
            'owner' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
            'email' => (string) $row['email'],
            'owner_status' => (string) $row['owner_status'],
            'verified' => (int) $row['verified'] === 1,
            'used' => $used,
            'limit' => $limit,
            'remaining' => max(0, $limit - $used),
            'per_minute' => (int) $row['per_minute'],
            'days' => $this->days($id, $now),
            'keys' => $this->keys($id, $now),
        ];
    }

    /** @return list<array{day:string,used:int}> */
    private function days(int $id, int $now): array
    {
        $today = gmmktime(0, 0, 0, (int) gmdate('n', $now), (int) gmdate('j', $now), (int) gmdate('Y', $now));
        $start = $today - (29 * 86400);
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT day::text AS day, sum(used_count) AS used
            FROM platform.daily_usage
            WHERE project_id = :id AND day >= :start AND day <= :end
            GROUP BY day
        SQL);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->bindValue('start', gmdate('Y-m-d', $start));
        $stmt->bindValue('end', gmdate('Y-m-d', $today));
        $stmt->execute();
        $byDay = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $byDay[(string) $row['day']] = (int) $row['used'];
        }
        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $day = gmdate('Y-m-d', $start + ($i * 86400));
            $days[] = ['day' => $day, 'used' => $byDay[$day] ?? 0];
        }

        return $days;
    }

    /** @return list<array{id:int,masked:string,scope:string,created_at:string,expires_at:string,revoked_at:string,last_used_at:string,status:string}> */
    private function keys(int $id, int $now): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT id, key_selector, scope,
                   to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS created_at,
                   to_char(expires_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS expires_at,
                   to_char(revoked_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS revoked_at,
                   to_char(last_used_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS last_used_at,
                   CASE
                       WHEN revoked_at IS NOT NULL THEN 'revoked'
                       WHEN expires_at IS NOT NULL AND expires_at <= :now THEN 'expired'
                       ELSE 'active'
                   END AS effective_status
            FROM platform.api_keys
            WHERE project_id = :id
            ORDER BY id
        SQL);
        $stmt->bindValue('now', gmdate('Y-m-d H:i:sP', $now));
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $keys = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $keys[] = [
                'id' => (int) $row['id'],
                'masked' => $this->mask((string) $row['key_selector']),
                'scope' => (string) $row['scope'],
                'created_at' => (string) $row['created_at'],
                'expires_at' => (string) ($row['expires_at'] ?? ''),
                'revoked_at' => (string) ($row['revoked_at'] ?? ''),
                'last_used_at' => (string) ($row['last_used_at'] ?? ''),
                'status' => (string) $row['effective_status'],
            ];
        }

        return $keys;
    }

    private function mask(string $selector): string
    {
        $head = substr($selector, 0, 4);
        $tail = strlen($selector) > 4 ? substr($selector, -4) : '';

        return 'carapi_sk_' . $head . '…' . $tail;
    }

    private function text(mixed $value): string
    {
        if (!is_string($value)) {
            return '';
        }
        $text = trim(str_replace("\0", '', $value));
        $text = preg_replace('/[\x00-\x1F\x7F]/u', '', $text) ?? '';
        if (mb_strlen($text) > self::MAX_QUERY) {
            $text = mb_substr($text, 0, self::MAX_QUERY);
        }

        return $text;
    }

    /** @param list<string> $allowed */
    private function choice(mixed $value, array $allowed): string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : '';
    }

    private function bounded(mixed $value, int $default, int $max): int
    {
        $text = is_int($value) ? (string) $value : (is_string($value) ? $value : '');
        if (preg_match('/^[1-9][0-9]*$/', $text) !== 1) {
            return $default;
        }
        $number = (int) $text;

        return $number > $max ? $max : $number;
    }
}
