<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * Local TEST payment mode (owner-approved 2026-10-22) for when no BOG credentials exist.
 *
 * It is impossible to enable while real BOG credentials are configured or BOG_ENV=production:
 * then every checkout goes to the bank (or fails closed) exactly as before.
 */
final class MockPay
{
    public const PROVIDER = 'mock';

    public static function enabled(): bool
    {
        if (BogClient::configured()) {
            return false;
        }
        if (config('services.bog.env') === 'production' || BogClient::environment() === 'production') {
            return false;
        }

        return config('services.bog.mock', true) !== false;
    }
}
