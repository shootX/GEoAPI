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
                'title' => 'GeoAPI',
                'version' => '1.2.0',
                'description' => 'Public catalog API. Send Authorization: Bearer geoapi_sk_… from your server. List responses keep data and meta, and add collection. limit defaults to 100 and cannot exceed 1000. per_page is an alias used only when limit is absent. Fuel consumption is litres per 100 km. Dimensions and performance groups are null because those columns are not stored.',
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
                    'volumeFrom' => ['name' => 'volume_from', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20000]],
                    'volumeTo' => ['name' => 'volume_to', 'in' => 'query', 'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 20000]],
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
                    'Brand' => self::object(['id' => 'string', 'slug' => 'string', 'name' => 'string', 'synonyms' => ['type' => 'array', 'items' => ['type' => 'string']], 'is_lcv_or_commercial' => 'boolean', 'logo_url_light' => self::nullable('string'), 'logo_url_dark' => self::nullable('string'), 'modified' => self::nullable('string')]),
                    'Model' => self::object(['id' => 'string', 'brand_id' => 'string', 'slug' => 'string', 'name' => 'string', 'markets' => ['type' => 'array', 'items' => ['type' => 'string']], 'has_generations' => 'boolean', 'generations_count' => 'integer', 'modified' => self::nullable('string')]),
                    'Generation' => self::object(['id' => 'string', 'model_id' => 'string', 'slug' => 'string', 'name' => self::nullable('string'), 'year_start' => self::nullable('integer'), 'year_end' => self::nullable('integer'), 'period_text' => self::nullable('string'), 'body_type' => self::nullable('string'), 'chassis_code' => self::nullable('string'), 'steering_wheel' => self::nullable('string'), 'market' => self::nullable('string'), 'display_label' => 'string', 'modified' => self::nullable('string')]),
                    'Year' => self::object(['id' => 'string', 'year' => 'integer']),
                    'GenerationModification' => self::object(['id' => 'string', 'generation_id' => 'string', 'name' => 'string', 'engine_volume_cc' => self::nullable('integer'), 'transmission' => self::nullable('string'), 'year_start' => self::nullable('integer'), 'year_end' => self::nullable('integer'), 'modified' => self::nullable('string')]),
                    'ModificationSearch' => self::object(['id' => 'string', 'name' => 'string', 'brand_id' => 'string', 'model_id' => 'string', 'generation_id' => self::nullable('string'), 'engine_volume_cc' => self::nullable('integer'), 'transmission' => self::nullable('string'), 'drive_type' => self::nullable('string'), 'body_type' => self::nullable('string'), 'fuel' => self::nullable('string'), 'year_start' => self::nullable('integer'), 'year_end' => self::nullable('integer'), 'modified' => self::nullable('string')]),
                    'Modification' => self::object([
                        'id' => 'string',
                        'name' => 'string',
                        'engine_volume_cc' => self::nullable('integer'),
                        'transmission' => self::nullable('string'),
                        'drive_type' => self::nullable('string'),
                        'body_type' => self::nullable('string'),
                        'year_start' => self::nullable('integer'),
                        'year_end' => self::nullable('integer'),
                        'brand' => self::object(['id' => 'string', 'slug' => 'string', 'name' => 'string']),
                        'model' => self::object(['id' => 'string', 'slug' => 'string', 'name' => 'string']),
                        'generation' => self::nullableObject(['id' => 'string', 'name' => self::nullable('string')]),
                        'modified' => self::nullable('string'),
                    ]),
                    'ModificationSpecs' => self::object([
                        'modification_id' => 'string',
                        'engine' => self::object(['volume_cc' => self::nullable('integer'), 'fuel' => self::nullable('string'), 'power_hp' => self::nullable('number')]),
                        'transmission' => self::object(['type' => self::nullable('string'), 'drive' => self::nullable('string')]),
                        'dimensions' => ['nullable' => true, 'description' => 'MISSING. No dimension columns are stored.'],
                        'performance' => ['nullable' => true, 'description' => 'MISSING. No performance columns are stored. power_hp stays on engine.'],
                        'consumption' => self::object(['city_l' => self::nullable('number'), 'highway_l' => self::nullable('number'), 'combined_l' => self::nullable('number'), 'unit' => 'string']),
                    ]),
                    'EquipmentItem' => self::object(['id' => 'string', 'category' => self::nullable('string'), 'name' => 'string', 'value' => self::nullable('string')]),
                    'ModificationEquipment' => self::object([
                        'modification_id' => 'string',
                        'standard' => self::itemArray('EquipmentItem'),
                        'optional' => self::itemArray('EquipmentItem'),
                        'not_available' => self::itemArray('EquipmentItem'),
                        'unknown' => self::itemArray('EquipmentItem'),
                    ]),
                    'Engine' => self::object(['id' => 'string', 'volume_cc' => self::nullable('integer'), 'fuel' => self::nullable('string'), 'power_hp' => self::nullable('number'), 'brand_id' => 'string', 'model_id' => 'string', 'modified' => self::nullable('string')]),
                    'Body' => self::object(['id' => 'string', 'name' => 'string', 'count' => 'integer', 'modified' => self::nullable('string')]),
                    'Fuel' => self::object(['id' => 'string', 'fuel' => self::nullable('string'), 'city_l' => self::nullable('number'), 'highway_l' => self::nullable('number'), 'combined_l' => self::nullable('number'), 'brand_id' => 'string', 'model_id' => 'string', 'modified' => self::nullable('string')]),
                    'Color' => self::object(['id' => 'string', 'model_id' => 'string', 'brand_id' => 'string', 'name' => 'string', 'verified' => 'boolean', 'modified' => self::nullable('string')]),
                    'Coverage' => self::object([
                        'brands_count' => 'integer',
                        'models_count' => 'integer',
                        'generations_count' => 'integer',
                        'models_with_generations_count' => 'integer',
                        'models_without_generations_count' => 'integer',
                        'modifications_count' => 'integer',
                        'photos_count' => 'integer',
                        'generations_with_photo_urls_count' => 'integer',
                        'measured_at' => 'string',
                    ]),
                ],
            ],
            'security' => [['secretKey' => []]],
            'paths' => [
                '/api/v1/brands' => self::list('Brands', ['q', 'modified'], [], 'Brand'),
                '/api/v1/brands/{slug}/models' => self::list('Models for one brand slug', ['q', 'body', 'modified'], ['slug' => 'string'], 'Model'),
                '/api/v1/models/{id}/years' => self::list('Years covered by one model. An open year_end stops at the current UTC year.', [], ['id' => 'integer'], 'Year'),
                '/api/v1/models/{id}/generations' => self::list('Generations grouped by chassis for one model id', ['q', 'year', 'body', 'modified'], ['id' => 'integer'], 'Generation'),
                '/api/v1/generations/{id}/modifications' => self::list('Modifications for one public generation id', ['q', 'year', 'body', 'fuel', 'drive', 'transmission', 'modified'], ['id' => 'integer'], 'GenerationModification'),
                '/api/v1/modifications/{id}/specs' => self::one('Stored engine, transmission, and consumption fields for one modification. Unknown specification keys are omitted.', 'ModificationSpecs'),
                '/api/v1/modifications/{id}/equipment' => self::one('Equipment grouped as standard, optional, not_available, and unknown.', 'ModificationEquipment'),
                '/api/v1/modifications/{id}' => self::one('One modification', 'Modification'),
                '/api/v1/modifications' => self::list('Modifications across brands, models, and generations', ['q', 'year', 'body', 'fuel', 'drive', 'transmission', 'brand', 'model', 'modified'], [], 'ModificationSearch'),
                '/api/v1/engines' => self::list('Engine volume, fuel, and power stored on modifications', ['q', 'fuel', 'brand', 'model', 'volume', 'modified'], [], 'Engine'),
                '/api/v1/bodies' => self::list('Distinct body types stored on models, generations, and modifications', ['q'], [], 'Body'),
                '/api/v1/fuel' => self::list('Fuel consumption stored on modification specs', ['q', 'year', 'fuel', 'brand', 'model', 'modified'], [], 'Fuel'),
                '/api/v1/colors' => self::list('Colors stored on model photos', ['q', 'brand', 'model', 'modified'], [], 'Color'),
                '/api/v1/coverage' => [
                    'get' => [
                        'summary' => 'Catalog counts.',
                        'responses' => self::responses(['$ref' => '#/components/schemas/Coverage']),
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
    private static function list(string $summary, array $filters, array $path = [], ?string $item = null): array
    {
        $schema = $item === null ? '#/components/schemas/List' : [
            'allOf' => [
                ['$ref' => '#/components/schemas/List'],
                ['type' => 'object', 'properties' => [
                    'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/'.$item]],
                ]],
            ],
        ];

        return ['get' => self::operation($summary, $path, $schema, $filters, true)];
    }

    /** @return array<string, mixed> */
    private static function one(string $summary, string $schema): array
    {
        return ['get' => self::operation($summary, ['id' => 'integer'], [
            'type' => 'object',
            'required' => ['data'],
            'properties' => ['data' => ['$ref' => '#/components/schemas/'.$schema]],
        ], [])];
    }

    /**
     * @param  array<string, string>  $path
     * @param  array<string, mixed>|string  $schema
     * @param  list<string>  $filters
     * @return array<string, mixed>
     */
    private static function operation(string $summary, array $path, array|string $schema, array $filters, bool $list = false): array
    {
        $parameters = [];
        foreach ($path as $name => $type) {
            $parameters[] = ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => ['type' => $type]];
        }
        if ($list) {
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
                'volume' => 'volumeFrom',
            ];
            foreach ($filters as $filter) {
                if ($filter === 'year') {
                    $parameters[] = ['$ref' => '#/components/parameters/yearFrom'];
                    $parameters[] = ['$ref' => '#/components/parameters/yearTo'];

                    continue;
                }
                if ($filter === 'volume') {
                    $parameters[] = ['$ref' => '#/components/parameters/volumeFrom'];
                    $parameters[] = ['$ref' => '#/components/parameters/volumeTo'];

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
            '403' => $error,
            '404' => $error,
            '422' => $error,
            '429' => $error,
        ];
    }

    /** @param array<string, mixed> $properties @return array<string, mixed> */
    private static function object(array $properties): array
    {
        $fields = [];
        foreach ($properties as $name => $type) {
            $fields[$name] = is_array($type) ? $type : ['type' => $type];
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => array_keys($fields),
            'properties' => $fields,
        ];
    }

    /** @return array<string, mixed> */
    private static function nullable(string $type): array
    {
        return ['type' => $type, 'nullable' => true];
    }

    /** @param array<string, mixed> $properties @return array<string, mixed> */
    private static function nullableObject(array $properties): array
    {
        return self::object($properties) + ['nullable' => true];
    }

    /** @return array<string, mixed> */
    private static function itemArray(string $schema): array
    {
        return ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/'.$schema]];
    }
}
