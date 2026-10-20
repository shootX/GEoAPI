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
                'description' => 'ადმინის API. ავტორიზაცია: Authorization: Bearer {token}. ტოკენს სჭირდება abilities: ["*"] და მომხმარებლის როლი admin.',
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
                    'schema' => ['type' => str_contains($name, 'key') ? 'string' : 'integer'],
                ];
            }
        }
        if ($method === 'GET' && ! str_contains($uri, '{')) {
            $parameters[] = ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer']];
            $parameters[] = ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer']];
            $parameters[] = ['name' => 'q', 'in' => 'query', 'schema' => ['type' => 'string']];
        }
        if ($parameters !== []) {
            $operation['parameters'] = $parameters;
        }
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $operation['requestBody'] = [
                'required' => $method !== 'PATCH',
                'content' => [
                    'application/json' => [
                        'schema' => ['type' => 'object'],
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
