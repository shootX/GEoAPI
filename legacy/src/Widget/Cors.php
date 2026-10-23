<?php

declare(strict_types=1);

namespace Geoapi\Widget;

final class Cors
{
    public static ?string $origin = null;

    public static function apply(): void
    {
        header('Vary: Origin');
        if (self::$origin === null) {
            return;
        }
        header('Access-Control-Allow-Origin: ' . self::$origin);
        header('Access-Control-Expose-Headers: Retry-After, X-Quota-Limit, X-Quota-Remaining, X-Quota-Reset');
    }

    public static function preflight(string $origin): void
    {
        self::$origin = $origin;
        self::apply();
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 600');
        header('Cache-Control: no-store');
        http_response_code(204);
    }
}
