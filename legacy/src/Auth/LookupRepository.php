<?php

declare(strict_types=1);

namespace Carapi\Auth;

use PDO;

final class LookupRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array{
     *   user_id:int,make:string,model:string,year:?int,found:bool,
     *   title:string,period:string,generation:string,attribution:string
     * } $lookup
     */
    public function add(array $lookup): int
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO lookups (
                user_id, make, model, year, found, title, period, generation, attribution, created_at
            ) VALUES (
                :user_id, :make, :model, :year, :found, :title, :period, :generation, :attribution, :created_at
            )
        SQL);
        $stmt->execute([
            'user_id' => $lookup['user_id'],
            'make' => $lookup['make'],
            'model' => $lookup['model'],
            'year' => $lookup['year'],
            'found' => $lookup['found'] ? 1 : 0,
            'title' => $lookup['title'],
            'period' => $lookup['period'],
            'generation' => $lookup['generation'],
            'attribution' => $lookup['attribution'],
            'created_at' => gmdate('c'),
        ]);
        $id = (int) $this->pdo->lastInsertId();
        $this->trim($lookup['user_id']);

        return $id;
    }

    /** @return array<string, mixed>|null */
    public function find(int $userId, int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM lookups WHERE id = :id AND user_id = :user_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $userId, int $limit): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM lookups WHERE user_id = :user_id ORDER BY id DESC LIMIT :limit'
        );
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    private function trim(int $userId): void
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            DELETE FROM lookups
            WHERE user_id = :user_id
              AND id NOT IN (
                SELECT id FROM lookups WHERE user_id = :user_id ORDER BY id DESC LIMIT 50
              )
        SQL);
        $stmt->execute(['user_id' => $userId]);
    }
}
