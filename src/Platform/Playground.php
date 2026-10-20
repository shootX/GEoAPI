<?php

declare(strict_types=1);

namespace Carapi\Platform;

use Carapi\Catalog\CatalogRepository;
use Carapi\Catalog\Connection as CatalogConnection;

final class Playground
{
    /** @return array<string, array{route:string, fields:list<string>}> */
    public static function registry(): array
    {
        return [
            '/api/v1/brands' => ['route' => 'brands', 'fields' => ['page', 'per_page']],
            '/api/v1/brands/{slug}/models' => ['route' => 'models', 'fields' => ['slug', 'page', 'per_page']],
            '/api/v1/models/{id}/generations' => ['route' => 'generations', 'fields' => ['id', 'page', 'per_page']],
            '/api/v1/generations/{id}/modifications' => ['route' => 'modifications', 'fields' => ['id', 'page', 'per_page']],
            '/api/v1/modifications/{id}' => ['route' => 'modification', 'fields' => ['id']],
            '/api/v1/coverage' => ['route' => 'coverage', 'fields' => []],
        ];
    }

    /** @return list<array{method:string,path:string}> */
    public static function endpoints(): array
    {
        $endpoints = [];
        foreach (array_keys(self::registry()) as $path) {
            $endpoints[] = ['method' => 'GET', 'path' => $path];
        }

        return $endpoints;
    }

    /** @return array<string, string> */
    public static function descriptions(): array
    {
        return [
            '/api/v1/brands' => 'ბრენდების სიის მიღება',
            '/api/v1/brands/{slug}/models' => 'არჩეული ბრენდის მოდელები',
            '/api/v1/models/{id}/generations' => 'არჩეული მოდელის თაობები',
            '/api/v1/generations/{id}/modifications' => 'არჩეული თაობის მოდიფიკაციები',
            '/api/v1/modifications/{id}' => 'კონკრეტული მოდიფიკაციის დეტალები',
            '/api/v1/coverage' => 'კატალოგის დაფარვის სტატისტიკა',
        ];
    }

    /** @return list<array{path:string, description:string, fields:list<string>}> */
    public static function clientOps(): array
    {
        $ops = [];
        foreach (self::registry() as $path => $operation) {
            $ops[] = [
                'path' => $path,
                'description' => self::descriptions()[$path],
                'fields' => $operation['fields'],
            ];
        }

        return $ops;
    }

    public static function catalogEnabled(): bool
    {
        return self::flag('CARAPI_CATALOG_ENABLED');
    }

    public static function operationEnabled(string $route): bool
    {
        if (!self::catalogEnabled()) {
            return false;
        }
        if ($route === 'modifications') {
            return self::flag('CARAPI_MODIFICATIONS_ENABLED');
        }
        if ($route === 'modification') {
            return self::flag('CARAPI_MODIFICATION_DETAILS_ENABLED');
        }

        return true;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok:true, operation:string, route:string, page:int, per_page:int, slug:?string, id:?string}|array{ok:false, message:string}
     */
    public static function parse(array $input): array
    {
        $operation = $input['operation'] ?? null;
        if (!is_string($operation) || !isset(self::registry()[$operation])) {
            return ['ok' => false, 'message' => 'ეს მარშრუტი არ არის დაშვებული.'];
        }
        $fields = self::registry()[$operation]['fields'];
        $allowed = array_merge(['_csrf', 'operation'], $fields);
        foreach (array_keys($input) as $key) {
            if (!in_array($key, $allowed, true)) {
                $message = in_array($key, ['page', 'per_page'], true)
                    ? 'პაგინაცია ამ მოთხოვნაზე არ გამოიყენება.'
                    : 'მოთხოვნაში დაუშვებელი ველია.';

                return ['ok' => false, 'message' => $message];
            }
        }
        $page = 1;
        $perPage = 50;
        if (in_array('page', $fields, true)) {
            $parsed = self::pageValue($input['page'] ?? null, 1, 1000000, 'page');
            if (!$parsed['ok']) {
                return ['ok' => false, 'message' => $parsed['message']];
            }
            $page = $parsed['value'];
        }
        if (in_array('per_page', $fields, true)) {
            $parsed = self::pageValue($input['per_page'] ?? null, 50, 100, 'per_page');
            if (!$parsed['ok']) {
                return ['ok' => false, 'message' => $parsed['message']];
            }
            $perPage = $parsed['value'];
        }
        $slug = null;
        if (in_array('slug', $fields, true)) {
            $slug = $input['slug'] ?? null;
            if (!is_string($slug) || $slug === '') {
                return ['ok' => false, 'message' => 'slug აუცილებელია. გამოიყენეთ ბრენდის slug API-ის პასუხიდან.'];
            }
            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
                return ['ok' => false, 'message' => 'slug არასწორია. გამოიყენეთ ბრენდის slug API-ის პასუხიდან.'];
            }
        }
        $id = null;
        if (in_array('id', $fields, true)) {
            $id = $input['id'] ?? null;
            $label = match (self::registry()[$operation]['route']) {
                'generations' => 'მოდელის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.',
                'modifications' => 'თაობის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.',
                default => 'მოდიფიკაციის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.',
            };
            if (!is_string($id) || preg_match('/^[1-9][0-9]{0,18}$/', $id) !== 1) {
                return ['ok' => false, 'message' => $label];
            }
        }

        return [
            'ok' => true,
            'operation' => $operation,
            'route' => self::registry()[$operation]['route'],
            'page' => $page,
            'per_page' => $perPage,
            'slug' => $slug,
            'id' => $id,
        ];
    }

    /**
     * @param array{operation:string, route:string, page:int, per_page:int, slug:?string, id:?string} $ready
     * @return array{status:int, route:string, body:array<string, mixed>}
     */
    public static function execute(array $ready): array
    {
        $repo = new CatalogRepository(CatalogConnection::pdo());
        $page = $ready['page'];
        $perPage = $ready['per_page'];
        $offset = ($page - 1) * $perPage;
        if ($ready['route'] === 'brands') {
            return self::listed('brands', $repo->brands($perPage, $offset), $page, $perPage);
        }
        if ($ready['route'] === 'models') {
            if (!$repo->brandExists((string) $ready['slug'])) {
                return self::missing('მითითებული ბრენდი ვერ მოიძებნა.');
            }

            return self::listed('models', $repo->models((string) $ready['slug'], $perPage, $offset), $page, $perPage);
        }
        if ($ready['route'] === 'generations') {
            if (!$repo->modelExists((string) $ready['id'])) {
                return self::missing('მითითებული მოდელი ვერ მოიძებნა.');
            }

            return self::listed('generations', $repo->generations((string) $ready['id'], $perPage, $offset), $page, $perPage);
        }
        if ($ready['route'] === 'modifications') {
            $result = $repo->modifications((string) $ready['id'], $perPage, $offset);
            if ($result === null) {
                return self::missing('მითითებული თაობა ვერ მოიძებნა.');
            }

            return self::listed('modifications', $result, $page, $perPage);
        }
        if ($ready['route'] === 'modification') {
            $row = $repo->modification((string) $ready['id']);
            if ($row === null) {
                return self::missing('მითითებული მოდიფიკაცია ვერ მოიძებნა.');
            }

            return ['status' => 200, 'route' => 'modification', 'body' => ['data' => $row]];
        }

        return ['status' => 200, 'route' => 'coverage', 'body' => $repo->coverage()];
    }

    /** @return array{ok:true,value:int}|array{ok:false,message:string} */
    public static function pageValue(mixed $value, int $default, int $max, string $name): array
    {
        if ($value === null || $value === '') {
            return ['ok' => true, 'value' => $default];
        }
        $hint = $name === 'per_page'
            ? 'პარამეტრი per_page არასწორია. მიუთითეთ მთელი რიცხვი 1-დან 100-მდე.'
            : 'პარამეტრი page არასწორია. მიუთითეთ დადებითი მთელი რიცხვი, არაუმეტეს 1000000.';
        if (is_array($value) || !is_string($value) || !preg_match('/^[1-9][0-9]*$/', $value)) {
            return ['ok' => false, 'message' => $hint];
        }
        $number = (int) $value;
        if ($number < 1 || $number > $max) {
            return ['ok' => false, 'message' => $hint];
        }

        return ['ok' => true, 'value' => $number];
    }

    /** @param array{rows:list<array<string, mixed>>, total:int} $result @return array{status:int, route:string, body:array<string, mixed>} */
    private static function listed(string $route, array $result, int $page, int $perPage): array
    {
        return [
            'status' => 200,
            'route' => $route,
            'body' => [
                'data' => $result['rows'],
                'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $result['total']],
            ],
        ];
    }

    /** @return array{status:int, route:string, body:array<string, mixed>} */
    private static function missing(string $message): array
    {
        return [
            'status' => 404,
            'route' => '',
            'body' => ['error' => ['code' => 'not_found', 'message' => $message]],
        ];
    }

    private static function flag(string $name): bool
    {
        $value = getenv($name);

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }
}
