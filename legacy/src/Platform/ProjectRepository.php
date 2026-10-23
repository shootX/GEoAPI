<?php

declare(strict_types=1);

namespace Geoapi\Platform;

use App\Billing\V2\Gate;
use App\Billing\V2\ProjectLimitReached;
use PDO;

final class ProjectRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return list<array<string, mixed>> */
    public function forUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, name, website_url, status, created_at, updated_at FROM platform.projects WHERE user_id = :user_id ORDER BY id'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @return array<string, mixed>|null */
    public function findOwned(int $userId, int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, name, website_url, status, created_at, updated_at FROM platform.projects WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function insert(int $userId, string $name, ?string $website): int
    {
        if (class_exists(Gate::class)) {
            Gate::prepareForCreate($userId);
            $blocked = Gate::blockCreate($userId);
            if ($blocked !== null) {
                throw new ProjectLimitReached($blocked);
            }
        }
        $limitsSql = $this->limitsInsert($userId);
        $nested = $this->pdo->inTransaction();
        if ($nested) {
            $this->pdo->exec('SAVEPOINT project_insert');
        } else {
            $this->pdo->beginTransaction();
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO platform.projects (user_id, name, website_url) VALUES (:user_id, :name, :website) RETURNING id'
            );
            $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
            $stmt->bindValue('name', $name);
            $stmt->bindValue('website', $website);
            $stmt->execute();
            $id = (int) $stmt->fetchColumn();
            $stmt->closeCursor();
            $limits = $this->pdo->prepare($limitsSql);
            $limits->bindValue('id', $id, PDO::PARAM_INT);
            $limits->execute();
            $limits->closeCursor();
            if ($nested) {
                $this->pdo->exec('RELEASE SAVEPOINT project_insert');
            } else {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($nested && $this->pdo->inTransaction()) {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT project_insert');
            } elseif ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        return $id;
    }

    public function update(int $userId, int $id, string $name, ?string $website): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE platform.projects SET name = :name, website_url = :website, updated_at = now() WHERE id = :id AND user_id = :user_id'
        );
        $stmt->bindValue('name', $name);
        $stmt->bindValue('website', $website);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $this->wrote($stmt);
    }

    public function setStatus(int $userId, int $id, string $status): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE platform.projects SET status = :status, updated_at = now() WHERE id = :id AND user_id = :user_id'
        );
        $stmt->bindValue('status', $status);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $this->wrote($stmt);
    }

    /** @return list<array<string, mixed>> */
    public function keys(int $userId, int $projectId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT k.id, k.project_id, k.name, k.key_selector, k.scope, k.created_at, k.expires_at, k.revoked_at, k.last_used_at
            FROM platform.api_keys k
            JOIN platform.projects p ON p.id = k.project_id
            WHERE k.project_id = :project_id AND p.user_id = :user_id
            ORDER BY k.id
        SQL);
        $stmt->bindValue('project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function insertKey(int $userId, int $projectId, string $name, string $selector, string $secretHash, ?string $expiresAt): bool
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO platform.api_keys (project_id, name, key_selector, secret_hash, expires_at)
            SELECT p.id, :name, :selector, :secret_hash, :expires_at
            FROM platform.projects p
            WHERE p.id = :project_id AND p.user_id = :user_id
        SQL);
        $stmt->bindValue('name', $name);
        $stmt->bindValue('selector', $selector);
        $stmt->bindValue('secret_hash', $secretHash);
        $stmt->bindValue('expires_at', $expiresAt);
        $stmt->bindValue('project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $this->wrote($stmt);
    }

    public function revoke(int $userId, int $projectId, int $keyId): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->prepare(<<<'SQL'
                UPDATE platform.api_keys
                SET revoked_at = now()
                WHERE id = :key_id AND project_id = :project_id AND revoked_at IS NULL
                  AND project_id IN (SELECT id FROM platform.projects WHERE user_id = :user_id)
            SQL);
        } else {
            $stmt = $this->pdo->prepare(<<<'SQL'
                UPDATE platform.api_keys k
                SET revoked_at = now()
                FROM platform.projects p
                WHERE k.id = :key_id AND k.project_id = :project_id AND k.project_id = p.id AND p.user_id = :user_id AND k.revoked_at IS NULL
            SQL);
        }
        $stmt->bindValue('key_id', $keyId, PDO::PARAM_INT);
        $stmt->bindValue('project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $this->wrote($stmt);
    }

    private function wrote(\PDOStatement $stmt): bool
    {
        if ($stmt->rowCount() > 0) {
            return true;
        }
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            return false;
        }
        $changes = $this->pdo->query('SELECT changes()');

        return $changes !== false && (int) $changes->fetchColumn() > 0;
    }

    private function limitsInsert(int $userId): string
    {
        $grandfathered = $this->grandfathered($userId);
        $quota = $grandfathered ? 10000 : 100;
        $columns = ['project_id', 'per_minute', 'monthly_quota'];
        $values = [':id', '60', (string) $quota];
        if ($this->hasColumn('project_limits', 'per_day')) {
            $columns[] = 'per_day';
            $values[] = '2000';
        }
        if ($this->hasColumn('project_limits', 'plan_code')) {
            $columns[] = 'plan_code';
            $values[] = $grandfathered ? 'NULL' : "'free'";
        }
        if ($this->hasColumn('project_limits', 'unlimited_month')) {
            $columns[] = 'unlimited_month';
            $values[] = 'FALSE'; // boolean column: an integer literal fails on Postgres (42804)
        }

        return 'INSERT INTO platform.project_limits ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).')';
    }

    private function grandfathered(int $userId): bool
    {
        if (! $this->hasColumn('users', 'grandfathered')) {
            return true;
        }
        $stmt = $this->pdo->prepare('SELECT grandfathered FROM platform.users WHERE id = :id');
        $stmt->bindValue('id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();
        if ($row === false) {
            return true;
        }
        $value = $row['grandfathered'];

        return ! in_array($value, [0, '0', false, 'f', 'F', 'false'], true);
    }

    private function hasColumn(string $table, string $column): bool
    {
        if (! in_array($table, ['users', 'project_limits'], true) || ! preg_match('/^[a-z_]+$/', $column)) {
            return false;
        }
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $this->pdo->query('PRAGMA platform.table_info('.$table.')');
            foreach ($stmt ? $stmt->fetchAll() : [] as $info) {
                if (($info['name'] ?? '') === $column) {
                    return true;
                }
            }

            return false;
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'platform'
              AND table_name = :table
              AND column_name = :column
        SQL);
        $stmt->execute(['table' => $table, 'column' => $column]);

        return $stmt->fetchColumn() !== false;
    }
}
