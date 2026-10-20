<?php

declare(strict_types=1);

namespace Carapi\Auth;

final class SessionAuth
{
    private const ATTEMPT_LIMIT = 8;
    private const ATTEMPT_WINDOW = 900;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        session_name('geoapi');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
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

    /** @param array<string, mixed> $row */
    public static function login(array $row): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $row['id'];
        $_SESSION['login_attempts'] = [];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
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

    public static function id(): ?int
    {
        self::start();
        $id = $_SESSION['user_id'] ?? null;

        return is_int($id) ? $id : (is_numeric($id) ? (int) $id : null);
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        $id = self::id();
        if ($id === null) {
            return null;
        }

        $row = (new UserRepository(Database::pdo()))->findById($id);
        if ($row === null) {
            unset($_SESSION['user_id']);

            return null;
        }

        return self::publicUser($row);
    }

    public static function tooManyAttempts(): bool
    {
        self::start();
        $now = time();
        $attempts = array_values(array_filter(
            $_SESSION['login_attempts'] ?? [],
            static fn ($ts): bool => is_int($ts) && ($now - $ts) < self::ATTEMPT_WINDOW
        ));
        $_SESSION['login_attempts'] = $attempts;

        return count($attempts) >= self::ATTEMPT_LIMIT;
    }

    public static function recordFailure(): void
    {
        self::start();
        $_SESSION['login_attempts'][] = time();
    }

    public static function setFlash(string $message): void
    {
        self::start();
        $_SESSION['flash'] = $message;
    }

    public static function pullFlash(): string
    {
        self::start();
        $message = $_SESSION['flash'] ?? '';
        unset($_SESSION['flash']);

        return is_string($message) ? $message : '';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function publicUser(array $row): array
    {
        $type = (string) $row['type'];
        $label = $type === 'company'
            ? (string) $row['company_name']
            : trim((string) $row['first_name'] . ' ' . (string) $row['last_name']);

        return [
            'id' => (int) $row['id'],
            'type' => $type,
            'type_label' => $type === 'company' ? 'იურიდიული პირი' : 'ფიზიკური პირი',
            'email' => (string) $row['email'],
            'phone' => (string) $row['phone'],
            'label' => $label,
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'personal_number' => $row['personal_number'],
            'company_name' => $row['company_name'],
            'identification_code' => $row['identification_code'],
            'representative_name' => $row['representative_name'],
            'created_at' => (string) $row['created_at'],
        ];
    }
}
