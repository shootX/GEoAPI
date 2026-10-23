<?php

declare(strict_types=1);

namespace Geoapi\Http;

interface FetchTransport
{
    /**
     * @param list<string> $resolve
     * @return array{status:int,location:?string,body:string,failed:bool}
     */
    public function execute(string $url, array $resolve, int $maxBytes, int $timeoutSeconds): array;
}
