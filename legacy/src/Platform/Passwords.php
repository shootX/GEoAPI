<?php

declare(strict_types=1);

namespace Carapi\Platform;

final class Passwords
{
    public const MIN = 15;
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

    public static function error(string $password): ?string
    {
        $length = mb_strlen($password, 'UTF-8');
        if ($length < self::MIN) {
            return 'პაროლი უნდა შეიცავდეს მინიმუმ 15 სიმბოლოს.';
        }
        if ($length > self::MAX) {
            return 'პაროლი არ უნდა აღემატებოდეს 128 სიმბოლოს.';
        }

        return null;
    }
}
