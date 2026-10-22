<?php

declare(strict_types=1);

namespace App\Billing;

use InvalidArgumentException;

/**
 * Commercial plans. Prices are in GEL tetri (1 ₾ = 100 tetri); there is no other currency.
 * A year is 12% below twelve monthly prices. The listed price includes VAT.
 */
final class Plans
{
    public const FREE = 'free';

    public const BASIC = 'basic';

    public const PREMIUM = 'premium';

    /** @var list<string> */
    public const PAID = ['basic', 'premium', 'basic-year', 'premium-year'];

    /** @return array<string, array{quota:int, unlimited:bool, tetri:int}> */
    public static function catalog(): array
    {
        return [
            self::FREE => ['quota' => 100, 'unlimited' => false, 'tetri' => 0],
            self::BASIC => ['quota' => 2000, 'unlimited' => false, 'tetri' => 15900],
            self::PREMIUM => ['quota' => 0, 'unlimited' => true, 'tetri' => 26700],
        ];
    }

    public static function code(string $plan, string $interval): string
    {
        self::assertPlan($plan);
        if ($plan === self::FREE) {
            return self::FREE;
        }
        if (! in_array($interval, ['month', 'year'], true)) {
            throw new InvalidArgumentException('interval');
        }

        return $interval === 'year' ? $plan.'-year' : $plan;
    }

    public static function tetri(string $plan, string $interval): int
    {
        self::assertPlan($plan);
        $monthly = self::catalog()[$plan]['tetri'];
        if ($plan === self::FREE || $interval === 'month') {
            return $monthly;
        }
        if ($interval !== 'year') {
            throw new InvalidArgumentException('interval');
        }

        return intdiv($monthly * 12 * 88, 100);
    }

    public static function quota(string $code): int
    {
        $plan = self::base($code);

        return self::catalog()[$plan]['quota'];
    }

    public static function unlimited(string $code): bool
    {
        return self::catalog()[self::base($code)]['unlimited'];
    }

    public static function base(string $code): string
    {
        $plan = str_ends_with($code, '-year') ? substr($code, 0, -5) : $code;
        self::assertPlan($plan);

        return $plan;
    }

    public static function formatTetri(int $tetri): string
    {
        $sign = $tetri < 0 ? '-' : '';
        $tetri = abs($tetri);

        return $sign.intdiv($tetri, 100).'.'.str_pad((string) ($tetri % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Customer-facing price in GEL. The bank is charged this amount. */
    public static function lari(int $tetri): string
    {
        $formatted = self::formatTetri($tetri);
        if (str_ends_with($formatted, '.00')) {
            $formatted = substr($formatted, 0, -3);
        }

        return $formatted.' ₾';
    }

    private static function assertPlan(string $plan): void
    {
        if (! array_key_exists($plan, self::catalog())) {
            throw new InvalidArgumentException('plan');
        }
    }
}
