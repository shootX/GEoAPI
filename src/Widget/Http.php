<?php

declare(strict_types=1);

namespace Carapi\Widget;

use Carapi\Catalog\CatalogRepository;
use Carapi\Catalog\Connection as CatalogConnection;
use Carapi\Catalog\Http as CatalogHttp;
use Carapi\Platform\Connection as PlatformConnection;
use Carapi\Platform\Origins;
use Carapi\Platform\UnavailableException;
use Carapi\Platform\UsageLimiter;
use Carapi\Platform\WidgetRepository;
use PDOException;

final class Http
{
    public static function enabled(): bool
    {
        return self::flag('CARAPI_WIDGET_ENABLED') && self::flag('CARAPI_CATALOG_ENABLED') && self::flag('CARAPI_PLATFORM_ENABLED');
    }

    public static function dispatch(string $path): void
    {
        if ($path === '/widget/v1/demo' || str_starts_with($path, '/widget/v1/demo/')) {
            Demo::dispatch($path);

            return;
        }
        if (!self::enabled()) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => ['code' => 'not_found', 'message' => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.']], JSON_UNESCAPED_UNICODE);

            return;
        }
        $route = self::route($path);
        if ($route === null || $route['kind'] === 'invalid') {
            if ($route === null) {
                CatalogHttp::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.']]);
            }

            return;
        }
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        try {
            $widget = (new WidgetRepository(PlatformConnection::pdo()))->findByKey($route['key']);
        } catch (UnavailableException | PDOException) {
            CatalogHttp::json(503, ['error' => ['code' => 'unavailable', 'message' => 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.']]);

            return;
        }
        if ($widget === null) {
            CatalogHttp::json(404, ['error' => ['code' => 'not_found', 'message' => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.']]);

            return;
        }
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if (!is_string($origin) || !Origins::matches($origin, $widget['allowed_origins'])) {
            CatalogHttp::json(403, ['error' => ['code' => 'forbidden', 'message' => 'მოთხოვნა არ არის დაშვებული. შეამოწმეთ საჯარო API გასაღები, დაშვებული origin და პროექტის მდგომარეობა.']]);

            return;
        }
        Cors::$origin = Origins::normalize($origin);
        if ($method === 'OPTIONS') {
            Cors::preflight((string) Cors::$origin);

            return;
        }
        if ($method !== 'POST') {
            header('Allow: POST, OPTIONS');
            CatalogHttp::json(405, ['error' => ['code' => 'method_not_allowed', 'message' => 'ეს HTTP მეთოდი დაუშვებელია. გამოიყენეთ POST.']]);

            return;
        }
        $open = $widget['enabled']
            && $widget['project_status'] === 'active'
            && $widget['user_status'] === 'active'
            && $widget['email_verified_at'] !== null;
        if (!$open) {
            CatalogHttp::json(403, ['error' => ['code' => 'forbidden', 'message' => 'მოთხოვნა არ არის დაშვებული. შეამოწმეთ საჯარო API გასაღები, დაშვებული origin და პროექტის მდგომარეობა.']]);

            return;
        }
        $body = json_decode(file_get_contents('php://input') ?: '', true);
        $body = is_array($body) ? $body : [];
        $_GET['page'] = isset($body['page']) ? (string) $body['page'] : null;
        $_GET['per_page'] = isset($body['per_page']) ? (string) $body['per_page'] : null;
        CatalogHttp::readForProject((int) $widget['project_id'], $route['kind'], $route['slug'] ?? null, $route['id'] ?? null);
    }

    /** @return array{key:string,kind:string,slug?:string,id?:string}|null */
    private static function route(string $path): ?array
    {
        $path = rawurldecode($path);
        if (preg_match('#^/widget/v1/(carapi_pk_[0-9a-f]{32})/brands$#', $path, $m) === 1) {
            return ['key' => $m[1], 'kind' => 'widget:brands'];
        }
        if (preg_match('#^/widget/v1/(carapi_pk_[0-9a-f]{32})/brands/([^/]+)/models$#u', $path, $m) === 1) {
            return ['key' => $m[1], 'kind' => 'widget:models', 'slug' => $m[2]];
        }
        if (preg_match('#^/widget/v1/(carapi_pk_[0-9a-f]{32})/models/([1-9][0-9]{0,18})/years$#', $path, $m) === 1) {
            return ['key' => $m[1], 'kind' => 'widget:years', 'id' => $m[2]];
        }
        if (preg_match('#^/widget/v1/(carapi_pk_[0-9a-f]{32})/models/([^/]+)/years$#', $path, $m) === 1) {
            CatalogHttp::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'მოდელის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.']]);

            return ['key' => $m[1], 'kind' => 'invalid'];
        }
        if (preg_match('#^/widget/v1/(carapi_pk_[0-9a-f]{32})/models/([1-9][0-9]{0,18})/generations$#', $path, $m) === 1) {
            return ['key' => $m[1], 'kind' => 'widget:generations', 'id' => $m[2]];
        }
        if (preg_match('#^/widget/v1/(carapi_pk_[0-9a-f]{32})/models/([^/]+)/generations$#', $path, $m) === 1) {
            CatalogHttp::json(422, ['error' => ['code' => 'invalid_parameter', 'message' => 'მოდელის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.']]);

            return ['key' => $m[1], 'kind' => 'invalid'];
        }

        return null;
    }

    private static function flag(string $name): bool
    {
        $value = getenv($name);

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }
}
