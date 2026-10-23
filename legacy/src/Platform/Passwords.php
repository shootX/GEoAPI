<?php

declare(strict_types=1);

namespace Geoapi\Platform;

final class Passwords
{
    public const MIN = 8;
    public const MAX = 128;


    public static function hash(string $password): string
    {
        $hash = password_hash($password, PASSWORD_ARGON2ID);

        if (!is_string($hash) || $hash === '') {
            throw new UnavailableException();
        }

        return $hash;
    }

    public static function verify(string $password, string $hash): bool
    {
        return $hash !== '' && password_verify($password, $hash);
    }

    /** The rule as one sentence, for form hints. */
    public static function rule(string $locale = 'ka'): string
    {
        return $locale === 'en'
            ? 'At least 8 characters, with one uppercase letter, one digit and one symbol (e.g. ! ? # @).'
            : 'მინიმუმ 8 სიმბოლო, მათ შორის ერთი დიდი ასო, ერთი ციფრი და ერთი სიმბოლო (მაგ. ! ? # @).';
    }

    /** The first rule the password breaks, in Georgian or English, or null when it is strong enough. */
    public static function error(string $password, string $locale = 'ka'): ?string
    {
        $en = $locale === 'en';
        $length = mb_strlen($password, 'UTF-8');
        if ($length < self::MIN) {
            return $en ? 'The password must be at least 8 characters.' : 'პაროლი უნდა შეიცავდეს მინიმუმ 8 სიმბოლოს.';
        }
        if ($length > self::MAX) {
            return $en ? 'The password must not be longer than 128 characters.' : 'პაროლი არ უნდა აღემატებოდეს 128 სიმბოლოს.';
        }
        if (preg_match('/\p{Lu}/u', $password) !== 1) {
            return $en ? 'The password must contain at least one uppercase letter (A-Z).' : 'პაროლი უნდა შეიცავდეს მინიმუმ ერთ დიდ ასოს (A-Z).';
        }
        if (preg_match('/\p{Nd}/u', $password) !== 1) {
            return $en ? 'The password must contain at least one digit (0-9).' : 'პაროლი უნდა შეიცავდეს მინიმუმ ერთ ციფრს (0-9).';
        }
        if (preg_match('/[^\p{L}\p{N}\s]/u', $password) !== 1) {
            return $en ? 'The password must contain at least one symbol, such as ! ? # @.' : 'პაროლი უნდა შეიცავდეს მინიმუმ ერთ სიმბოლოს, მაგალითად ! ? # @.';
        }

        return null;
    }
}
