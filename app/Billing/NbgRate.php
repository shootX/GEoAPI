<?php

declare(strict_types=1);

namespace App\Billing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Official National Bank of Georgia USD rate.
 * https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/en/json/?currencies=USD
 * The stored row is the fallback when a later fetch fails. No rate is invented.
 */
final class NbgRate
{
    public const URL = 'https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/en/json/?currencies=USD';

    /**
     * @return array{code:string, rate:string, quantity:int, rated_on:string}
     */
    public static function parse(string $json): array
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded) || ! isset($decoded[0]) || ! is_array($decoded[0])) {
            throw new RuntimeException('nbg');
        }
        $date = $decoded[0]['date'] ?? '';
        if (! is_string($date) || ! preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
            throw new RuntimeException('nbg date');
        }
        $currencies = $decoded[0]['currencies'] ?? null;
        if (! is_array($currencies)) {
            throw new RuntimeException('nbg currencies');
        }
        foreach ($currencies as $row) {
            if (! is_array($row) || ($row['code'] ?? '') !== 'USD') {
                continue;
            }
            $quantity = $row['quantity'] ?? 1;
            if (! is_int($quantity) && ! (is_string($quantity) && preg_match('/^\d+$/', $quantity))) {
                throw new RuntimeException('nbg quantity');
            }
            $quantity = (int) $quantity;
            if ($quantity < 1) {
                throw new RuntimeException('nbg quantity');
            }
            $formatted = $row['rateFormated'] ?? $row['rateFormatted'] ?? null;
            if (! is_string($formatted) || ! preg_match('/^\d+(\.\d+)?$/', $formatted)) {
                throw new RuntimeException('nbg rate');
            }

            return [
                'code' => 'USD',
                'rate' => $formatted,
                'quantity' => $quantity,
                'rated_on' => substr($date, 0, 10),
            ];
        }

        throw new RuntimeException('nbg usd');
    }

    /**
     * @param  array{code:string, rate:string, quantity:int, rated_on:string}  $parsed
     */
    public static function remember(array $parsed): void
    {
        $now = now();
        DB::table('billing_rates')->insert([
            'code' => $parsed['code'],
            'rate' => $parsed['rate'],
            'quantity' => $parsed['quantity'],
            'rated_on' => $parsed['rated_on'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array{code:string, rate:string, quantity:int, rated_on:string}|null */
    public static function latest(): ?array
    {
        $row = DB::table('billing_rates')->where('code', 'USD')->orderByDesc('rated_on')->orderByDesc('id')->first();
        if ($row === null) {
            return null;
        }

        return [
            'code' => 'USD',
            'rate' => self::decimal((string) $row->rate),
            'quantity' => (int) $row->quantity,
            'rated_on' => substr((string) $row->rated_on, 0, 10),
        ];
    }

    /**
     * Fetch the public JSON. On failure, keep the last stored rate and return it.
     *
     * @return array{code:string, rate:string, quantity:int, rated_on:string, fetched:bool}|null
     */
    public static function refresh(): ?array
    {
        try {
            $response = Http::timeout(10)->acceptJson()->get(self::URL);
            if (! $response->successful()) {
                throw new RuntimeException('nbg http');
            }
            $parsed = self::parse($response->body());
            self::remember($parsed);

            return $parsed + ['fetched' => true];
        } catch (\Throwable) {
            $latest = self::latest();

            return $latest === null ? null : $latest + ['fetched' => false];
        }
    }

    private static function decimal(string $value): string
    {
        if (preg_match('/^\d+(\.\d+)?$/', $value)) {
            return $value;
        }
        if (preg_match('/^(\d+(?:\.\d+)?)/', $value, $match)) {
            return $match[1];
        }

        return $value;
    }
}
