<?php

declare(strict_types=1);

namespace GeoAPI;

final class SiteFooter
{
    public static function html(): string
    {
        return SiteChrome::footer();
    }
}
