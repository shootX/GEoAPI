<?php

declare(strict_types=1);

namespace Carapi\Site;

final class Locale
{
    public const COOKIE = 'carapi_locale';

    public static function current(): string
    {
        $query = $_GET['lang'] ?? null;
        if ($query === 'en' || $query === 'ka') {
            self::remember($query);

            return $query;
        }
        $cookie = $_COOKIE[self::COOKIE] ?? '';

        return $cookie === 'en' ? 'en' : 'ka';
    }

    public static function remember(string $lang): void
    {
        if ($lang !== 'en' && $lang !== 'ka') {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        setcookie(self::COOKIE, $lang, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $lang;
    }

    public static function safeReturn(): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (!is_string($referer) || $referer === '') {
            return '/';
        }
        $parts = parse_url($referer);
        if (!is_array($parts)) {
            return '/';
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $request = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $requestHost = explode(':', $request)[0];
        $path = (string) ($parts['path'] ?? '/');
        if ($host === '' || $host !== $requestHost || !str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/locale/')) {
            return '/';
        }

        return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}
