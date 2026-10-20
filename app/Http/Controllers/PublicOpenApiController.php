<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

final class PublicOpenApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $url = rtrim((string) config('app.url'), '/');

        return response()->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'CarAPI',
                'version' => '1.0.0',
                'description' => 'Public catalog API. Send Authorization: Bearer carapi_sk_… from your server. The browser widget uses POST /widget/v1/{public_key}/… and does not use this secret.',
            ],
            'servers' => [['url' => $url]],
            'components' => [
                'securitySchemes' => [
                    'secretKey' => ['type' => 'http', 'scheme' => 'bearer'],
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
                '/api/v1/brands' => $this->list('Brands'),
                '/api/v1/brands/{slug}/models' => $this->list('Models for one brand slug', ['slug' => 'string']),
                '/api/v1/models/{id}/generations' => $this->list('Generations grouped by chassis for one model id', ['id' => 'integer']),
                '/api/v1/generations/{id}/modifications' => $this->list('Modifications for one public generation id', ['id' => 'integer']),
                '/api/v1/modifications/{id}' => $this->one('One modification'),
                '/api/v1/coverage' => [
                    'get' => [
                        'summary' => 'Catalog counts. generations_count is chassis groups. Row totals are on the home page.',
                        'responses' => [
                            '200' => ['description' => 'Counts'],
                            '401' => ['description' => 'Missing key', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                        ],
                    ],
                ],
            ],
        ]);
    }

    /**
     * @param  array<string, string>  $path
     * @return array<string, mixed>
     */
    private function list(string $summary, array $path = []): array
    {
        return ['get' => $this->operation($summary, $path, '#/components/schemas/List')];
    }

    /**
     * @return array<string, mixed>
     */
    private function one(string $summary): array
    {
        return ['get' => $this->operation($summary, ['id' => 'integer'], ['type' => 'object'])];
    }

    /**
     * @param  array<string, string>  $path
     * @param  array<string, mixed>|string  $schema
     * @return array<string, mixed>
     */
    private function operation(string $summary, array $path, array|string $schema): array
    {
        $parameters = [];
        foreach ($path as $name => $type) {
            $parameters[] = [
                'name' => $name,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => $type],
            ];
        }
        $parameters[] = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 1]];
        $parameters[] = ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer', 'default' => 50, 'maximum' => 100]];
        $responseSchema = is_string($schema) ? ['$ref' => $schema] : $schema;

        return [
            'summary' => $summary,
            'parameters' => $parameters,
            'responses' => [
                '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => $responseSchema]]],
                '401' => ['description' => 'Missing key', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
            ],
        ];
    }
}
