<?php

declare(strict_types=1);

namespace Carapi\Platform;

use PDO;

final class WidgetRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public static function newKey(): string
    {
        return 'carapi_pk_' . bin2hex(random_bytes(16));
    }

    /** @return array<string, mixed>|null */
    public function findOwned(int $userId, int $projectId): ?array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT w.project_id, w.enabled, w.public_key, w.allowed_origins, p.status AS project_status,
                   u.status AS user_status, u.email_verified_at
            FROM platform.widgets w
            JOIN platform.projects p ON p.id = w.project_id
            JOIN platform.users u ON u.id = p.user_id
            WHERE w.project_id = :project_id AND p.user_id = :user_id
        SQL);
        $stmt->execute(['project_id' => $projectId, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->cast($row);
    }

    /** @return array<string, mixed>|null */
    public function findByKey(string $publicKey): ?array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT w.project_id, w.enabled, w.public_key, w.allowed_origins, p.status AS project_status,
                   u.status AS user_status, u.email_verified_at
            FROM platform.widgets w
            JOIN platform.projects p ON p.id = w.project_id
            JOIN platform.users u ON u.id = p.user_id
            WHERE w.public_key = :public_key
        SQL);
        $stmt->execute(['public_key' => $publicKey]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->cast($row);
    }

    public function ensure(int $userId, int $projectId): ?array
    {
        if ($this->findOwned($userId, $projectId) === null && !$this->projectOwned($userId, $projectId)) {
            return null;
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO platform.widgets (project_id, public_key)
            VALUES (:project_id, :public_key)
            ON CONFLICT (project_id) DO NOTHING
        SQL);
        $stmt->execute(['project_id' => $projectId, 'public_key' => self::newKey()]);

        return $this->findOwned($userId, $projectId);
    }

    public function setEnabled(int $userId, int $projectId, bool $enabled): bool
    {
        $widget = $this->ensure($userId, $projectId);
        if ($widget === null) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE platform.widgets SET enabled = :enabled, updated_at = now() WHERE project_id = :project_id'
        );
        $stmt->bindValue('enabled', $enabled, PDO::PARAM_BOOL);
        $stmt->bindValue('project_id', $projectId, PDO::PARAM_INT);
        $stmt->execute();

        return true;
    }

    public function rotate(int $userId, int $projectId): ?string
    {
        if ($this->ensure($userId, $projectId) === null) {
            return null;
        }
        $key = self::newKey();
        $stmt = $this->pdo->prepare(
            'UPDATE platform.widgets SET public_key = :public_key, updated_at = now() WHERE project_id = :project_id'
        );
        $stmt->execute(['public_key' => $key, 'project_id' => $projectId]);

        return $key;
    }

    /** @param list<string> $origins */
    public function setOrigins(int $userId, int $projectId, array $origins): bool
    {
        if ($this->ensure($userId, $projectId) === null) {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'UPDATE platform.widgets SET allowed_origins = :origins::jsonb, updated_at = now() WHERE project_id = :project_id'
        );
        $stmt->execute(['origins' => json_encode(array_values($origins), JSON_UNESCAPED_SLASHES), 'project_id' => $projectId]);

        return true;
    }

    private function projectOwned(int $userId, int $projectId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM platform.projects WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $projectId, 'user_id' => $userId]);

        return $stmt->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function cast(array $row): array
    {
        $origins = $row['allowed_origins'];
        if (is_string($origins)) {
            $decoded = json_decode($origins, true);
            $origins = is_array($decoded) ? $decoded : [];
        }
        $row['allowed_origins'] = array_values(array_filter(
            is_array($origins) ? $origins : [],
            static fn ($item): bool => is_string($item)
        ));
        $row['enabled'] = $row['enabled'] === true || $row['enabled'] === 1 || $row['enabled'] === '1' || $row['enabled'] === 't';

        return $row;
    }
}
