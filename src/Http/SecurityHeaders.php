<?php

declare(strict_types=1);

namespace Carapi\Http;

final class SecurityHeaders
{
    private static bool $sent = false;

    public static function send(): void
    {
        if (self::$sent || headers_sent()) {
            return;
        }
        self::$sent = true;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; font-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; object-src 'none'");
    }
}
