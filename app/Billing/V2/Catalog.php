<?php

declare(strict_types=1);

namespace App\Billing\V2;

use InvalidArgumentException;

/**
 * Workspace plans. USD cents are the list price. GEL is the NBG conversion.
 * A year is 15% below twelve monthly prices. The price includes VAT.
 * The monthly quota still resets every month on an annual term.
 * There is no automatic charge at the end of a term.
 */
final class Catalog
{
    public const CREDIT_DAYS = 90;

    public const EXTRA_PROJECT_CENTS = 500;

    /** @return array<string, array{usd_cents:int, quota:int, projects:int, rpm:int, rank:int}> */
    public static function plans(): array
    {
        return [
            'free' => ['usd_cents' => 0, 'quota' => 1000, 'projects' => 1, 'rpm' => 15, 'rank' => 0],
            'starter' => ['usd_cents' => 1900, 'quota' => 25000, 'projects' => 3, 'rpm' => 60, 'rank' => 1],
            'growth' => ['usd_cents' => 5900, 'quota' => 150000, 'projects' => 10, 'rpm' => 120, 'rank' => 2],
            'business' => ['usd_cents' => 14900, 'quota' => 500000, 'projects' => 25, 'rpm' => 240, 'rank' => 3],
        ];
    }

    /** @return array<string, array{usd_cents:int, requests:int}> */
    public static function packs(): array
    {
        return [
            'starter' => ['usd_cents' => 900, 'requests' => 10000],
            'growth' => ['usd_cents' => 2000, 'requests' => 50000],
            'business' => ['usd_cents' => 3500, 'requests' => 100000],
        ];
    }

    public static function purchasable(string $plan): bool
    {
        if (! in_array($plan, ['starter', 'growth', 'business'], true)) {
            return false;
        }

        return $plan !== 'business' || Flag::businessRpm();
    }

    public static function rpm(string $plan): int
    {
        if ($plan === 'enterprise') {
            return 120;
        }
        $listed = self::plans()[$plan]['rpm'] ?? 15;
        if ($plan === 'business' && ! Flag::businessRpm()) {
            return 120;
        }

        return $listed;
    }

    public static function usdCents(string $product, string $interval): int
    {
        if ($product === 'extra-project') {
            if ($interval !== 'month') {
                throw new InvalidArgumentException('interval');
            }

            return self::EXTRA_PROJECT_CENTS;
        }
        if (str_starts_with($product, 'credits:')) {
            $pack = substr($product, 8);
            if (! isset(self::packs()[$pack]) || $interval !== 'month') {
                throw new InvalidArgumentException('plan');
            }

            return self::packs()[$pack]['usd_cents'];
        }
        if (! isset(self::plans()[$product]) || $product === 'free') {
            throw new InvalidArgumentException('plan');
        }
        if (! in_array($interval, ['month', 'year'], true)) {
            throw new InvalidArgumentException('interval');
        }
        $monthly = self::plans()[$product]['usd_cents'];
        if ($interval === 'month') {
            return $monthly;
        }

        return intdiv($monthly * 12 * 85, 100);
    }

    public static function code(string $product, string $interval): string
    {
        self::usdCents($product, $interval);
        if ($interval === 'year') {
            return 'v2:'.$product.'-year';
        }

        return 'v2:'.$product;
    }

    /** @return array{kind:string, plan:string, interval:string} */
    public static function parse(string $code): array
    {
        if (! str_starts_with($code, 'v2:')) {
            throw new InvalidArgumentException('plan');
        }
        $body = substr($code, 3);
        $interval = 'month';
        if (str_ends_with($body, '-year')) {
            $interval = 'year';
            $body = substr($body, 0, -5);
        }
        if ($body === 'extra-project') {
            return ['kind' => 'extra-project', 'plan' => 'extra-project', 'interval' => 'month'];
        }
        if (str_starts_with($body, 'credits:')) {
            return ['kind' => 'credits', 'plan' => substr($body, 8), 'interval' => 'month'];
        }
        if (! isset(self::plans()[$body])) {
            throw new InvalidArgumentException('plan');
        }

        return ['kind' => 'plan', 'plan' => $body, 'interval' => $interval];
    }

    public static function rank(string $plan): int
    {
        if ($plan === 'enterprise') {
            return 4;
        }

        return self::plans()[$plan]['rank'] ?? 0;
    }
}
