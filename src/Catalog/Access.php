<?php

declare(strict_types=1);

namespace Carapi\Catalog;

use Carapi\Platform\ApiKeys;
use Carapi\Platform\Connection as PlatformConnection;
use Carapi\Platform\UnavailableException as PlatformUnavailable;
use PDOException;

final class Access
{
    /** @return array{key_id:int,project_id:int} */
    public static function allow(): array
    {
        if (!self::platformReady()) {
            throw new UnavailableException();
        }
        $token = self::bearer();
        $parsed = $token === null ? null : ApiKeys::parse($token);
        if ($parsed === null) {
            throw new DeniedException(401);
        }
        try {
            $found = ApiKeys::lookup(PlatformConnection::pdo(), $parsed['selector'], $parsed['secret']);
        } catch (PlatformUnavailable | PDOException) {
            error_log('[catalog] platform unavailable');
            throw new UnavailableException();
        }
        if ($found === null) {
            throw new DeniedException(401);
        }
        if ($found['code'] !== 'ok') {
            throw new DeniedException(403);
        }

        return ['key_id' => $found['id'], 'project_id' => $found['project_id']];
    }

    public static function touch(int $id): void
    {
        try {
            ApiKeys::touch(PlatformConnection::pdo(), $id);
        } catch (PlatformUnavailable | PDOException) {
            error_log('[catalog] platform unavailable');
            throw new UnavailableException();
        }
    }

    private static function platformReady(): bool
    {
        foreach (['CARAPI_PLATFORM_HOST', 'CARAPI_PLATFORM_DATABASE', 'CARAPI_PLATFORM_USER'] as $name) {
            $value = getenv($name);
            if (!is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    private static function bearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!is_string($header) || preg_match('/^Bearer\s+(\S+)\s*$/', $header, $match) !== 1) {
            return null;
        }

        return $match[1];
    }
}
