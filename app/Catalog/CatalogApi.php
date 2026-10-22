<?php

declare(strict_types=1);

namespace App\Catalog;

use App\Access\LegacyGate;
use Carapi\Catalog\Access;
use Carapi\Catalog\Connection;
use Carapi\Catalog\DeniedException;
use Carapi\Catalog\UnavailableException;
use Carapi\Platform\UnavailableException as PlatformUnavailable;
use Carapi\Platform\UsageLimiter;
use PDOException;

/**
 * Public /api/v1 response builder. It does not write HTTP headers.
 */
final class CatalogApi
{
    /**
     * @return array{status:int, headers:array<string, string>, body:array<string, mixed>}
     */
    public function handle(string $path): array
    {
        if (! $this->enabled('CARAPI_CATALOG_ENABLED')) {
            return $this->error(404, 'not_found', 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.');
        }
        if ($this->modificationDetail($path) && ! $this->enabled('CARAPI_MODIFICATION_DETAILS_ENABLED')) {
            return $this->error(404, 'not_found', 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.');
        }

        try {
            $limiter = UsageLimiter::fromEnv();
            $now = UsageLimiter::now();
            $ipHit = $limiter->hitIp(UsageLimiter::clientIp(), $now);
        } catch (UnavailableException|PlatformUnavailable|PDOException) {
            return $this->unavailable();
        }
        if ($ipHit['exceeded']) {
            return $this->limited('RATE_LIMIT_EXCEEDED', $ipHit['retry_after'], UsageLimiter::ipLimit(), 0, $now + $ipHit['retry_after']);
        }

        try {
            $access = Access::allow();
        } catch (DeniedException $e) {
            $headers = $e->status === 401 ? ['WWW-Authenticate' => 'Bearer'] : [];
            $message = $e->status === 403
                ? 'მოთხოვნა არ არის დაშვებული. მოთხოვნა არ აკმაყოფილებს გასაღების ან პროექტის წვდომის პირობებს.'
                : 'ავტორიზაცია ვერ შესრულდა. API გასაღები არ არის მითითებული, არასწორია ან ვეღარ გამოიყენება.';

            return $this->error($e->status, $e->status === 403 ? 'forbidden' : 'unauthorized', $message, $headers);
        } catch (UnavailableException|PlatformUnavailable|PDOException) {
            return $this->unavailable();
        }

        if (class_exists(LegacyGate::class)) {
            $denial = LegacyGate::catalog($access['project_id']);
            if ($denial !== null) {
                return ['status' => $denial['status'], 'headers' => [], 'body' => $denial['body']];
            }
        }

        // After auth (a keyless POST stays 401, as documented) but before the project counter is hit.
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET') {
            return $this->error(405, 'method_not_allowed', 'ეს HTTP მეთოდი დაუშვებელია. გამოიყენეთ GET.', ['Allow' => 'GET']);
        }

        try {
            $projectHit = $limiter->hitProject($access['project_id'], $now);
            $preview = $projectHit['missing'] ? null : $limiter->previewMonth($access['project_id'], $now);
            $day = $projectHit['missing'] ? null : $limiter->previewDay($access['project_id'], $now);
        } catch (UnavailableException|PlatformUnavailable|PDOException) {
            return $this->unavailable();
        }
        if ($projectHit['missing'] || $preview === null || $day === null) {
            return $this->unavailable();
        }
        if ($projectHit['exceeded']) {
            return $this->limited('RATE_LIMIT_EXCEEDED', max(1, $projectHit['retry_after']), $projectHit['limit'], 0, $projectHit['reset_at']);
        }
        if (! empty($preview['cap'])) {
            return $this->limited('PROJECT_CAP_EXCEEDED', max(1, $preview['reset_at'] - $now), $preview['limit'], 0, $preview['reset_at']);
        }
        if (empty($preview['unlimited']) && $preview['exhausted']) {
            return $this->limited('MONTHLY_QUOTA_EXCEEDED', max(1, $preview['reset_at'] - $now), $preview['limit'], 0, $preview['reset_at']);
        }
        if ($day['enforced'] && $day['exhausted']) {
            return $this->limited('DAILY_QUOTA_EXCEEDED', max(1, $day['reset_at'] - $now), $day['limit'], 0, $day['reset_at']);
        }


        $route = $this->route($path);
        if ($route === null) {
            return $this->error(404, 'not_found', 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.');
        }
        if ($route['kind'] === 'invalid') {
            return $route['response'];
        }

        try {
            $catalog = new PublicCatalog(Connection::pdo());
            $result = $this->query($catalog, $route);
        } catch (UnavailableException|PDOException) {
            error_log('[catalog] query failed');

            return $this->unavailable();
        }
        if (isset($result['error'])) {
            if (($result['error']['status'] ?? 500) === 404 && $access['key_id'] > 0) {
                try {
                    Access::touch($access['key_id']);
                } catch (UnavailableException|PlatformUnavailable|PDOException) {
                    return $this->unavailable();
                }
            }

            return $result['error'];
        }

        try {
            $saved = $limiter->commitSuccess($access['project_id'], (string) $route['kind'], $now);
            if ($access['key_id'] > 0) {
                Access::touch($access['key_id']);
            }
        } catch (UnavailableException|PlatformUnavailable|PDOException) {
            return $this->unavailable();
        }
        if (! $saved['ok']) {
            if (($saved['reason'] ?? 'month') === 'day') {
                return $this->limited('DAILY_QUOTA_EXCEEDED', max(1, $saved['reset_at'] - $now), $saved['limit'], 0, $saved['reset_at']);
            }
            if (($saved['reason'] ?? '') === 'cap') {
                return $this->limited('PROJECT_CAP_EXCEEDED', max(1, $saved['reset_at'] - $now), $saved['limit'], 0, $saved['reset_at']);
            }

            return $this->limited('MONTHLY_QUOTA_EXCEEDED', max(1, $saved['reset_at'] - $now), $saved['limit'], 0, $saved['reset_at']);
        }

        $quotaLimit = ! empty($saved['unlimited']) ? 'unlimited' : (string) $saved['limit'];
        $quotaRemaining = ! empty($saved['unlimited']) ? 'unlimited' : (string) $saved['remaining'];

        return [
            'status' => 200,
            'headers' => [
                'X-Quota-Limit' => $quotaLimit,
                'X-Quota-Remaining' => $quotaRemaining,
                'X-Quota-Reset' => (string) $saved['reset_at'],
                'X-RateLimit-Limit' => (string) $projectHit['limit'],
                'X-RateLimit-Remaining' => (string) max(0, $projectHit['limit'] - $projectHit['count']),
                'X-RateLimit-Reset' => (string) $projectHit['reset_at'],
            ],
            'body' => $result['body'],
        ];
    }

    /**
     * @param  array{kind:string, slug?:string, id?:string}  $route
     * @return array{body:array<string, mixed>}|array{error:array{status:int, headers:array<string, string>, body:array<string, mixed>}}
     */
    private function query(PublicCatalog $catalog, array $route): array
    {
        $kind = $route['kind'];
        if ($kind === 'coverage') {
            $parsed = ListQuery::parse($_GET, [], []);
            if ($parsed->fails() || $parsed->q !== null || $parsed->sort !== null || $parsed->modifiedAfter !== null || $parsed->filters !== []) {
                return ['error' => $this->error(422, 'invalid_parameter', 'ფილტრი ამ მისამართზე არ გამოიყენება.')];
            }

            return ['body' => $catalog->coverage()];
        }
        if ($kind === 'modification_specs') {
            $row = $catalog->modificationSpecs((string) $route['id']);
            if ($row === null) {
                return ['error' => $this->error(404, 'not_found', 'მითითებული მოდიფიკაცია ვერ მოიძებნა.')];
            }

            return ['body' => ['data' => $row]];
        }
        if ($kind === 'modification_equipment') {
            $row = $catalog->modificationEquipment((string) $route['id']);
            if ($row === null) {
                return ['error' => $this->error(404, 'not_found', 'მითითებული მოდიფიკაცია ვერ მოიძებნა.')];
            }

            return ['body' => ['data' => $row]];
        }
        if ($kind === 'modification') {
            $row = $catalog->modification((string) $route['id']);
            if ($row === null) {
                return ['error' => $this->error(404, 'not_found', 'მითითებული მოდიფიკაცია ვერ მოიძებნა.')];
            }

            return ['body' => ['data' => $row]];
        }

        $parsed = ListQuery::parse($_GET, self::sortAllowlist($kind), self::filterAllowlist($kind));
        if ($parsed->fails()) {
            return ['error' => $this->error(422, 'invalid_parameter', (string) $parsed->error)];
        }
        if ($kind === 'years' && $parsed->q !== null) {
            return ['error' => $this->error(422, 'invalid_parameter', 'ფილტრი q ამ მისამართზე არ გამოიყენება.')];
        }
        if (in_array($kind, ['bodies', 'years'], true) && $parsed->modifiedAfter !== null) {
            return ['error' => $this->error(422, 'invalid_parameter', 'ფილტრი modified_after ამ მისამართზე არ გამოიყენება.')];
        }
        if ($parsed->sort === 'modified' && ! $catalog->hasUpdatedAt($this->modifiedTable($kind))) {
            return ['error' => $this->error(422, 'invalid_parameter', 'პარამეტრი sort არასწორია.')];
        }
        if ($parsed->modifiedAfter !== null && ! $catalog->hasUpdatedAt($this->modifiedTable($kind))) {
            return ['error' => $this->error(422, 'invalid_parameter', 'პარამეტრი modified_after არასწორია. გამოიყენეთ UTC თარიღი, მაგალითად 2026-01-01T00:00:00Z.')];
        }

        $found = match ($kind) {
            'brands' => $catalog->brands($parsed),
            'models' => $catalog->models((string) $route['slug'], $parsed),
            'generations' => $catalog->generations((string) $route['id'], $parsed),
            'modifications' => $catalog->modificationsForGeneration((string) $route['id'], $parsed),
            'modification_search' => $catalog->searchModifications($parsed),
            'years' => $catalog->years((string) $route['id'], $parsed),
            'engines' => $catalog->engines($parsed),
            'bodies' => $catalog->bodies($parsed),
            'fuel' => $catalog->fuel($parsed),
            'colors' => $catalog->colors($parsed),
            default => null,
        };
        if ($found === null) {
            $message = match ($kind) {
                'models' => 'მითითებული ბრენდი ვერ მოიძებნა.',
                'generations' => 'მითითებული მოდელი ვერ მოიძებნა.',
                'modifications' => 'მითითებული თაობა ვერ მოიძებნა.',
                'years' => 'მითითებული მოდელი ვერ მოიძებნა.',
                default => 'მითითებული მისამართით მონაცემები ვერ მოიძებნა.',
            };

            return ['error' => $this->error(404, 'not_found', $message)];
        }

        return ['body' => $parsed->envelope($this->path($kind, $route), $found['rows'], $found['total'])];
    }

    /** @param array{kind:string, slug?:string, id?:string} $route */
    private function path(string $kind, array $route): string
    {
        return match ($kind) {
            'brands' => '/api/v1/brands',
            'models' => '/api/v1/brands/'.rawurlencode((string) $route['slug']).'/models',
            'generations' => '/api/v1/models/'.$route['id'].'/generations',
            'years' => '/api/v1/models/'.$route['id'].'/years',
            'modifications' => '/api/v1/generations/'.$route['id'].'/modifications',
            'modification_search' => '/api/v1/modifications',
            'engines' => '/api/v1/engines',
            'bodies' => '/api/v1/bodies',
            'fuel' => '/api/v1/fuel',
            default => '/api/v1/colors',
        };
    }

    /**
     * Sort fields a list route accepts. `PublicSpec` publishes this allowlist.
     *
     * @return list<string>
     */
    public static function sortAllowlist(string $kind): array
    {
        return match ($kind) {
            'brands', 'models' => ['name', 'id', 'modified'],
            'generations' => ['year_start', 'name', 'id', 'modified'],
            'years' => ['year', 'id'],
            'modifications', 'modification_search' => ['name', 'id', 'year_start', 'engine_volume_cc', 'modified'],
            'engines' => ['volume_cc', 'id', 'power_hp', 'modified'],
            'bodies' => ['name', 'id', 'count'],
            'fuel' => ['combined_l', 'id', 'modified'],
            default => ['name', 'id', 'modified'],
        };
    }

    /**
     * Filter names a list route accepts. `q` and `modified_after` are separate.
     *
     * @return list<string>
     */
    public static function filterAllowlist(string $kind): array
    {
        return match ($kind) {
            'models' => ['body'],
            'generations' => ['year_from', 'year_to', 'body'],
            'modifications' => ['year_from', 'year_to', 'body', 'fuel', 'drive', 'transmission'],
            'modification_search', 'fuel' => ['year_from', 'year_to', 'body', 'fuel', 'drive', 'transmission', 'brand_id', 'model_id'],
            'engines' => ['fuel', 'brand_id', 'model_id', 'volume_from', 'volume_to'],
            'colors' => ['brand_id', 'model_id'],
            default => [],
        };
    }

    /**
     * Query parameter names a list route accepts, in document order.
     * Years reject `q`. Years and bodies reject `modified_after`.
     *
     * @return list<string>
     */
    public static function listParameters(string $kind): array
    {
        $names = ['page', 'limit', 'per_page', 'sort', 'direction'];
        if ($kind !== 'years') {
            $names[] = 'q';
        }
        if (! in_array($kind, ['years', 'bodies'], true)) {
            $names[] = 'modified_after';
        }

        return array_merge($names, self::filterAllowlist($kind));
    }

    private function modifiedTable(string $kind): string
    {
        return match ($kind) {
            'brands' => 'brands',
            'models' => 'models',
            'generations' => 'generations',
            'colors' => 'model_photos',
            'bodies' => 'modifications',
            default => 'modifications',
        };
    }

    /** @return list<string> */
    public static function templates(): array
    {
        return array_column(self::definitions(), 'template');
    }

    /**
     * Route table shared with PublicSpec. More specific patterns come first.
     *
     * @return list<array{template:string, kind:string, pattern:string, param?:string, id?:bool, gate?:string}>
     */
    private static function definitions(): array
    {
        return [
            ['template' => '/api/v1/brands', 'kind' => 'brands', 'pattern' => '#^/api/v1/brands$#'],
            ['template' => '/api/v1/brands/{slug}/models', 'kind' => 'models', 'pattern' => '#^/api/v1/brands/([^/]+)/models$#u', 'param' => 'slug'],
            ['template' => '/api/v1/models/{id}/years', 'kind' => 'years', 'pattern' => '#^/api/v1/models/([^/]+)/years$#', 'param' => 'id', 'id' => true],
            ['template' => '/api/v1/models/{id}/generations', 'kind' => 'generations', 'pattern' => '#^/api/v1/models/([^/]+)/generations$#', 'param' => 'id', 'id' => true],
            ['template' => '/api/v1/generations/{id}/modifications', 'kind' => 'modifications', 'pattern' => '#^/api/v1/generations/([^/]+)/modifications$#', 'param' => 'id', 'id' => true, 'gate' => 'CARAPI_MODIFICATIONS_ENABLED'],
            ['template' => '/api/v1/modifications/{id}/specs', 'kind' => 'modification_specs', 'pattern' => '#^/api/v1/modifications/([^/]+)/specs$#', 'param' => 'id', 'id' => true, 'gate' => 'CARAPI_MODIFICATION_DETAILS_ENABLED'],
            ['template' => '/api/v1/modifications/{id}/equipment', 'kind' => 'modification_equipment', 'pattern' => '#^/api/v1/modifications/([^/]+)/equipment$#', 'param' => 'id', 'id' => true, 'gate' => 'CARAPI_MODIFICATION_DETAILS_ENABLED'],
            ['template' => '/api/v1/modifications/{id}', 'kind' => 'modification', 'pattern' => '#^/api/v1/modifications/([^/]+)$#', 'param' => 'id', 'id' => true, 'gate' => 'CARAPI_MODIFICATION_DETAILS_ENABLED'],
            ['template' => '/api/v1/modifications', 'kind' => 'modification_search', 'pattern' => '#^/api/v1/modifications$#', 'gate' => 'CARAPI_MODIFICATIONS_ENABLED'],
            ['template' => '/api/v1/engines', 'kind' => 'engines', 'pattern' => '#^/api/v1/engines$#', 'gate' => 'CARAPI_MODIFICATIONS_ENABLED'],
            ['template' => '/api/v1/bodies', 'kind' => 'bodies', 'pattern' => '#^/api/v1/bodies$#'],
            ['template' => '/api/v1/fuel', 'kind' => 'fuel', 'pattern' => '#^/api/v1/fuel$#', 'gate' => 'CARAPI_MODIFICATIONS_ENABLED'],
            ['template' => '/api/v1/colors', 'kind' => 'colors', 'pattern' => '#^/api/v1/colors$#'],
            ['template' => '/api/v1/coverage', 'kind' => 'coverage', 'pattern' => '#^/api/v1/coverage$#'],
        ];
    }

    /** @return array{kind:string, slug?:string, id?:string, response?:array{status:int, headers:array<string, string>, body:array<string, mixed>}}|null */
    private function route(string $path): ?array
    {
        $path = rawurldecode($path);
        foreach (self::definitions() as $definition) {
            if (preg_match($definition['pattern'], $path, $match) !== 1) {
                continue;
            }
            if (isset($definition['gate']) && ! $this->enabled($definition['gate'])) {
                return null;
            }
            if (! isset($definition['param'])) {
                return ['kind' => $definition['kind']];
            }
            if (($definition['id'] ?? false) && preg_match('/^[1-9][0-9]{0,18}$/', $match[1]) !== 1) {
                return ['kind' => 'invalid', 'response' => $this->error(422, 'invalid_parameter', $this->idMessage($definition['kind']))];
            }

            return ['kind' => $definition['kind'], $definition['param'] => $match[1]];
        }

        return null;
    }

    private function idMessage(string $kind): string
    {
        $label = match ($kind) {
            'modifications' => 'თაობის',
            'generations', 'years' => 'მოდელის',
            default => 'მოდიფიკაციის',
        };

        return $label.' id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.';
    }

    private function enabled(string $name): bool
    {
        $value = carapi_env($name);

        return is_string($value) && in_array(strtolower(trim($value)), ['1', 'true', 'on', 'yes'], true);
    }

    private function modificationDetail(string $path): bool
    {
        return preg_match('#^/api/v1/modifications/[^/]+(?:/(?:specs|equipment))?$#', rawurldecode($path)) === 1;
    }

    /** @param array<string, string> $headers @return array{status:int, headers:array<string, string>, body:array<string, mixed>} */
    private function error(int $status, string $code, string $message, array $headers = []): array
    {
        return ['status' => $status, 'headers' => $headers, 'body' => ['error' => ['code' => $code, 'message' => $message]]];
    }

    /** @return array{status:int, headers:array<string, string>, body:array<string, mixed>} */
    private function unavailable(): array
    {
        return $this->error(503, 'unavailable', 'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.');
    }

    /** @return array{status:int, headers:array<string, string>, body:array<string, mixed>} */
    private function limited(string $code, int $retryAfter, int $limit, int $remaining, int $reset): array
    {
        $message = $code === 'DAILY_QUOTA_EXCEEDED'
            ? 'დღიური ლიმიტი მიღწეულია.'
            : 'მოთხოვნების ლიმიტი მიღწეულია.';

        return $this->error($code === 'RATE_LIMIT_EXCEEDED' || $code === 'DAILY_QUOTA_EXCEEDED' || $code === 'MONTHLY_QUOTA_EXCEEDED' ? 429 : 429, $code, $message, [
            'Retry-After' => (string) max(1, $retryAfter),
            'X-RateLimit-Limit' => (string) $limit,
            'X-RateLimit-Remaining' => (string) $remaining,
            'X-RateLimit-Reset' => (string) $reset,
        ]);
    }
}
