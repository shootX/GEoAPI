<?php

declare(strict_types=1);

namespace App\Billing\V2;

final class Flag
{
    public static function on(): bool
    {
        if (function_exists('app')) {
            try {
                if (app()->bound('config')) {
                    return (bool) config('billing.v2');
                }
            } catch (\Throwable) {
            }
        }
        $raw = function_exists('carapi_env') ? carapi_env('BILLING_V2') : getenv('BILLING_V2');

        return is_string($raw) && in_array(strtolower($raw), ['1', 'true', 'on', 'yes'], true);
    }

    public static function businessRpm(): bool
    {
        if (function_exists('app')) {
            try {
                if (app()->bound('config')) {
                    return (bool) config('billing.v2_business_rpm');
                }
            } catch (\Throwable) {
            }
        }
        $raw = function_exists('carapi_env') ? carapi_env('BILLING_V2_BUSINESS_RPM') : getenv('BILLING_V2_BUSINESS_RPM');

        return is_string($raw) && in_array(strtolower($raw), ['1', 'true', 'on', 'yes'], true);
    }

    public static function is(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }
}
