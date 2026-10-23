<?php

declare(strict_types=1);

namespace Geoapi\Platform;

use PDO;
use PDOException;

final class Connection
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = self::env('GEOAPI_PLATFORM_HOST');
        $database = self::env('GEOAPI_PLATFORM_DATABASE');
        $user = self::env('GEOAPI_PLATFORM_USER');
        $password = geoapi_env('GEOAPI_PLATFORM_PASSWORD');
        $password = is_string($password) ? $password : '';
        $port = self::intEnv('GEOAPI_PLATFORM_PORT', 5432, 1, 65535);
        $connectTimeout = self::intEnv('GEOAPI_PLATFORM_CONNECT_TIMEOUT', 3, 1, 10);
        $statementTimeout = self::intEnv('GEOAPI_PLATFORM_STATEMENT_TIMEOUT_MS', 3000, 100, 10000);

        if ($host === '' || $database === '' || $user === '') {
            throw new UnavailableException;
        }

        $dsn = sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;connect_timeout=%d',
            $host,
            $port,
            $database,
            $connectTimeout
        );

        try {
            $pdo = new PDO($dsn, $user, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('SET statement_timeout = '.$statementTimeout);
            $pdo->exec("SET lock_timeout = '1s'");
        } catch (PDOException) {
            error_log('[platform] database unavailable');
            throw new UnavailableException;
        }

        self::$pdo = $pdo;

        return $pdo;
    }

    public static function active(): bool
    {
        return self::$pdo instanceof PDO;
    }

    public static function use(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    private static function env(string $name): string
    {
        $value = geoapi_env($name);

        return is_string($value) ? trim($value) : '';
    }

    private static function intEnv(string $name, int $default, int $min, int $max): int
    {
        $raw = geoapi_env($name);
        if (! is_string($raw) || ! preg_match('/^[0-9]+$/', $raw)) {
            return $default;
        }

        return max($min, min($max, (int) $raw));
    }
}
