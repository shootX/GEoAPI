<?php

declare(strict_types=1);

namespace Carapi\Platform;

use PDO;

final class Tokens
{
    public function __construct(private PDO $pdo) {}

    public function issue(int $userId, string $purpose, string $email): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $expires = gmdate('c', time() + ($purpose === 'reset_password' ? 1800 : 86400));
        $this->pdo->beginTransaction();
        $revoke = $this->pdo->prepare(
            'UPDATE platform.email_tokens SET revoked_at = now() WHERE user_id = :user_id AND purpose = :purpose AND used_at IS NULL AND revoked_at IS NULL'
        );
        $revoke->execute(['user_id' => $userId, 'purpose' => $purpose]);
        $insert = $this->pdo->prepare(<<<'SQL'
            INSERT INTO platform.email_tokens (user_id, purpose, token_hash, email_snapshot, expires_at)
            VALUES (:user_id, :purpose, :token_hash, :email_snapshot, :expires_at)
        SQL);
        $insert->execute([
            'user_id' => $userId,
            'purpose' => $purpose,
            'token_hash' => hash('sha256', $token),
            'email_snapshot' => $email,
            'expires_at' => $expires,
        ]);
        $this->pdo->commit();

        return $token;
    }

    /** @return array{ok:bool,reason:string} */
    public function verifyEmail(string $token): array
    {
        return $this->consume($token, 'verify_email', null);
    }

    /** @return array{ok:bool,reason:string} */
    public function resetPassword(string $token, string $passwordHash): array
    {
        return $this->consume($token, 'reset_password', $passwordHash);
    }

    public function limited(string $email, string $ip, string $purpose): ?int
    {
        $this->pdo->beginTransaction();
        $this->pdo->prepare('SELECT pg_advisory_xact_lock(hashtext(:key))')->execute(['key' => $purpose.'|'.$email.'|'.$ip]);
        $this->note($email, 'email', $purpose);
        $emailMinute = $this->count('email', $email, $purpose, '1 minute');
        if ($emailMinute > 1) {
            $this->pdo->commit();

            return 60;
        }
        if ($this->count('email', $email, $purpose, '1 hour') > 5) {
            $this->pdo->commit();

            return 3600;
        }
        if ($this->count('ip', $ip, $purpose, '1 hour') > 20) {
            $this->pdo->commit();

            return 3600;
        }
        $this->pdo->commit();

        return null;
    }

    public function guessesLeft(string $ip): bool
    {
        $this->note($ip, 'ip', 'token_guess');

        return $this->count('ip', $ip, 'token_guess', '1 hour') <= 10;
    }

    /** @return array{ok:bool,reason:string} */
    private function consume(string $token, string $purpose, ?string $passwordHash): array
    {
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT t.id, t.user_id, t.email_snapshot, t.expires_at, t.used_at, t.revoked_at, u.email, u.status
            FROM platform.email_tokens t
            JOIN platform.users u ON u.id = t.user_id
            WHERE t.token_hash = :hash AND t.purpose = :purpose
            FOR UPDATE
        SQL);
        $stmt->execute(['hash' => hash('sha256', $token), 'purpose' => $purpose]);
        $row = $stmt->fetch();
        if ($row === false || $row['used_at'] !== null || $row['revoked_at'] !== null || strtotime((string) $row['expires_at']) <= time()) {
            $this->pdo->commit();

            return ['ok' => false, 'reason' => 'invalid'];
        }
        if ((string) $row['email_snapshot'] !== (string) $row['email']) {
            $this->pdo->commit();

            return ['ok' => false, 'reason' => 'invalid'];
        }
        $this->pdo->prepare('UPDATE platform.email_tokens SET used_at = now() WHERE id = :id')->execute(['id' => $row['id']]);
        if ($purpose === 'verify_email') {
            $this->pdo->prepare('UPDATE platform.users SET email_verified_at = COALESCE(email_verified_at, now()), updated_at = now() WHERE id = :id')
                ->execute(['id' => $row['user_id']]);
        } else {
            $this->pdo->prepare('UPDATE platform.users SET password_hash = :hash, auth_version = auth_version + 1, updated_at = now() WHERE id = :id')
                ->execute(['hash' => $passwordHash, 'id' => $row['user_id']]);
        }
        $this->pdo->prepare(
            'UPDATE platform.email_tokens SET revoked_at = now() WHERE user_id = :user_id AND purpose = :purpose AND used_at IS NULL AND revoked_at IS NULL'
        )->execute(['user_id' => $row['user_id'], 'purpose' => $purpose]);
        $this->pdo->commit();

        return ['ok' => true, 'reason' => 'ok', 'user_id' => (int) $row['user_id']];
    }

    private function note(string $subject, string $kind, string $purpose): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO platform.mail_limits (subject, kind, purpose) VALUES (:subject, :kind, :purpose)'
        );
        $stmt->execute(['subject' => $subject, 'kind' => $kind, 'purpose' => $purpose]);
    }

    private function count(string $kind, string $subject, string $purpose, string $window): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT count(*) FROM platform.mail_limits WHERE kind = :kind AND subject = :subject AND purpose = :purpose AND created_at > now() - :window::interval'
        );
        $stmt->execute(['kind' => $kind, 'subject' => $subject, 'purpose' => $purpose, 'window' => $window]);

        return (int) $stmt->fetchColumn();
    }
}
