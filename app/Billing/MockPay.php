<?php

declare(strict_types=1);

namespace App\Billing;

use App\Access\AdminAccess;
use App\Models\User;

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

    /**
     * Outside local and test runs a mock "payment" would hand out paid plans for free, so only admins
     * and operators may use it there (QA GEO-QA-005). A mistyped APP_ENV must not open it to everyone.
     */
    public static function allowedFor(?User $user): bool
    {
        if (! self::enabled()) {
            return false;
        }

        return app()->environment(['local', 'testing']) || AdminAccess::allows($user);
    }

    /** Mock orders carry bog_order_id "mock:<public_id>" (no schema change; a real BOG callback can never match it). */
    public static function flag(string $publicId): string
    {
        return 'mock:'.$publicId;
    }

    public static function isMock(object $order): bool
    {
        return str_starts_with((string) ($order->bog_order_id ?? ''), 'mock:');
    }
}
