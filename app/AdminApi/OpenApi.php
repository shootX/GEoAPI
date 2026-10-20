<?php

declare(strict_types=1);

namespace App\AdminApi;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

final class OpenApi
{
    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        $paths = [];
        foreach (RouteFacade::getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }
            $uri = '/'.$route->uri();
            if (! str_starts_with($uri, '/api/admin') && ! str_starts_with($uri, '/api/docs')) {
                continue;
            }
            foreach ($route->methods() as $method) {
                if (in_array($method, ['HEAD', 'OPTIONS'], true)) {
                    continue;
                }
                $paths[$uri][strtolower($method)] = $this->operation($method, $uri, $route);
            }
        }
        ksort($paths);

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'CarAPI Admin',
                'version' => '1.0.0',
                'description' => 'ადმინის API. ავტორიზაცია: Authorization: Bearer {token}. ტოკენს სჭირდება abilities: ["*"] და მომხმარებლის როლი admin. pages-ის გამოქვეყნებული slug ცვლის ჩაშენებულ საჯარო გვერდს. ცარიელი სია ნიშნავს, რომ ჩანს კოდის გვერდი. model-photos-ის გზის id არის model_id. equipment-ის სიას სჭირდება modification_id. generations იღებს model_id და brand_id ფილტრს.',
            ],
            'servers' => [
                ['url' => rtrim((string) config('app.url'), '/')],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                    ],
                ],
                'schemas' => [
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'error' => [
                                'type' => 'object',
                                'properties' => [
                                    'code' => ['type' => 'string', 'example' => 'conflict'],
                                    'message' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                    'Page' => [
                        'type' => 'object',
                        'required' => ['slug', 'title', 'body'],
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'slug' => ['type' => 'string', 'example' => 'privacy'],
                            'locale' => ['type' => 'string', 'example' => 'ka'],
                            'title' => ['type' => 'string'],
                            'body' => ['type' => 'string'],
                            'status' => ['type' => 'string', 'enum' => ['draft', 'published']],
                        ],
                    ],
                    'CatalogList' => [
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
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'paths' => $paths,
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return list<array{method:string,path:string,summary:string}>
     */
    public function operations(array $spec): array
    {
        $rows = [];
        foreach ($spec['paths'] as $path => $methods) {
            if (! is_array($methods)) {
                continue;
            }
            foreach ($methods as $method => $operation) {
                $rows[] = [
                    'method' => strtoupper((string) $method),
                    'path' => (string) $path,
                    'summary' => is_array($operation) ? (string) ($operation['summary'] ?? '') : '',
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function operation(string $method, string $uri, Route $route): array
    {
        $secured = str_starts_with($uri, '/api/admin');
        $operation = [
            'tags' => [$this->tag($uri)],
            'summary' => $this->summary($method, $uri),
            'operationId' => $route->getName() ?: strtolower($method).'_'.trim(str_replace(['/', '{', '}'], ['_', '', ''], $uri), '_'),
            'responses' => [
                '200' => ['description' => 'წარმატებული პასუხი'],
                '201' => ['description' => 'შეიქმნა'],
                '401' => ['description' => 'ტოკენი არ არის ან არასწორია'],
                '403' => ['description' => 'ადმინის როლი ან უფლება * არ არის'],
                '404' => ['description' => 'ჩანაწერი ვერ მოიძებნა'],
                '409' => ['description' => 'უნიკალური ველი დუბლირებულია', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                '422' => ['description' => 'ვალიდაციის შეცდომა'],
            ],
        ];
        if (! $secured) {
            $operation['security'] = [];
        }
        $parameters = [];
        if (preg_match_all('/\{([^}]+)\}/', $uri, $matches) === 1 || isset($matches[1])) {
            foreach ($matches[1] ?? [] as $name) {
                $parameters[] = [
                    'name' => $name,
                    'in' => 'path',
                    'required' => true,
                    'description' => str_contains($uri, 'model-photos') ? 'model_photos.model_id' : null,
                    'schema' => ['type' => str_contains($name, 'key') ? 'string' : 'integer'],
                ];
            }
        }
        if ($method === 'GET' && ! str_contains($uri, '{')) {
            $parameters[] = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']];
            $parameters[] = ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']];
            $parameters[] = ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']];
            if (preg_match('#/(generations|models|cars)$#', $uri) === 1) {
                $parameters[] = ['name' => 'model_id', 'in' => 'query', 'schema' => ['type' => 'integer']];
                $parameters[] = ['name' => 'brand_id', 'in' => 'query', 'schema' => ['type' => 'integer']];
            }
            if (str_ends_with($uri, '/equipment')) {
                $parameters[] = ['name' => 'modification_id', 'in' => 'query', 'required' => true, 'schema' => ['type' => 'integer']];
            }
        }
        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $operation['requestBody'] = [
                'required' => $method !== 'PATCH',
                'content' => [
                    'application/json' => [
                        'schema' => str_contains($uri, '/pages')
                            ? ['$ref' => '#/components/schemas/Page']
                            : ['type' => 'object'],
                    ],
                ],
            ];
        }

        return $operation;
    }

    private function tag(string $uri): string
    {
        $parts = explode('/', trim($uri, '/'));

        return $parts[2] ?? ($parts[1] ?? 'api');
    }

    private function summary(string $method, string $uri): string
    {
        return match ($method) {
            'GET' => 'წაკითხვა '.$uri,
            'POST' => 'შექმნა '.$uri,
            'PUT', 'PATCH' => 'განახლება '.$uri,
            'DELETE' => 'წაშლა '.$uri,
            default => $method.' '.$uri,
        };
    }
}
