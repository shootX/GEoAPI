<?php

declare(strict_types=1);

namespace Geoapi\Catalog;

use App\Access\SanctumCatalogAccess;
use Geoapi\Platform\ApiKeys;
use Geoapi\Platform\Connection as PlatformConnection;
use Geoapi\Platform\UnavailableException as PlatformUnavailable;
use PDOException;

final class Access
{
    /** @return array{key_id:int,project_id:int} */
    public static function allow(): array
    {
        $testProject = self::testProject();
        if ($testProject !== null) {
            return ['key_id' => 0, 'project_id' => $testProject];
        }
        $token = self::bearer();
        $parsed = $token === null ? null : ApiKeys::parse($token);
        if ($parsed === null) {
            if ($token !== null && class_exists(SanctumCatalogAccess::class)) {
                $sanctum = SanctumCatalogAccess::allow($token);
                if ($sanctum !== null) {
                    return $sanctum;
                }
            }
            throw new DeniedException(401);
        }
        if (! PlatformConnection::active() && ! self::platformReady()) {
            throw new UnavailableException;
        }
        try {
            $found = ApiKeys::lookup(PlatformConnection::pdo(), $parsed['selector'], $parsed['secret']);
        } catch (PlatformUnavailable|PDOException) {
            error_log('[catalog] platform unavailable');
            throw new UnavailableException;
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
        } catch (PlatformUnavailable|PDOException) {
            error_log('[catalog] platform unavailable');
            throw new UnavailableException;
        }
    }

    private static function testProject(): ?int
    {
        try {
            if (! function_exists('app') || ! app()->environment('testing')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }
        $raw = geoapi_env('GEOAPI_CATALOG_TEST_PROJECT');
        if (! is_string($raw) || preg_match('/^[1-9][0-9]*$/', $raw) !== 1) {
            return null;
        }

        return (int) $raw;
    }

    private static function platformReady(): bool
    {
        foreach (['GEOAPI_PLATFORM_HOST', 'GEOAPI_PLATFORM_DATABASE', 'GEOAPI_PLATFORM_USER'] as $name) {
            $value = geoapi_env($name);
            if (! is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    private static function bearer(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (! is_string($header) || preg_match('/^Bearer\s+(\S+)\s*$/', $header, $match) !== 1) {
            return null;
        }

        return $match[1];
    }
}
