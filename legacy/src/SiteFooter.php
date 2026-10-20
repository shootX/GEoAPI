<?php

declare(strict_types=1);

namespace Carapi;

final class SiteFooter
{
    public static function html(): string
    {
        return SiteChrome::footer();
    }
}
