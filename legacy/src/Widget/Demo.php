<?php

declare(strict_types=1);

namespace Geoapi\Widget;

use Geoapi\Catalog\CatalogRepository;
use Geoapi\Catalog\Connection as CatalogConnection;
use Geoapi\Catalog\Http as CatalogHttp;
use Geoapi\Catalog\PickerSelection;
use Geoapi\Catalog\UnavailableException;
use Geoapi\Platform\Origins;
use PDOException;

final class Demo
{
    private const PER_MINUTE = 120;

    public static function dispatch(string $path): void
    {
        Cors::$origin = null;
        $route = self::route($path);
        if ($route === null || $route['kind'] === 'invalid') {
            if ($route === null) {
                CatalogHttp::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.']]);
            }

            return;
        }
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'POST') {
            header('Allow: POST');
            CatalogHttp::json(405, ['error' => ['code' => 'method_not_allowed', 'message' => 'ეს HTTP მეთოდი დაუშვებელია. გამოიყენეთ POST.']]);

            return;
        }
        if (! self::sameOrigin()) {
            CatalogHttp::json(403, ['error' => ['code' => 'forbidden', 'message' => 'მოთხოვნა არ არის დაშვებული.']]);

            return;
        }
        $allowed = self::allowIp(self::clientIp());
        if ($allowed === null) {
            CatalogHttp::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

            return;
        }
        if (! $allowed) {
            header('Retry-After: 60');
            CatalogHttp::json(429, ['error' => ['code' => 'RATE_LIMIT_EXCEEDED', 'message' => 'მოთხოვნების ლიმიტი მიღწეულია.']]);

            return;
        }
        $body = json_decode(file_get_contents('php://input') ?: '', true);
        $body = is_array($body) ? $body : [];
        $page = self::page($body);
        if ($page === null) {
            return;
        }
        try {
            $repo = new CatalogRepository(CatalogConnection::pdo());
            if ($route['kind'] === 'brands') {
                $result = $repo->brands($page['per_page'], $page['offset']);
            } elseif ($route['kind'] === 'models') {
                if (! $repo->brandExists((string) $route['slug'])) {
                    CatalogHttp::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული ბრენდი ვერ მოიძებნა.']]);

                    return;
                }
                $result = $repo->models((string) $route['slug'], $page['per_page'], $page['offset']);
            } elseif ($route['kind'] === 'modifications') {
                $year = CatalogHttp::yearParam($body['year'] ?? null, true);
                if ($year === false) {
                    return;
                }
                $choices = PickerSelection::modifications(CatalogConnection::pdo(), (string) $route['id'], $year);
                if ($choices === null) {
                    CatalogHttp::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული თაობა ვერ მოიძებნა.']]);

                    return;
                }
                $result = [
                    'rows' => array_slice($choices['rows'], $page['offset'], $page['per_page']),
                    'total' => $choices['total'],
                ];
            } else {
                if (! $repo->modelExists((string) $route['id'])) {
                    CatalogHttp::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მოდელი ვერ მოიძებნა.']]);

                    return;
                }
                if ($route['kind'] === 'years') {
                    $choices = $repo->yearChoices((string) $route['id']);
                    $result = [
                        'rows' => array_slice($choices['rows'], $page['offset'], $page['per_page']),
                        'total' => $choices['total'],
                    ];
                } else {
                    $year = CatalogHttp::yearParam($body['year'] ?? null, false);
                    if ($year === false) {
                        return;
                    }
                    if ($year === null) {
                        $result = $repo->generations((string) $route['id'], $page['per_page'], $page['offset']);
                    } else {
                        $choices = PickerSelection::generations(CatalogConnection::pdo(), (string) $route['id'], $year);
                        if ($choices === null) {
                            CatalogHttp::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მოდელი ვერ მოიძებნა.']]);

                            return;
                        }
                        $result = [
                            'rows' => array_slice($choices['rows'], $page['offset'], $page['per_page']),
                            'total' => $choices['total'],
                        ];
                    }
                }
            }
        } catch (UnavailableException|PDOException) {
            CatalogHttp::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

            return;
        }
        CatalogHttp::json(200, [
            'data' => $result['rows'],
            'meta' => [
                'page' => $page['page'],
                'per_page' => $page['per_page'],
                'total' => $result['total'],
            ],
        ]);
    }

    /** @return array{kind:string,slug?:string,id?:string}|null */
    private static function route(string $path): ?array
    {
        $path = rawurldecode($path);
        if ($path === '/widget/v1/demo/brands') {
            return ['kind' => 'brands'];
        }
        if (preg_match('#^/widget/v1/demo/brands/([^/]+)/models$#u', $path, $match) === 1) {
            return ['kind' => 'models', 'slug' => $match[1]];
        }
        if (preg_match('#^/widget/v1/demo/models/([1-9][0-9]{0,18})/years$#', $path, $match) === 1) {
            return ['kind' => 'years', 'id' => $match[1]];
        }
        if (preg_match('#^/widget/v1/demo/models/([^/]+)/years$#', $path) === 1) {
            CatalogHttp::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'მოდელის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.']]);

            return ['kind' => 'invalid'];
        }
        if (preg_match('#^/widget/v1/demo/models/([1-9][0-9]{0,18})/generations$#', $path, $match) === 1) {
            return ['kind' => 'generations', 'id' => $match[1]];
        }
        if (preg_match('#^/widget/v1/demo/generations/([1-9][0-9]{0,18})/modifications$#', $path, $match) === 1) {
            return ['kind' => 'modifications', 'id' => $match[1]];
        }
        if (preg_match('#^/widget/v1/demo/generations/([^/]+)/modifications$#', $path) === 1) {
            CatalogHttp::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'თაობის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.']]);

            return ['kind' => 'invalid'];
        }
        if (preg_match('#^/widget/v1/demo/models/([^/]+)/generations$#', $path) === 1) {
            CatalogHttp::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'მოდელის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.']]);

            return ['kind' => 'invalid'];
        }

        return null;
    }

    private static function sameOrigin(): bool
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (! is_string($host) || preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host) !== 1) {
            return false;
        }
        $https = isset($_SERVER['HTTPS']) && is_string($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off';
        $expected = ($https ? 'https://' : 'http://').strtolower($host);
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        return is_string($origin) && Origins::normalize($origin) === $expected;
    }

    private static function clientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        return is_string($ip) && $ip !== '' ? $ip : '0';
    }

    private static function allowIp(string $ip): ?bool
    {
        $dir = dirname(__DIR__, 2).'/cache/demo-limit';
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            return null;
        }
        $handle = fopen($dir.'/'.hash('sha256', $ip), 'c+');
        if ($handle === false) {
            return null;
        }
        try {
            if (! flock($handle, LOCK_EX)) {
                return null;
            }
            $raw = stream_get_contents($handle);
            $now = time();
            $stamps = [];
            if (is_string($raw) && $raw !== '') {
                foreach (explode(',', $raw) as $part) {
                    if (ctype_digit($part) && (int) $part > $now - 60) {
                        $stamps[] = (int) $part;
                    }
                }
            }
            if (count($stamps) >= self::PER_MINUTE) {
                return false;
            }
            $stamps[] = $now;
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, implode(',', $stamps));
            fflush($handle);

            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /** @param array<string, mixed> $body @return array{page:int,per_page:int,offset:int}|null */
    private static function page(array $body): ?array
    {
        $page = self::positive($body['page'] ?? null, 1, 1000000, 'page');
        if ($page === null) {
            return null;
        }
        $perPage = self::positive($body['per_page'] ?? null, 50, 100, 'per_page');
        if ($perPage === null) {
            return null;
        }

        return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage];
    }

    private static function positive(mixed $value, int $default, int $max, string $name): ?int
    {
        if ($value === null) {
            return $default;
        }
        if (is_int($value)) {
            $number = $value;
        } elseif (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            $number = (int) $value;
        } else {
            CatalogHttp::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => self::pageError($name)]]);

            return null;
        }
        if ($number < 1 || $number > $max) {
            CatalogHttp::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => self::pageError($name)]]);

            return null;
        }

        return $number;
    }

    private static function pageError(string $name): string
    {
        return $name === 'per_page'
            ? 'პარამეტრი per_page არასწორია. მიუთითეთ მთელი რიცხვი 1-დან 100-მდე.'
            : 'პარამეტრი page არასწორია. მიუთითეთ დადებითი მთელი რიცხვი, არაუმეტეს 1000000.';
    }
}
