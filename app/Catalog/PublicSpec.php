<?php

declare(strict_types=1);

namespace App\Catalog;

final class PublicSpec
{
    /** @return array<string, mixed> */
    public static function document(): array
    {
        $url = rtrim((string) config('app.url'), '/');

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'CarAPI',
                'version' => '1.1.0',
                'description' => 'Public catalog API. Send Authorization: Bearer carapi_sk_… from your server. List responses keep data and meta, and add collection. limit defaults to 100 and cannot exceed 1000. per_page is an alias used only when limit is absent.',
            ],
            'servers' => [['url' => $url]],
            'components' => [
                'securitySchemes' => [
                    'secretKey' => ['type' => 'http', 'scheme' => 'bearer'],
                ],
                'parameters' => [
                    'page' => ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'default' => 1]],
                    'limit' => ['name' => 'limit', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000, 'default' => 100]],
                    'perPage' => ['name' => 'per_page', 'in' => 'query', 'description' => 'Alias of limit. Ignored when limit is present.', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000]],
                    'sort' => ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string']],
                    'direction' => ['name' => 'direction', 'in' => 'query', 'schema' => ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'asc']],
                    'q' => ['name' => 'q', 'in' => 'query', 'description' => 'Case-insensitive name search.', 'schema' => ['type' => 'string', 'maxLength' => 100]],
                    'modifiedAfter' => ['name' => 'modified_after', 'in' => 'query', 'description' => 'UTC timestamp. Returns rows whose modified value is newer.', 'schema' => ['type' => 'string', 'format' => 'date-time']],
                    'yearFrom' => ['name' => 'year_from', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    'yearTo' => ['name' => 'year_to', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    'body' => ['name' => 'body', 'in' => 'query', 'schema' => ['type' => 'string']],
                    'fuel' => ['name' => 'fuel', 'in' => 'query', 'schema' => ['type' => 'string']],
                    'drive' => ['name' => 'drive', 'in' => 'query', 'schema' => ['type' => 'string']],
                    'transmission' => ['name' => 'transmission', 'in' => 'query', 'schema' => ['type' => 'string']],
                    'brandId' => ['name' => 'brand_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                    'modelId' => ['name' => 'model_id', 'in' => 'query', 'schema' => ['type' => 'integer']],
                ],
                'schemas' => [
                    'List' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                            'meta' => [
                                'type' => 'object',
                                'properties' => [
                                    'page' => ['type' => 'integer'],
                                    'per_page' => ['type' => 'integer'],
                                    'total' => ['type' => 'integer'],
                                ],
                            ],
                            'collection' => [
                                'type' => 'object',
                                'properties' => [
                                    'count' => ['type' => 'integer'],
                                    'total' => ['type' => 'integer'],
                                    'pages' => ['type' => 'integer'],
                                    'next' => ['type' => 'string', 'nullable' => true],
                                    'prev' => ['type' => 'string', 'nullable' => true],
                                    'first' => ['type' => 'string', 'nullable' => true],
                                    'last' => ['type' => 'string', 'nullable' => true],
                                ],
                            ],
                        ],
                    ],
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
                ],
            ],
            'security' => [['secretKey' => []]],
            'paths' => [
                '/api/v1/brands' => self::list('Brands', ['q', 'modified']),
                '/api/v1/brands/{slug}/models' => self::list('Models for one brand slug', ['q', 'body', 'modified'], ['slug' => 'string']),
                '/api/v1/models/{id}/generations' => self::list('Generations grouped by chassis for one model id', ['q', 'year', 'body', 'modified'], ['id' => 'integer']),
                '/api/v1/generations/{id}/modifications' => self::list('Modifications for one public generation id', ['q', 'year', 'body', 'fuel', 'drive', 'transmission', 'modified'], ['id' => 'integer']),
                '/api/v1/modifications' => self::list('Modifications across brands, models, and generations', ['q', 'year', 'body', 'fuel', 'drive', 'transmission', 'brand', 'model', 'modified']),
                '/api/v1/modifications/{id}' => self::one('One modification'),
                '/api/v1/engines' => self::list('Engine volume, fuel, and power stored on modifications', ['q', 'fuel', 'brand', 'model', 'modified']),
                '/api/v1/bodies' => self::list('Distinct body types stored on models, generations, and modifications', ['q']),
                '/api/v1/fuel' => self::list('Fuel consumption stored on modification specs', ['q', 'year', 'fuel', 'brand', 'model', 'modified']),
                '/api/v1/colors' => self::list('Colors stored on model photos', ['q', 'brand', 'model', 'modified']),
                '/api/v1/coverage' => [
                    'get' => [
                        'summary' => 'Catalog counts.',
                        'responses' => self::responses(['type' => 'object']),
                    ],
                ],
            ],
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function requests(): array
    {
        $items = [];
        foreach (array_keys(self::document()['paths']) as $path) {
            $items[] = [
                'name' => $path,
                'request' => [
                    'method' => 'GET',
                    'header' => [],
                    'url' => '{{baseUrl}}'.$path,
                ],
            ];
        }

        return $items;
    }

    /**
     * @param  list<string>  $filters
     * @param  array<string, string>  $path
     * @return array<string, mixed>
     */
    private static function list(string $summary, array $filters, array $path = []): array
    {
        return ['get' => self::operation($summary, $path, '#/components/schemas/List', $filters)];
    }

    /** @return array<string, mixed> */
    private static function one(string $summary): array
    {
        return ['get' => self::operation($summary, ['id' => 'integer'], ['type' => 'object'], [])];
    }

    /**
     * @param  array<string, string>  $path
     * @param  array<string, mixed>|string  $schema
     * @param  list<string>  $filters
     * @return array<string, mixed>
     */
    private static function operation(string $summary, array $path, array|string $schema, array $filters): array
    {
        $parameters = [];
        foreach ($path as $name => $type) {
            $parameters[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => $type]];
        }
        if ($schema === '#/components/schemas/List') {
            foreach (['page', 'limit', 'perPage', 'sort', 'direction'] as $name) {
                $parameters[] = ['$ref' => '#/components/parameters/'.$name];
            }
            $map = [
                'q' => 'q',
                'modified' => 'modifiedAfter',
                'year' => 'yearFrom',
                'body' => 'body',
                'fuel' => 'fuel',
                'drive' => 'drive',
                'transmission' => 'transmission',
                'brand' => 'brandId',
                'model' => 'modelId',
            ];
            foreach ($filters as $filter) {
                if ($filter === 'year') {
                    $parameters[] = ['$ref' => '#/components/parameters/yearFrom'];
                    $parameters[] = ['$ref' => '#/components/parameters/yearTo'];

                    continue;
                }
                if (isset($map[$filter])) {
                    $parameters[] = ['$ref' => '#/components/parameters/'.$map[$filter]];
                }
            }
        }
        $responseSchema = is_string($schema) ? ['$ref' => $schema] : $schema;

        return [
            'summary' => $summary,
            'parameters' => $parameters,
            'responses' => self::responses($responseSchema),
        ];
    }

    /** @param array<string, mixed>|array{'$ref': string} $schema @return array<string, mixed> */
    private static function responses(array $schema): array
    {
        $error = ['description' => 'Error', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]];

        return [
            '200' => [
                'description' => 'OK',
                'headers' => [
                    'X-RateLimit-Limit' => ['schema' => ['type' => 'integer']],
                    'X-RateLimit-Remaining' => ['schema' => ['type' => 'integer']],
                    'X-RateLimit-Reset' => ['schema' => ['type' => 'integer']],
                    'X-Quota-Limit' => ['schema' => ['type' => 'integer']],
                    'X-Quota-Remaining' => ['schema' => ['type' => 'integer']],
                    'X-Quota-Reset' => ['schema' => ['type' => 'integer']],
                ],
                'content' => ['application/json' => ['schema' => $schema]],
            ],
            '401' => $error,
            '422' => $error,
            '429' => $error,
        ];
    }
}
