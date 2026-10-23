<?php

declare(strict_types=1);

namespace Geoapi\Platform;

use PDO;
use PDOException;

final class UserRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public static function email(string $value): string
    {
        return mb_strtolower(trim($value), 'UTF-8');
    }

    /**
     * @param array{first_name:string,last_name:string,email:string,password_hash:string,terms_version?:string} $user
     * @return array{ok:bool,id?:int,duplicate?:bool}
     */
    public function insert(array $user): array
    {
        try {
            $stmt = $this->pdo->prepare(<<<'SQL'
                INSERT INTO platform.users (first_name, last_name, email, password_hash, terms_version, terms_accepted_at)
                VALUES (:first_name, :last_name, :email, :password_hash, :terms_version, :terms_accepted_at)
                RETURNING id
            SQL);
            $version = $user['terms_version'] ?? null;
            $stmt->execute([
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'password_hash' => $user['password_hash'],
                'terms_version' => $version,
                'terms_accepted_at' => $version === null ? null : gmdate('c'),
            ]);
        } catch (PDOException $e) {
            if ($e->getCode() === '23505') {
                return ['ok' => false, 'duplicate' => true];
            }
            error_log('[platform] query failed: SQLSTATE '.$e->getCode());
            throw new UnavailableException();
        }

        return ['ok' => true, 'id' => (int) $stmt->fetchColumn()];
    }

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, first_name, last_name, email, password_hash, status, auth_version FROM platform.users WHERE lower(email) = :email LIMIT 1'
        );
        $stmt->execute(['email' => self::email($email)]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findActive(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, first_name, last_name, email, status, email_verified_at, auth_version FROM platform.users WHERE id = :id AND status = 'active' LIMIT 1"
        );
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    public function role(int $id): ?string
    {
        $stmt = $this->pdo->prepare('SELECT role FROM platform.users WHERE id = :id LIMIT 1');
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $role = $stmt->fetchColumn();

        return is_string($role) ? $role : null;
    }

    public function touchLogin(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE platform.users SET last_login_at = now(), updated_at = now() WHERE id = :id'
        );
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function recordFailure(string $email, string $ip): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO platform.login_attempts (email, ip) VALUES (:email, :ip)'
        );
        $stmt->execute(['email' => $email === '' ? null : $email, 'ip' => $ip]);
        $this->prune();
    }

    public function tooManyAttempts(string $email, string $ip): bool
    {
        $limit = self::intEnv('GEOAPI_PLATFORM_ATTEMPT_LIMIT', 8);
        $window = self::intEnv('GEOAPI_PLATFORM_ATTEMPT_WINDOW', 900);
        $ipLimit = self::intEnv('GEOAPI_PLATFORM_IP_ATTEMPT_LIMIT', 30);
        $emailCount = 0;
        if ($email !== '') {
            $stmt = $this->pdo->prepare(
                "SELECT count(*) FROM platform.login_attempts WHERE email = :email AND created_at > now() - (:window || ' seconds')::interval"
            );
            $stmt->execute(['email' => $email, 'window' => (string) $window]);
            $emailCount = (int) $stmt->fetchColumn();
        }
        $stmt = $this->pdo->prepare(
            "SELECT count(*) FROM platform.login_attempts WHERE ip = :ip AND created_at > now() - (:window || ' seconds')::interval"
        );
        $stmt->execute(['ip' => $ip, 'window' => (string) $window]);
        $ipCount = (int) $stmt->fetchColumn();

        return $emailCount >= $limit || $ipCount >= $ipLimit;
    }

    private function prune(): void
    {
        $this->pdo->exec("DELETE FROM platform.login_attempts WHERE created_at < now() - interval '1 day'");
    }

    private static function intEnv(string $name, int $default): int
    {
        $raw = geoapi_env($name);

        return is_string($raw) && preg_match('/^[0-9]+$/', $raw) ? (int) $raw : $default;
    }
}
