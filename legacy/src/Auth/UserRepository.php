<?php

declare(strict_types=1);

namespace Geoapi\Auth;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array{
     *   type:string,email:string,password_hash:string,phone:string,
     *   first_name:?string,last_name:?string,personal_number:?string,
     *   company_name:?string,identification_code:?string,representative_name:?string
     * } $user
     */
    public function insert(array $user): int
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            INSERT INTO users (
                type, email, password_hash, phone,
                first_name, last_name, personal_number,
                company_name, identification_code, representative_name,
                created_at
            ) VALUES (
                :type, :email, :password_hash, :phone,
                :first_name, :last_name, :personal_number,
                :company_name, :identification_code, :representative_name,
                :created_at
            )
        SQL);
        $stmt->execute([
            'type' => $user['type'],
            'email' => $user['email'],
            'password_hash' => $user['password_hash'],
            'phone' => $user['phone'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'personal_number' => $user['personal_number'],
            'company_name' => $user['company_name'],
            'identification_code' => $user['identification_code'],
            'representative_name' => $user['representative_name'],
            'created_at' => gmdate('c'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function emailTaken(string $email): bool
    {
        return $this->findByEmail($email) !== null;
    }

    public function personalNumberTaken(string $number): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM users WHERE personal_number = :number LIMIT 1'
        );
        $stmt->execute(['number' => $number]);

        return $stmt->fetchColumn() !== false;
    }

    public function updatePhone(int $id, string $phone): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET phone = :phone WHERE id = :id');
        $stmt->execute(['phone' => $phone, 'id' => $id]);
    }

    public function updatePassword(int $id, string $hash): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => $hash, 'id' => $id]);
    }

    public function identificationCodeTaken(string $code): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM users WHERE identification_code = :code LIMIT 1'
        );
        $stmt->execute(['code' => $code]);

        return $stmt->fetchColumn() !== false;
    }
}
