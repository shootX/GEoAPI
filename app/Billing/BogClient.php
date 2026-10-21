<?php

declare(strict_types=1);

namespace App\Billing;

use Illuminate\Support\Facades\Http;

/**
 * Bank of Georgia sandbox only. Production hosts are not accepted.
 */
final class BogClient
{
    public const TOKEN_URL = 'https://oauth2-sandbox.bog.ge/auth/realms/bog/protocol/openid-connect/token';

    public const ORDER_URL = 'https://api-sandbox.bog.ge/payments/v1/ecommerce/orders';

    public static function configured(): bool
    {
        $id = config('services.bog.client_id');
        $secret = config('services.bog.client_secret');

        return is_string($id) && trim($id) !== '' && is_string($secret) && trim($secret) !== '';
    }

    /**
     * @return array{id:string, redirect:string}
     */
    public static function createOrder(string $externalId, string $gel, string $productId, string $callback, string $success, string $fail): array
    {
        if (! self::configured()) {
            throw new BillingNotConfigured('payments not configured');
        }
        self::assertSandboxUrl(self::TOKEN_URL);
        self::assertSandboxUrl(self::ORDER_URL);
        self::assertAmount($gel);
        self::assertCallback($callback);
        $token = self::token();
        $payload = [
            'callback_url' => $callback,
            'external_order_id' => $externalId,
            'purchase_units' => [
                'currency' => 'GEL',
                'total_amount' => '__AMOUNT__',
                'basket' => [[
                    'quantity' => 1,
                    'unit_price' => '__AMOUNT__',
                    'product_id' => $productId,
                ]],
            ],
            'redirect_urls' => [
                'fail' => $fail,
                'success' => $success,
            ],
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            throw new BillingNotConfigured('order');
        }
        $json = str_replace('"__AMOUNT__"', $gel, $json);
        $response = Http::withToken($token)
            ->withHeaders([
                'Accept-Language' => 'ka',
                'Idempotency-Key' => $externalId,
                'Content-Type' => 'application/json',
            ])
            ->withBody($json, 'application/json')
            ->post(self::ORDER_URL);
        if (! $response->successful()) {
            throw new BillingNotConfigured('order');
        }
        $body = $response->json();
        $id = is_array($body) ? ($body['id'] ?? null) : null;
        $redirect = is_array($body) ? ($body['_links']['redirect']['href'] ?? null) : null;
        if (! is_string($id) || $id === '' || ! is_string($redirect) || $redirect === '') {
            throw new BillingNotConfigured('order');
        }

        return ['id' => $id, 'redirect' => $redirect];
    }

    private static function token(): string
    {
        $id = trim((string) config('services.bog.client_id'));
        $secret = trim((string) config('services.bog.client_secret'));
        $response = Http::withBasicAuth($id, $secret)
            ->asForm()
            ->post(self::TOKEN_URL, ['grant_type' => 'client_credentials']);
        $token = $response->successful() ? $response->json('access_token') : null;
        if (! is_string($token) || $token === '') {
            throw new BillingNotConfigured('token');
        }

        return $token;
    }

    private static function assertSandboxUrl(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (! in_array($host, ['oauth2-sandbox.bog.ge', 'api-sandbox.bog.ge'], true)) {
            throw new BillingNotConfigured('sandbox');
        }
    }

    private static function assertAmount(string $gel): void
    {
        if (! preg_match('/^\d+\.\d{2}$/', $gel)) {
            throw new BillingNotConfigured('amount');
        }
    }

    private static function assertCallback(string $callback): void
    {
        $scheme = parse_url($callback, PHP_URL_SCHEME);
        $host = parse_url($callback, PHP_URL_HOST);
        $local = in_array($host, ['localhost', '127.0.0.1'], true);
        if ($scheme === 'https' || $local || app()->environment('testing')) {
            return;
        }

        throw new BillingNotConfigured('callback');
    }
}
