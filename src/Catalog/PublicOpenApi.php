<?php

declare(strict_types=1);

namespace Carapi\Catalog;

final class PublicOpenApi
{
    /** @return array<string, mixed> */
    public static function document(): array
    {
        $error = ['$ref' => '#/components/schemas/Error'];
        $list = static function (string $schema): array {
            return [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/' . $schema]],
                    'meta' => ['$ref' => '#/components/schemas/Page'],
                ],
            ];
        };
        $json = ['application/json' => ['schema' => $error]];

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'CarAPI',
                'version' => '1',
                'description' => 'Public catalog. Server calls use Authorization: Bearer carapi_sk_. The embeddable widget uses POST /widget/v1/{public-key} and does not return generation_id or modification_id. The full picker is /demo and /api/picker.',
            ],
            'components' => [
                'securitySchemes' => [
                    'secretKey' => ['type' => 'http', 'scheme' => 'bearer'],
                ],
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'error' => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => ['type' => 'string'],
                                    'message' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                    'Page' => [
                        'type' => 'object',
                        'properties' => [
                            'page' => ['type' => 'integer'],
                            'per_page' => ['type' => 'integer'],
                            'total' => ['type' => 'integer'],
                        ],
                    ],
                    'Brand' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                        ],
                    ],
                    'Model' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'brand_id' => ['type' => 'string'],
                            'slug' => ['type' => 'string'],
                            'name' => ['type' => 'string'],
                            'generations_count' => ['type' => 'integer'],
                        ],
                    ],
                    'Generation' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string'],
                            'model_id' => ['type' => 'string'],
                            'name' => ['type' => 'string', 'nullable' => true],
                            'chassis_code' => ['type' => 'string', 'nullable' => true],
                            'year_start' => ['type' => 'integer', 'nullable' => true],
                            'year_end' => ['type' => 'integer', 'nullable' => true],
                            'display_label' => ['type' => 'string'],
                        ],
                    ],
                    'Coverage' => [
                        'type' => 'object',
                        'properties' => [
                            'brands_count' => ['type' => 'integer'],
                            'models_count' => ['type' => 'integer'],
                            'generations_count' => [
                                'type' => 'integer',
                                'description' => 'Distinct groups of model plus chassis code, or name when chassis is empty. Not a raw row count.',
                            ],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/api/v1/brands' => [
                    'get' => [
                        'security' => [['secretKey' => []]],
                        'parameters' => [
                            ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1]],
                            ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100]],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Brands', 'content' => ['application/json' => ['schema' => $list('Brand')]]],
                            '401' => ['description' => 'Missing or invalid key', 'content' => $json],
                        ],
                    ],
                ],
                '/api/v1/brands/{slug}/models' => [
                    'get' => [
                        'security' => [['secretKey' => []]],
                        'parameters' => [['name' => 'slug', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => [
                            '200' => ['description' => 'Models', 'content' => ['application/json' => ['schema' => $list('Model')]]],
                            '401' => ['description' => 'Missing or invalid key', 'content' => $json],
                        ],
                    ],
                ],
                '/api/v1/models/{id}/generations' => [
                    'get' => [
                        'security' => [['secretKey' => []]],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => [
                            '200' => ['description' => 'Grouped generations', 'content' => ['application/json' => ['schema' => $list('Generation')]]],
                        ],
                    ],
                ],
                '/api/v1/coverage' => [
                    'get' => [
                        'security' => [['secretKey' => []]],
                        'responses' => [
                            '200' => ['description' => 'Coverage counts', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Coverage']]]],
                        ],
                    ],
                ],
                '/api/picker/brands' => [
                    'get' => [
                        'parameters' => [
                            ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string', 'maxLength' => 80], 'description' => 'Case-insensitive match on name or synonym.'],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Same-origin picker brands', 'content' => ['application/json' => ['schema' => $list('Brand')]]],
                        ],
                    ],
                ],
                '/widget/v1/{publicKey}/brands' => [
                    'post' => [
                        'parameters' => [['name' => 'publicKey', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => [
                            '200' => ['description' => 'Widget brands. Demo key is the path /widget/v1/demo/brands and requires this site as Origin.'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
