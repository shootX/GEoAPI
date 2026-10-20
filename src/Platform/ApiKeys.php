<?php

declare(strict_types=1);

namespace Carapi\Platform;

use PDO;

final class ApiKeys
{
    public static function issue(): array
    {
        $selector = bin2hex(random_bytes(16));
        $secret = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        return [
            'selector' => $selector,
            'secret' => $secret,
            'token' => 'carapi_sk_' . $selector . '_' . $secret,
            'hash' => hash('sha256', $secret),
        ];
    }

    /** @return array{selector:string,secret:string}|null */
    public static function parse(string $token): ?array
    {
        if (preg_match('/^carapi_sk_([0-9a-f]{32})_([A-Za-z0-9_-]{43})$/', $token) !== 1) {
            return null;
        }

        return [
            'selector' => substr($token, 10, 32),
            'secret' => substr($token, 43),
        ];
    }

    public static function matches(string $secret, string $hash): bool
    {
        return hash_equals($hash, hash('sha256', $secret));
    }

    /** @return array{id:int,project_id:int,code:string}|null code is ok|forbidden */
    public static function lookup(PDO $pdo, string $selector, string $secret): ?array
    {
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT k.id, k.project_id, k.secret_hash, k.scope, k.expires_at, k.revoked_at,
                   p.status AS project_status, u.status AS user_status, u.email_verified_at
            FROM platform.api_keys k
            JOIN platform.projects p ON p.id = k.project_id
            JOIN platform.users u ON u.id = p.user_id
            WHERE k.key_selector = :selector
            LIMIT 1
        SQL);
        $stmt->execute(['selector' => $selector]);
        $row = $stmt->fetch();
        if ($row === false || !self::matches($secret, (string) $row['secret_hash'])) {
            return null;
        }
        if ($row['revoked_at'] !== null || ($row['expires_at'] !== null && strtotime((string) $row['expires_at']) <= time())) {
            return null;
        }
        $allowed = (string) $row['scope'] === 'catalog:read'
            && (string) $row['project_status'] === 'active'
            && (string) $row['user_status'] === 'active'
            && $row['email_verified_at'] !== null;

        return ['id' => (int) $row['id'], 'project_id' => (int) $row['project_id'], 'code' => $allowed ? 'ok' : 'forbidden'];
    }

    public static function touch(PDO $pdo, int $id): void
    {
        $stmt = $pdo->prepare(
            "UPDATE platform.api_keys SET last_used_at = now() WHERE id = :id AND (last_used_at IS NULL OR last_used_at < now() - interval '1 minute')"
        );
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
