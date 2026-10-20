<?php

declare(strict_types=1);

namespace Carapi\Platform;

final class Origins
{
    public static function normalize(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || strcasecmp($value, 'null') === 0 || str_contains($value, '*')) {
            return null;
        }
        $parts = parse_url($value);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }
        if (isset($parts['path']) && $parts['path'] !== '') {
            return null;
        }
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }
        if ($scheme === 'http' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
            return null;
        }
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        $default = $scheme === 'https' ? 443 : 80;
        $suffix = $port === $default ? '' : ':' . $port;

        return $scheme . '://' . $host . $suffix;
    }

    /** @param list<string> $allowed */
    public static function matches(string $origin, array $allowed): bool
    {
        $normal = self::normalize($origin);
        if ($normal === null) {
            return false;
        }
        foreach ($allowed as $item) {
            if (self::normalize($item) === $normal) {
                return true;
            }
        }

        return false;
    }
}
