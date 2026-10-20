<?php

declare(strict_types=1);

namespace Carapi\Catalog;

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

        $host = self::env('CARAPI_CATALOG_HOST');
        $database = self::env('CARAPI_CATALOG_DATABASE');
        $user = self::env('CARAPI_CATALOG_USER');
        $password = getenv('CARAPI_CATALOG_PASSWORD');
        $password = is_string($password) ? $password : '';
        $port = self::intEnv('CARAPI_CATALOG_PORT', 5432, 1, 65535);
        $connectTimeout = self::intEnv('CARAPI_CATALOG_CONNECT_TIMEOUT', 3, 1, 10);
        $statementTimeout = self::intEnv('CARAPI_CATALOG_STATEMENT_TIMEOUT_MS', 3000, 100, 10000);

        if ($host === '' || $database === '' || $user === '') {
            throw new UnavailableException();
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
            $pdo->exec('SET default_transaction_read_only = on');
            $pdo->exec('SET statement_timeout = ' . $statementTimeout);
            $pdo->exec("SET lock_timeout = '1s'");
        } catch (PDOException) {
            error_log('[catalog] database unavailable');
            throw new UnavailableException();
        }

        self::$pdo = $pdo;

        return $pdo;
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }

    private static function env(string $name): string
    {
        $value = getenv($name);

        return is_string($value) ? trim($value) : '';
    }

    private static function intEnv(string $name, int $default, int $min, int $max): int
    {
        $raw = getenv($name);
        if (!is_string($raw) || !preg_match('/^[0-9]+$/', $raw)) {
            return $default;
        }
        $value = (int) $raw;

        return max($min, min($max, $value));
    }
}
