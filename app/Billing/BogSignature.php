<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * BOG Callback-Signature is SHA256withRSA over the raw body.
 * The public keys are published by the bank. They are not secrets.
 * BOG_ENV selects which key is used unless BOG_CALLBACK_PUBLIC_KEY is set.
 */
final class BogSignature
{
    public const PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAqczfAuhtxw2iF68kS0Hy
bGSv0ZlDAjsXh6VC8avDl3Vxa9qCn6Pzl37Tl2Z21WodiISLeXdhCtOMTeLNUBeb
CYD31y2/MwnhLYqlCk2bOh29fyPc1iT5Eu/k/1IaNRrK9/UVZaTkhOMeEm+aL4y8
5XsE4UjqftEmwrAdbO2G4cCpuoMC9ZXG9gAdr2BFN6i2Vt9eCen5Poj7E1ik7s8T
GyzploVV0NflhwBGeWnvQANUQGr87gsP5k2JG1z5EwnMybJQ7i3XT726rJMaV6QW
sY5hP72Mtv1I1zL2d9FXm9FWOzbpcXCyxuEBXvqqOHzogri8C7KRRYKyk97Ri7D6
8wIDAQAB
-----END PUBLIC KEY-----
PEM;

    public static function publicKey(): string
    {
        $override = config('services.bog.public_key');
        if (is_string($override) && trim($override) !== '') {
            return trim($override);
        }
        $env = config('services.bog.env') === 'production' ? 'production' : 'sandbox';
        $selected = config('services.bog.'.$env.'.public_key');
        if (is_string($selected) && trim($selected) !== '') {
            return trim($selected);
        }

        return self::PUBLIC_KEY;
    }

    public static function verify(string $body, ?string $signature): bool
    {
        if ($signature === null || trim($signature) === '') {
            return false;
        }
        $decoded = base64_decode(trim($signature), true);
        if ($decoded === false) {
            return false;
        }
        $key = openssl_pkey_get_public(self::publicKey());
        if ($key === false) {
            return false;
        }
        $result = openssl_verify($body, $decoded, $key, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }
}
