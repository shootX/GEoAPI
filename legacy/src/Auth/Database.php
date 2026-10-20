<?php

declare(strict_types=1);

namespace Carapi\Auth;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $path = carapi_env('CARAPI_DB') ?: dirname(__DIR__, 2) . '/data/carapi.sqlite';
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create data directory');
        }

        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        self::migrate($pdo);
        self::$pdo = $pdo;

        return $pdo;
    }

    private static function migrate(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL CHECK (type IN ('individual', 'company')),
                email TEXT NOT NULL COLLATE NOCASE UNIQUE,
                password_hash TEXT NOT NULL,
                phone TEXT NOT NULL,
                first_name TEXT,
                last_name TEXT,
                personal_number TEXT,
                company_name TEXT,
                identification_code TEXT,
                representative_name TEXT,
                created_at TEXT NOT NULL
            )
        SQL);
        $pdo->exec(
            "CREATE UNIQUE INDEX IF NOT EXISTS users_personal_number
             ON users (personal_number)
             WHERE personal_number IS NOT NULL AND personal_number != ''"
        );
        $pdo->exec(
            "CREATE UNIQUE INDEX IF NOT EXISTS users_identification_code
             ON users (identification_code)
             WHERE identification_code IS NOT NULL AND identification_code != ''"
        );
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS lookups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                make TEXT NOT NULL,
                model TEXT NOT NULL,
                year INTEGER,
                found INTEGER NOT NULL,
                title TEXT,
                period TEXT,
                generation TEXT,
                attribution TEXT,
                created_at TEXT NOT NULL
            )
        SQL);
        $pdo->exec('CREATE INDEX IF NOT EXISTS lookups_user_id ON lookups (user_id, id DESC)');
    }
}
