<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Session cookie Secure flag. An empty setting follows APP_ENV and does not override it.
 */
final class SessionSecure
{
    public static function resolve(mixed $configured, mixed $environment): bool
    {
        if (is_string($configured)) {
            $configured = trim($configured);
        }
        if ($configured === null || $configured === '') {
            return $environment === 'production';
        }

        return filter_var($configured, FILTER_VALIDATE_BOOLEAN);
    }
}
