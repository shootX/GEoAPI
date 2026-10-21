<?php

declare(strict_types=1);

namespace App\Billing;

use InvalidArgumentException;

/**
 * Commercial plans. USD cents are the list price. GEL is applied later with the NBG rate.
 * A year is 12% below twelve monthly prices. The listed price includes VAT.
 */
final class Plans
{
    public const FREE = 'free';

    public const BASIC = 'basic';

    public const PREMIUM = 'premium';

    /** @var list<string> */
    public const PAID = ['basic', 'premium', 'basic-year', 'premium-year'];

    /** @return array<string, array{quota:int, unlimited:bool, usd_cents:int}> */
    public static function catalog(): array
    {
        return [
            self::FREE => ['quota' => 100, 'unlimited' => false, 'usd_cents' => 0],
            self::BASIC => ['quota' => 2000, 'unlimited' => false, 'usd_cents' => 5900],
            self::PREMIUM => ['quota' => 0, 'unlimited' => true, 'usd_cents' => 9900],
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

    public static function usdCents(string $plan, string $interval): int
    {
        self::assertPlan($plan);
        $monthly = self::catalog()[$plan]['usd_cents'];
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

    /**
     * GEL tetri for a USD cent amount. rate is GEL per `quantity` USD.
     */
    public static function gelTetri(int $usdCents, string $rate, int $quantity): int
    {
        if ($usdCents < 0 || $quantity < 1 || ! preg_match('/^\d+(\.\d+)?$/', $rate)) {
            throw new InvalidArgumentException('rate');
        }
        $product = bcmul((string) $usdCents, $rate, 8);
        $divided = bcdiv($product, (string) $quantity, 8);
        $negative = str_starts_with($divided, '-');
        $absolute = ltrim($divided, '-');
        [$whole, $fraction] = array_pad(explode('.', $absolute, 2), 2, '0');
        $tetri = (int) $whole;
        if (isset($fraction[0]) && $fraction[0] >= '5') {
            $tetri++;
        }

        return $negative ? -$tetri : $tetri;
    }

    public static function formatTetri(int $tetri): string
    {
        $sign = $tetri < 0 ? '-' : '';
        $tetri = abs($tetri);

        return $sign.intdiv($tetri, 100).'.'.str_pad((string) ($tetri % 100), 2, '0', STR_PAD_LEFT);
    }

    private static function assertPlan(string $plan): void
    {
        if (! array_key_exists($plan, self::catalog())) {
            throw new InvalidArgumentException('plan');
        }
    }
}
