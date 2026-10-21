<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Catalog\PublicSpec;
use Illuminate\Http\JsonResponse;

final class PublicPostmanController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'info' => [
                'name' => 'GeoAPI',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
                'description' => 'Public catalog API. Set token to your server key. The placeholder geoapi_sk_… is not a real key.',
            ],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [
                    ['key' => 'token', 'value' => '{{token}}', 'type' => 'string'],
                ],
            ],
            'variable' => [
                ['key' => 'baseUrl', 'value' => PublicSpec::ORIGIN],
                ['key' => 'token', 'value' => 'geoapi_sk_…'],
            ],
            'item' => PublicSpec::requests(),
        ]);
    }
}
