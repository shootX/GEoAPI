<?php

declare(strict_types=1);

namespace Geoapi\Http;

/**
 * New downloads and public redistribution stay off until a source and a license are approved.
 * Cached files remain on disk.
 */
final class ImagePolicy
{
    public static function downloadsAllowed(): bool
    {
        return false;
    }

    /** @return list<string> */
    public static function allowedHosts(): array
    {
        return [];
    }

    public static function mayRedistribute(string $license): bool
    {
        return false;
    }
}
