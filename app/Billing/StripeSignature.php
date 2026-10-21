<?php

declare(strict_types=1);

namespace App\Billing;

final class StripeSignature
{
    public const TOLERANCE_SECONDS = 300;

    /**
     * @return array{id: string, type: string}
     */
    public static function verify(string $payload, ?string $header, string $secret, ?int $now = null): array
    {
        if (trim($secret) === '') {
            throw new StripeSignatureException(StripeSignatureException::UNCONFIGURED);
        }

        $parsed = self::parseHeader($header);
        if ($parsed === null) {
            throw new StripeSignatureException(StripeSignatureException::MISSING);
        }

        [$timestamp, $signatures] = $parsed;
        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $matched = false;
        foreach ($signatures as $signature) {
            if (strlen($expected) === strlen($signature) && hash_equals($expected, $signature)) {
                $matched = true;
                break;
            }
        }
        if (! $matched) {
            throw new StripeSignatureException(StripeSignatureException::FORGED);
        }

        $now ??= now()->getTimestamp();
        if (abs($now - $timestamp) > self::TOLERANCE_SECONDS) {
            throw new StripeSignatureException(StripeSignatureException::EXPIRED);
        }

        $decoded = json_decode($payload, true);
        $id = is_array($decoded) ? ($decoded['id'] ?? null) : null;
        $type = is_array($decoded) ? ($decoded['type'] ?? null) : null;
        if (! is_string($id) || $id === '' || ! is_string($type) || $type === '') {
            throw new StripeSignatureException(StripeSignatureException::MALFORMED);
        }

        return ['id' => $id, 'type' => $type];
    }

    /**
     * @return array{0: int, 1: list<string>}|null
     */
    private static function parseHeader(?string $header): ?array
    {
        if ($header === null || trim($header) === '') {
            return null;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $pieces = explode('=', trim($part), 2);
            if (count($pieces) !== 2) {
                continue;
            }
            [$key, $value] = [trim($pieces[0]), trim($pieces[1])];
            if ($key === 't' && $timestamp === null && $value !== '' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            return null;
        }

        return [$timestamp, $signatures];
    }
}
