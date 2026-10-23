<?php

declare(strict_types=1);

namespace Geoapi\Catalog;

final class DeniedException extends \RuntimeException
{
    public function __construct(public int $status)
    {
        parent::__construct('denied');
    }
}
