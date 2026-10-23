<?php

declare(strict_types=1);

namespace GeoAPI;

use Illuminate\Support\Facades\Vite;

/**
 * Nonce for legacy inline <script>/<style> tags. Laravel's SecurityHeaders creates it per
 * request (Vite::useCspNonce); outside a Laravel request there is no nonce and no policy.
 */
final class Csp
{
    public static function attr(): string
    {
        if (! class_exists(Vite::class) || Vite::getFacadeApplication() === null) {
            return '';
        }
        try {
            $nonce = Vite::cspNonce();
        } catch (\Throwable) {
            return '';
        }

        return $nonce === null ? '' : ' nonce="'.htmlspecialchars($nonce, ENT_QUOTES).'"';
    }
}
