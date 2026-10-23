<?php

declare(strict_types=1);

namespace Geoapi\Platform;

use PDO;

final class AdminUsers
{
    private const DEFAULT_PER_PAGE = 20;
    private const MAX_PER_PAGE = 100;
    private const MAX_PAGE = 100000;
    private const MAX_QUERY = 80;
    private const DEFAULT_QUOTA = 10000;

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, mixed> $query
     * @return array{
     *   q:string,status:string,role:string,page:int,per:int,total:int,pages:int,
     *   rows:list<array{id:int,name:string,email:string,role:string,status:string,verified:bool,created_at:string,last_login_at:string,projects:int}>
     * }
     */
    public function search(array $query): array
    {
        $q = $this->text($query['q'] ?? null);
        $status = $this->choice($query['status'] ?? null, ['active', 'suspended']);
        $role = $this->choice($query['role'] ?? null, ['admin', 'user']);
        $per = $this->bounded($query['per'] ?? null, self::DEFAULT_PER_PAGE, self::MAX_PER_PAGE);
        $page = $this->bounded($query['page'] ?? null, 1, self::MAX_PAGE);

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(strpos(lower(u.email), :q) > 0 OR strpos(lower(u.first_name), :q) > 0 OR strpos(lower(u.last_name), :q) > 0)';
            $params['q'] = mb_strtolower($q, 'UTF-8');
        }
        if ($status !== '') {
            $where[] = 'u.status = :status';
            $params['status'] = $status;
        }
        if ($role !== '') {
            $where[] = 'u.role = :role';
            $params['role'] = $role;
        }
        $sqlWhere = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $count = $this->pdo->prepare('SELECT count(*) FROM platform.users u' . $sqlWhere);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / $per));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * $per;

        $stmt = $this->pdo->prepare(<<<SQL
            SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.status,
                   CASE WHEN u.email_verified_at IS NULL THEN 0 ELSE 1 END AS verified,
                   to_char(u.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS created_at,
                   to_char(u.last_login_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS last_login_at,
                   (SELECT count(*) FROM platform.projects p WHERE p.user_id = u.id) AS projects
            FROM platform.users u
            {$sqlWhere}
            ORDER BY u.created_at DESC, u.id DESC
            LIMIT :limit OFFSET :offset
        SQL);
        foreach ($params as $name => $value) {
            $stmt->bindValue($name, $value);
        }
        $stmt->bindValue('limit', $per, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'id' => (int) $row['id'],
                'name' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
                'email' => (string) $row['email'],
                'role' => (string) $row['role'],
                'status' => (string) $row['status'],
                'verified' => (int) $row['verified'] === 1,
                'created_at' => (string) $row['created_at'],
                'last_login_at' => (string) ($row['last_login_at'] ?? ''),
                'projects' => (int) $row['projects'],
            ];
        }

        return [
            'q' => $q,
            'status' => $status,
            'role' => $role,
            'page' => $page,
            'per' => $per,
            'total' => $total,
            'pages' => $pages,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{
     *   id:int,name:string,email:string,role:string,status:string,verified:bool,created_at:string,last_login_at:string,
     *   projects:list<array{id:int,name:string,status:string,created_at:string,used:int,limit:int,remaining:int}>
     * }|null
     */
    public function find(int $id, int $now): ?array
    {
        if ($id < 1) {
            return null;
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT id, first_name, last_name, email, role, status,
                   CASE WHEN email_verified_at IS NULL THEN 0 ELSE 1 END AS verified,
                   to_char(created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS created_at,
                   to_char(last_login_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS last_login_at
            FROM platform.users
            WHERE id = :id
            LIMIT 1
        SQL);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false) {
            return null;
        }

        $period = gmdate('Y-m-01', $now);
        $projects = $this->pdo->prepare(<<<'SQL'
            SELECT p.id, p.name, p.status,
                   to_char(p.created_at AT TIME ZONE 'UTC', 'YYYY-MM-DD HH24:MI') AS created_at,
                   COALESCE(l.monthly_quota, :quota) AS monthly_quota,
                   COALESCE(m.used_count, 0) AS used_count
            FROM platform.projects p
            LEFT JOIN platform.project_limits l ON l.project_id = p.id
            LEFT JOIN platform.monthly_usage m ON m.project_id = p.id AND m.period = :period
            WHERE p.user_id = :id
            ORDER BY p.id
        SQL);
        $projects->bindValue('quota', self::DEFAULT_QUOTA, PDO::PARAM_INT);
        $projects->bindValue('period', $period);
        $projects->bindValue('id', $id, PDO::PARAM_INT);
        $projects->execute();

        $owned = [];
        foreach ($projects->fetchAll() ?: [] as $project) {
            $limit = (int) $project['monthly_quota'];
            $used = (int) $project['used_count'];
            $owned[] = [
                'id' => (int) $project['id'],
                'name' => (string) $project['name'],
                'status' => (string) $project['status'],
                'created_at' => (string) $project['created_at'],
                'used' => $used,
                'limit' => $limit,
                'remaining' => max(0, $limit - $used),
            ];
        }

        return [
            'id' => (int) $row['id'],
            'name' => trim((string) $row['first_name'] . ' ' . (string) $row['last_name']),
            'email' => (string) $row['email'],
            'role' => (string) $row['role'],
            'status' => (string) $row['status'],
            'verified' => (int) $row['verified'] === 1,
            'created_at' => (string) $row['created_at'],
            'last_login_at' => (string) ($row['last_login_at'] ?? ''),
            'projects' => $owned,
        ];
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
