<?php

declare(strict_types=1);

namespace Carapi\Platform;

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
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare(
            'INSERT INTO platform.projects (user_id, name, website_url) VALUES (:user_id, :name, :website) RETURNING id'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('name', $name);
        $stmt->bindValue('website', $website);
        $stmt->execute();
        $id = (int) $stmt->fetchColumn();
        $limits = $this->pdo->prepare(
            $this->limitsInsert()
        );
        $limits->bindValue('id', $id, PDO::PARAM_INT);
        $limits->execute();
        $this->pdo->commit();

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

        return $stmt->rowCount() > 0;
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

        return $stmt->rowCount() > 0;
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

        return $stmt->rowCount() > 0;
    }

    public function revoke(int $userId, int $projectId, int $keyId): bool
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            UPDATE platform.api_keys k
            SET revoked_at = now()
            FROM platform.projects p
            WHERE k.id = :key_id AND k.project_id = :project_id AND k.project_id = p.id AND p.user_id = :user_id AND k.revoked_at IS NULL
        SQL);
        $stmt->bindValue('key_id', $keyId, PDO::PARAM_INT);
        $stmt->bindValue('project_id', $projectId, PDO::PARAM_INT);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    private function limitsInsert(): string
    {
        $stmt = $this->pdo->query(<<<'SQL'
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'platform'
              AND table_name = 'project_limits'
              AND column_name = 'per_day'
        SQL);
        if ($stmt !== false && $stmt->fetchColumn() !== false) {
            return 'INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota, per_day) VALUES (:id, 60, 10000, 2000)';
        }

        return 'INSERT INTO platform.project_limits (project_id, per_minute, monthly_quota) VALUES (:id, 60, 10000)';
    }
}
