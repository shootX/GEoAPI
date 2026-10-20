<?php

declare(strict_types=1);

namespace Carapi\Platform;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $path = getenv('CARAPI_PLATFORM_SESSION_PATH');
        $path = is_string($path) && $path !== '' ? $path : dirname(__DIR__, 2) . '/data/sessions';
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new UnavailableException();
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_save_path($path);
        session_name('carapi_platform');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        self::expire();
    }

    public static function csrf(): string
    {
        self::start();
        if (empty($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    public static function checkCsrf(?string $token): bool
    {
        self::start();
        $known = $_SESSION['csrf'] ?? '';

        return is_string($known) && $known !== '' && is_string($token) && hash_equals($known, $token);
    }

    public static function login(int $id, int $authVersion): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION = [
            'user_id' => $id,
            'auth_version' => $authVersion,
            'issued_at' => time(),
            'last_seen' => time(),
            'csrf' => bin2hex(random_bytes(32)),
        ];
    }

    public static function authVersion(): ?int
    {
        self::start();
        $version = $_SESSION['auth_version'] ?? null;

        return is_numeric($version) ? (int) $version : null;
    }

    public static function id(): ?int
    {
        self::start();
        $id = $_SESSION['user_id'] ?? null;

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    private static function expire(): void
    {
        $id = $_SESSION['user_id'] ?? null;
        if (!is_numeric($id)) {
            return;
        }
        $now = time();
        $issued = (int) ($_SESSION['issued_at'] ?? 0);
        $seen = (int) ($_SESSION['last_seen'] ?? 0);
        $idle = self::limit('CARAPI_PLATFORM_IDLE_SECONDS', 1800);
        $absolute = self::limit('CARAPI_PLATFORM_ABSOLUTE_SECONDS', 43200);
        if ($issued < 1 || $seen < 1 || ($now - $seen) > $idle || ($now - $issued) > $absolute) {
            $_SESSION = [];
            session_regenerate_id(true);

            return;
        }
        $_SESSION['last_seen'] = $now;
    }

    private static function limit(string $name, int $default): int
    {
        $raw = getenv($name);

        return is_string($raw) && preg_match('/^[0-9]+$/', $raw) ? (int) $raw : $default;
    }
}
