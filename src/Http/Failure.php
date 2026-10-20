<?php

declare(strict_types=1);

namespace Carapi\Http;

use PDOException;
use Throwable;

/**
 * Production responses never include exception class, path, SQL, or trace.
 */
final class Failure
{
    public static function install(): void
    {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        set_exception_handler([self::class, 'render']);
    }

    public static function render(Throwable $error): void
    {
        error_log('[carapi] ' . $error::class);
        if (headers_sent()) {
            return;
        }
        $status = self::status($error);
        $json = self::wantsJson();
        http_response_code($status);
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo self::jsonBody($status);

            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo self::htmlBody($status);
    }

    public static function status(Throwable $error): int
    {
        for ($current = $error; $current instanceof Throwable; $current = $current->getPrevious()) {
            if ($current instanceof PDOException && self::uniqueViolation($current)) {
                return 409;
            }
        }

        return 500;
    }

    public static function uniqueViolation(PDOException $error): bool
    {
        if ((string) $error->getCode() === '23505') {
            return true;
        }

        return str_contains($error->getMessage(), 'SQLSTATE[23505]');
    }

    public static function jsonBody(int $status): string
    {
        $message = $status === 409
            ? 'That value is already in use.'
            : 'The request could not be completed.';

        return (string) json_encode(['message' => $message], JSON_UNESCAPED_UNICODE);
    }

    public static function htmlBody(int $status): string
    {
        $text = $status === 409
            ? 'ეს მნიშვნელობა უკვე გამოყენებულია.'
            : 'მოთხოვნა ვერ შესრულდა.';

        return '<!doctype html><html lang="ka"><head><meta charset="utf-8"><title>შეცდომა</title></head><body><p>'
            . $text . '</p></body></html>';
    }

    private static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        return is_string($accept) && str_contains($accept, 'application/json');
    }
}
