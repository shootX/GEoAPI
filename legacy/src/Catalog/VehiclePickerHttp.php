<?php

declare(strict_types=1);

namespace Geoapi\Catalog;

use PDOException;

final class VehiclePickerHttp
{
    private const PER_MINUTE = 120;

    public static function matches(string $path): bool
    {
        $path = rawurldecode($path);

        return str_starts_with($path, '/api/picker')
            || preg_match('#^/api/models/[^/]+/photo$#', $path) === 1;
    }

    public static function dispatch(string $path): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET') {
            header('Allow: GET');
            self::json(405, ['error' => ['code' => 'method_not_allowed', 'message' => 'Use GET.']]);

            return;
        }
        $route = self::route(rawurldecode($path));
        if ($route === null || $route['kind'] === 'invalid') {
            if ($route === null) {
                self::json(404, ['error' => ['code' => 'not_found', 'message' => 'Not found.']]);
            }

            return;
        }
        if ($route['kind'] !== 'photo' && ! self::sameOrigin()) {
            self::json(403, ['error' => ['code' => 'forbidden', 'message' => 'Request is not allowed.']]);

            return;
        }
        $allowed = self::allowIp(self::clientIp());
        if ($allowed === null) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'Catalog is temporarily unavailable.']]);

            return;
        }
        if (! $allowed) {
            header('Retry-After: 60');
            self::json(429, ['error' => ['code' => 'rate_limited', 'message' => 'Too many requests.']]);

            return;
        }
        $page = null;
        if ($route['kind'] !== 'photo' && $route['kind'] !== 'modification') {
            $page = self::page();
            if ($page === null) {
                return;
            }
        }
        try {
            $repo = new VehiclePickerRepository(Connection::pdo());
            if ($route['kind'] === 'photo') {
                if (! self::embeddedImage()) {
                    self::json(403, ['error' => ['code' => 'forbidden', 'message' => 'Request is not allowed.']]);

                    return;
                }
                $generation = $_GET['generation'] ?? null;
                $photo = $repo->photo($route['id'], is_string($generation) ? $generation : null);
                if ($photo === null) {
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'No photo yet.']]);

                    return;
                }
                $marked = DemoWatermark::apply($photo['bytes']);
                if ($marked === null) {
                    self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'Catalog is temporarily unavailable.']]);

                    return;
                }
                header('Content-Type: '.$marked['mime']);
                header('Content-Length: '.strlen($marked['bytes']));
                header('Content-Disposition: inline');
                header('X-Content-Type-Options: nosniff');
                header('X-Robots-Tag: noindex, noimageindex, noarchive');
                header('Referrer-Policy: no-referrer');
                header('Cross-Origin-Resource-Policy: same-origin');
                header('Content-Security-Policy: default-src \'none\'');
                header('Cache-Control: private, no-store');
                echo $marked['bytes'];

                return;
            }
            $result = self::result($repo, $route, $page);
        } catch (UnavailableException|PDOException) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'Catalog is temporarily unavailable.']]);

            return;
        }
        if ($result === null) {
            return;
        }
        if ($route['kind'] === 'modification') {
            self::json(200, ['data' => $result]);

            return;
        }
        self::json(200, [
            'data' => $result['rows'],
            'meta' => [
                'page' => $page['page'],
                'per_page' => $page['per_page'],
                'total' => $result['total'],
            ],
        ]);
    }

    /**
     * @param  array{kind:string,id?:string,year?:int}  $route
     * @param  array{page:int,per_page:int,offset:int}|null  $page
     * @return array<string, mixed>|null
     */
    private static function result(VehiclePickerRepository $repo, array $route, ?array $page): ?array
    {
        if ($route['kind'] === 'brands') {
            $term = self::term();
            if ($term === null) {
                return null;
            }

            return $repo->brands($page['per_page'], $page['offset'], $term);
        }
        if ($route['kind'] === 'models') {
            $result = $repo->models($route['id'], $page['per_page'], $page['offset']);
            if ($result === null) {
                self::json(404, ['error' => ['code' => 'not_found', 'message' => 'Brand was not found.']]);
            }

            return $result;
        }
        if ($route['kind'] === 'years') {
            $choices = $repo->years($route['id']);
            if ($choices === null) {
                self::json(404, ['error' => ['code' => 'not_found', 'message' => 'Model was not found.']]);

                return null;
            }

            return [
                'rows' => array_slice($choices['rows'], $page['offset'], $page['per_page']),
                'total' => $choices['total'],
            ];
        }
        if ($route['kind'] === 'generations') {
            $result = $repo->generations($route['id'], $route['year'], $page['per_page'], $page['offset']);
            if ($result === null) {
                self::json(404, ['error' => ['code' => 'not_found', 'message' => 'Model was not found.']]);
            }

            return $result;
        }
        if ($route['kind'] === 'modifications') {
            $result = $repo->modifications($route['id'], $route['year'], $page['per_page'], $page['offset']);
            if ($result === null) {
                self::json(404, ['error' => ['code' => 'not_found', 'message' => 'Generation was not found.']]);
            }

            return $result;
        }
        $row = $repo->modification($route['id']);
        if ($row === null) {
            self::json(404, ['error' => ['code' => 'not_found', 'message' => 'Modification was not found.']]);
        }

        return $row;
    }

    /** @return array{kind:string,id?:string,year?:int}|null */
    private static function route(string $path): ?array
    {
        if ($path === '/api/picker/brands') {
            return ['kind' => 'brands'];
        }
        if (preg_match('#^/api/picker/brands/([1-9][0-9]{0,18})/models$#', $path, $match) === 1) {
            return ['kind' => 'models', 'id' => $match[1]];
        }
        if (preg_match('#^/api/picker/brands/[^/]+/models$#', $path) === 1) {
            return self::invalid('Brand id must be a positive integer.');
        }
        if (preg_match('#^/api/picker/models/([1-9][0-9]{0,18})/years$#', $path, $match) === 1) {
            return ['kind' => 'years', 'id' => $match[1]];
        }
        if (preg_match('#^/api/picker/models/[^/]+/years$#', $path) === 1) {
            return self::invalid('Model id must be a positive integer.');
        }
        if (preg_match('#^/api/picker/models/([1-9][0-9]{0,18})/generations$#', $path, $match) === 1) {
            $year = self::year();
            if ($year === null) {
                return ['kind' => 'invalid'];
            }

            return ['kind' => 'generations', 'id' => $match[1], 'year' => $year];
        }
        if (preg_match('#^/api/picker/models/[^/]+/generations$#', $path) === 1) {
            return self::invalid('Model id must be a positive integer.');
        }
        if (preg_match('#^/api/picker/generations/([1-9][0-9]{0,18})/modifications$#', $path, $match) === 1) {
            $year = self::year();
            if ($year === null) {
                return ['kind' => 'invalid'];
            }

            return ['kind' => 'modifications', 'id' => $match[1], 'year' => $year];
        }
        if (preg_match('#^/api/picker/generations/[^/]+/modifications$#', $path) === 1) {
            return self::invalid('Generation id must be a positive integer.');
        }
        if (preg_match('#^/api/picker/modifications/([1-9][0-9]{0,18})$#', $path, $match) === 1) {
            return ['kind' => 'modification', 'id' => $match[1]];
        }
        if (preg_match('#^/api/picker/modifications/[^/]+$#', $path) === 1) {
            return self::invalid('Modification id must be a positive integer.');
        }
        if (preg_match('#^/api/models/([1-9][0-9]{0,18})/photo$#', $path, $match) === 1) {
            return ['kind' => 'photo', 'id' => $match[1]];
        }
        if (preg_match('#^/api/models/[^/]+/photo$#', $path) === 1) {
            return self::invalid('Model id must be a positive integer.');
        }

        return null;
    }

    /** @return array{kind:string} */
    private static function invalid(string $message): array
    {
        self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => $message]]);

        return ['kind' => 'invalid'];
    }

    private static function term(): ?string
    {
        $value = $_GET['q'] ?? null;
        if ($value === null || $value === '') {
            return '';
        }
        if (! is_string($value) || strlen($value) > 80) {
            self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'q must be a string of at most 80 characters.']]);

            return null;
        }

        return trim($value);
    }

    private static function year(): ?int
    {
        $value = $_GET['year'] ?? null;
        if (! is_string($value) || preg_match('/^(18[8-9][0-9]|19[0-9]{2}|20[0-9]{2}|2100)$/', $value) !== 1) {
            self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'Year must be a four-digit year from 1880 to 2100.']]);

            return null;
        }

        return (int) $value;
    }

    /** @return array{page:int,per_page:int,offset:int}|null */
    private static function page(): ?array
    {
        $page = self::positive($_GET['page'] ?? null, 1, 1000000, 'page');
        if ($page === null) {
            return null;
        }
        $perPage = self::positive($_GET['per_page'] ?? null, 50, 100, 'per_page');
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
        if (! is_string($value) || preg_match('/^[1-9][0-9]*$/', $value) !== 1) {
            self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => self::pageError($name)]]);

            return null;
        }
        $number = (int) $value;
        if ($number < 1 || $number > $max) {
            self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => self::pageError($name)]]);

            return null;
        }

        return $number;
    }

    private static function pageError(string $name): string
    {
        return $name === 'per_page'
            ? 'per_page must be an integer from 1 to 100.'
            : 'page must be a positive integer up to 1000000.';
    }

    private static function embeddedImage(): bool
    {
        $dest = $_SERVER['HTTP_SEC_FETCH_DEST'] ?? '';
        $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';

        return $dest === 'image' && $site === 'same-origin';
    }

    private static function sameOrigin(): bool
    {
        $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';
        if ($site === 'same-origin') {
            return true;
        }
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (! is_string($host) || preg_match('/^[A-Za-z0-9.-]+(?::[0-9]{1,5})?$/', $host) !== 1) {
            return false;
        }
        $https = isset($_SERVER['HTTPS']) && is_string($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower($_SERVER['HTTPS']) !== 'off';
        $expected = ($https ? 'https://' : 'http://').strtolower($host);
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        return is_string($origin) && strtolower($origin) === $expected;
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

    /** @param array<string, mixed> $payload */
    private static function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
