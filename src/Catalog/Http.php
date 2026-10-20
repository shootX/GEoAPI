<?php

declare(strict_types=1);

namespace Carapi\Catalog;

use Carapi\Platform\UnavailableException as PlatformUnavailable;
use Carapi\Platform\UsageLimiter;
use PDOException;

final class Http
{
    public static function dispatch(string $path): void
    {
        if (!self::enabled()) {
            self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.']]);

            return;
        }

        if (self::modificationDetailPath($path) && !self::modificationDetailsEnabled()) {
            self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.']]);

            return;
        }

        try {
            $limiter = UsageLimiter::fromEnv();
            $now = UsageLimiter::now();
            $ipHit = $limiter->hitIp(UsageLimiter::clientIp(), $now);
        } catch (UnavailableException | PlatformUnavailable | PDOException) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

            return;
        }
        if ($ipHit['exceeded']) {
            self::limited('RATE_LIMIT_EXCEEDED', $ipHit['retry_after']);

            return;
        }

        try {
            $access = Access::allow();
        } catch (DeniedException $e) {
            if ($e->status === 401) {
                header('WWW-Authenticate: Bearer');
            }
            $message = $e->status === 403
                ? 'მოთხოვნა არ არის დაშვებული. მოთხოვნა არ აკმაყოფილებს გასაღების ან პროექტის წვდომის პირობებს.'
                : 'ავტორიზაცია ვერ შესრულდა. API გასაღები არ არის მითითებული, არასწორია ან ვეღარ გამოიყენება.';
            self::json($e->status, ['error' => ['code' => $e->status === 403 ? 'forbidden' : 'unauthorized', 'message' => $message]]);

            return;
        } catch (UnavailableException) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

            return;
        }

        try {
            $projectHit = $limiter->hitProject($access['project_id'], $now);
        } catch (UnavailableException | PlatformUnavailable | PDOException) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

            return;
        }
        if ($projectHit['missing']) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

            return;
        }
        if ($projectHit['exceeded']) {
            self::limited('RATE_LIMIT_EXCEEDED', max($ipHit['retry_after'], $projectHit['retry_after']));

            return;
        }
        $preview = $limiter->previewMonth($access['project_id'], $now);
        if ($preview === null) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

            return;
        }
        if ($preview['exhausted']) {
            self::limited('MONTHLY_QUOTA_EXCEEDED', max(1, $preview['reset_at'] - $now));

            return;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET') {
            header('Allow: GET');
            self::json(405, ['error' => ['code' => 'method_not_allowed', 'message' => 'ეს HTTP მეთოდი დაუშვებელია. გამოიყენეთ GET.']]);

            return;
        }

        try {
            $route = self::route($path);
            if ($route === null) {
                self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.']]);

                return;
            }
            if ($route['kind'] === 'invalid') {
                return;
            }
            if ($route['kind'] === 'modification') {
                $row = (new CatalogRepository(Connection::pdo()))->modification((string) $route['id']);
                if ($row === null) {
                    Access::touch($access['key_id']);
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მოდიფიკაცია ვერ მოიძებნა.']]);

                    return;
                }
                self::sendCounted($limiter, $access, 'modification', ['data' => $row], $now);

                return;
            }
            if ($route['kind'] === 'coverage') {
                $payload = (new CatalogRepository(Connection::pdo()))->coverage();
                self::sendCounted($limiter, $access, 'coverage', $payload, $now);

                return;
            }
            $page = self::pageParams();
            if ($page === null) {
                return;
            }
            $repo = new CatalogRepository(Connection::pdo());
            if ($route['kind'] === 'brands') {
                $result = $repo->brands($page['per_page'], $page['offset']);
            } elseif ($route['kind'] === 'models') {
                if (!$repo->brandExists($route['slug'])) {
                    Access::touch($access['key_id']);
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული ბრენდი ვერ მოიძებნა.']]);

                    return;
                }
                $result = $repo->models($route['slug'], $page['per_page'], $page['offset']);
            } elseif ($route['kind'] === 'modifications') {
                $result = $repo->modifications($route['id'], $page['per_page'], $page['offset']);
                if ($result === null) {
                    Access::touch($access['key_id']);
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული თაობა ვერ მოიძებნა.']]);

                    return;
                }
            } else {
                if (!$repo->modelExists($route['id'])) {
                    Access::touch($access['key_id']);
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მოდელი ვერ მოიძებნა.']]);

                    return;
                }
                $result = $repo->generations($route['id'], $page['per_page'], $page['offset']);
            }
            self::sendCounted($limiter, $access, (string) $route['kind'], [
                'data' => $result['rows'],
                'meta' => [
                    'page' => $page['page'],
                    'per_page' => $page['per_page'],
                    'total' => $result['total'],
                ],
            ], $now);
        } catch (UnavailableException) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);
        } catch (PDOException) {
            error_log('[catalog] query failed');
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);
        }
    }

    public static function readForProject(int $projectId, string $kind, ?string $slug, ?string $id): void
    {
        try {
            $limiter = UsageLimiter::fromEnv();
            $now = UsageLimiter::now();
            $ipHit = $limiter->hitIp(UsageLimiter::clientIp(), $now);
            if ($ipHit['exceeded']) {
                self::limited('RATE_LIMIT_EXCEEDED', $ipHit['retry_after']);

                return;
            }
            $projectHit = $limiter->hitProject($projectId, $now);
            if ($projectHit['missing']) {
                self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

                return;
            }
            if ($projectHit['exceeded']) {
                self::limited('RATE_LIMIT_EXCEEDED', max($ipHit['retry_after'], $projectHit['retry_after']));

                return;
            }
            $preview = $limiter->previewMonth($projectId, $now);
            if ($preview === null || $preview['exhausted']) {
                if ($preview === null) {
                    self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

                    return;
                }
                self::limited('MONTHLY_QUOTA_EXCEEDED', max(1, $preview['reset_at'] - $now));

                return;
            }
            $page = self::pageParams();
            if ($page === null) {
                return;
            }
            $repo = new CatalogRepository(Connection::pdo());
            if ($kind === 'widget:brands') {
                $result = $repo->brands($page['per_page'], $page['offset']);
            } elseif ($kind === 'widget:models') {
                if (!$repo->brandExists((string) $slug)) {
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული ბრენდი ვერ მოიძებნა.']]);

                    return;
                }
                $result = $repo->models((string) $slug, $page['per_page'], $page['offset']);
            } elseif ($kind === 'widget:years') {
                if (!$repo->modelExists((string) $id)) {
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მოდელი ვერ მოიძებნა.']]);

                    return;
                }
                $choices = $repo->yearChoices((string) $id);
                $result = [
                    'rows' => array_slice($choices['rows'], $page['offset'], $page['per_page']),
                    'total' => $choices['total'],
                ];
            } else {
                if (!$repo->modelExists((string) $id)) {
                    self::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მოდელი ვერ მოიძებნა.']]);

                    return;
                }
                $result = $repo->generations((string) $id, $page['per_page'], $page['offset']);
            }
            self::sendCounted($limiter, ['key_id' => 0, 'project_id' => $projectId], $kind, [
                'data' => $result['rows'],
                'meta' => ['page' => $page['page'], 'per_page' => $page['per_page'], 'total' => $result['total']],
            ], $now);
        } catch (UnavailableException | PlatformUnavailable | PDOException) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);
        }
    }

    /** @param array{key_id:int,project_id:int} $access @param array<string, mixed> $payload */
    private static function sendCounted(UsageLimiter $limiter, array $access, string $route, array $payload, int $now): void
    {
        try {
            $saved = $limiter->commitSuccess($access['project_id'], $route, $now);
            if ($access['key_id'] > 0) {
                Access::touch($access['key_id']);
            }
        } catch (UnavailableException | PlatformUnavailable | PDOException) {
            self::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

            return;
        }
        if (!$saved['ok']) {
            self::limited('MONTHLY_QUOTA_EXCEEDED', max(1, $saved['reset_at'] - $now));

            return;
        }
        header('X-Quota-Limit: ' . $saved['limit']);
        header('X-Quota-Remaining: ' . $saved['remaining']);
        header('X-Quota-Reset: ' . $saved['reset_at']);
        self::json(200, $payload);
    }

    private static function limited(string $code, int $retryAfter): void
    {
        header('Retry-After: ' . max(1, $retryAfter));
        self::json(429, ['error' => ['code' => $code, 'message' => 'მოთხოვნების ლიმიტი მიღწეულია.']]);
    }

    public static function enabled(): bool
    {
        $value = getenv('CARAPI_CATALOG_ENABLED');

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    private static function modificationsEnabled(): bool
    {
        $value = getenv('CARAPI_MODIFICATIONS_ENABLED');

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    private static function modificationDetailsEnabled(): bool
    {
        $value = getenv('CARAPI_MODIFICATION_DETAILS_ENABLED');

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    private static function modificationDetailPath(string $path): bool
    {
        return preg_match('#^/api/v1/modifications/[^/]+$#', rawurldecode($path)) === 1;
    }

    /** @return array{kind:string, slug?:string, id?:string}|null */
    private static function route(string $path): ?array
    {
        $path = rawurldecode($path);
        if ($path === '/api/v1/brands') {
            return ['kind' => 'brands'];
        }
        if ($path === '/api/v1/coverage') {
            return ['kind' => 'coverage'];
        }
        if (preg_match('#^/api/v1/brands/([^/]+)/models$#u', $path, $match) === 1) {
            return ['kind' => 'models', 'slug' => $match[1]];
        }
        if (preg_match('#^/api/v1/modifications/([^/]+)$#', $path, $match) === 1) {
            if (!self::modificationDetailsEnabled()) {
                return null;
            }
            if (!preg_match('/^[1-9][0-9]{0,18}$/', $match[1])) {
                self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'მოდიფიკაციის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.']]);

                return ['kind' => 'invalid'];
            }

            return ['kind' => 'modification', 'id' => $match[1]];
        }
        if (preg_match('#^/api/v1/generations/([^/]+)/modifications$#', $path, $match) === 1) {
            if (!self::modificationsEnabled()) {
                return null;
            }
            if (!preg_match('/^[1-9][0-9]{0,18}$/', $match[1])) {
                self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'თაობის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.']]);

                return ['kind' => 'invalid'];
            }

            return ['kind' => 'modifications', 'id' => $match[1]];
        }
        if (preg_match('#^/api/v1/models/([^/]+)/generations$#', $path, $match) === 1) {
            if (!preg_match('/^[1-9][0-9]{0,18}$/', $match[1])) {
                self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'მოდელის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.']]);

                return ['kind' => 'invalid'];
            }

            return ['kind' => 'generations', 'id' => $match[1]];
        }

        return null;
    }

    /** @return array{page:int, per_page:int, offset:int}|null */
    private static function pageParams(): ?array
    {
        $page = self::positive($_GET['page'] ?? null, 1, 1000000, 'page');
        if ($page === null) {
            return null;
        }
        $perPage = self::positive($_GET['per_page'] ?? null, 50, 100, 'per_page');
        if ($perPage === null) {
            return null;
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => ($page - 1) * $perPage,
        ];
    }

    private static function positive(mixed $value, int $default, int $max, string $name): ?int
    {
        if ($value === null) {
            return $default;
        }
        if (is_array($value) || !is_string($value) || !preg_match('/^[1-9][0-9]*$/', $value)) {
            $hint = $name === 'per_page'
                ? 'პარამეტრი per_page არასწორია. მიუთითეთ მთელი რიცხვი 1-დან 100-მდე.'
                : 'პარამეტრი page არასწორია. მიუთითეთ დადებითი მთელი რიცხვი, არაუმეტეს 1000000.';
            self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => $hint]]);

            return null;
        }
        $number = (int) $value;
        if ($number < 1 || $number > $max) {
            $hint = $name === 'per_page'
                ? 'პარამეტრი per_page არასწორია. მიუთითეთ მთელი რიცხვი 1-დან 100-მდე.'
                : 'პარამეტრი page არასწორია. მიუთითეთ დადებითი მთელი რიცხვი, არაუმეტეს 1000000.';
            self::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => $hint]]);

            return null;
        }

        return $number;
    }

    /** @param array<string, mixed> $payload */
    public static function json(int $status, array $payload): void
    {
        \Carapi\Widget\Cors::apply();
        if (isset($payload['error']['message']) && is_string($payload['error']['message'])) {
            $payload['error']['message'] = ApiMessages::localize($payload['error']['message']);
        }
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
