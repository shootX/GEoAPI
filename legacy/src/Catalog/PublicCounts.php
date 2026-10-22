<?php

declare(strict_types=1);

namespace Carapi\Catalog;

final class PublicCounts
{
    private const TTL = 3600;

    /** @return array{status:string, brands?:int, models?:int, generations?:int, generation_groups?:int, models_without_generations?:int, modifications?:int, photos?:int} */
    public static function read(): array
    {
        if (! Http::enabled()) {
            return ['status' => 'hidden'];
        }
        $cached = self::cached();
        if ($cached !== null) {
            return $cached;
        }
        try {
            $pdo = Connection::pdo();
            $coverage = (new CatalogRepository($pdo))->coverage();
            // coverage() groups a chassis into one generation. The public total is every row.
            $totals = $pdo->query(<<<'SQL'
                SELECT
                    (SELECT count(*) FROM public.generations) AS generations,
                    (SELECT count(*) FROM public.model_photos WHERE image IS NOT NULL AND verified = true) AS photos
            SQL)->fetch();
            $generations = is_array($totals) ? (int) $totals['generations'] : 0;
            $photos = is_array($totals) ? (int) $totals['photos'] : 0;
            $extra = self::extras($pdo);
        } catch (UnavailableException|\PDOException) {
            return ['status' => 'unavailable'];
        }
        if ($generations < 1) {
            return ['status' => 'unavailable'];
        }
        $counts = [
            'status' => 'ok',
            'brands' => (int) $coverage['brands_count'],
            'models' => (int) $coverage['models_count'],
            'generations' => $generations,
            'generation_groups' => (int) $coverage['generations_count'],
            'models_without_generations' => (int) $coverage['models_without_generations_count'],
            'modifications' => (int) $coverage['modifications_count'],
            'photos' => $photos,
        ] + $extra;
        self::store($counts);

        return $counts;
    }

    /** @return array{status:string, brands:int, models:int, generations:int, generation_groups:int, models_without_generations:int}|null */
    private static function cached(): ?array
    {
        $path = self::path();
        if (! is_file($path) || filemtime($path) < time() - self::TTL) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);
        if (! is_array($decoded) || ($decoded['status'] ?? '') !== 'ok') {
            return null;
        }
        foreach (['brands', 'models', 'generations', 'generation_groups', 'models_without_generations'] as $key) {
            $min = $key === 'models_without_generations' ? 0 : 1;
            if (! isset($decoded[$key]) || ! is_int($decoded[$key]) || $decoded[$key] < $min) {
                return null;
            }
        }
        foreach (['modifications', 'photos', 'specifications', 'markets', 'equipment'] as $optional) {
            if (array_key_exists($optional, $decoded) && (! is_int($decoded[$optional]) || $decoded[$optional] < 0)) {
                return null;
            }
        }

        return $decoded;
    }

    /** @param array{status:string, brands:int, models:int, generations:int, generation_groups:int, models_without_generations:int, modifications:int, photos:int} $counts */
    /**
     * Cheap extra totals. Equipment (~47M rows) uses the planner estimate, never COUNT(*).
     *
     * @return array<string, int>
     */
    private static function extras(\PDO $pdo): array
    {
        $out = [];
        $exact = ['specifications' => 'public.modification_specs', 'markets' => 'public.model_markets'];
        foreach ($exact as $key => $table) {
            try {
                if ($pdo->query("SELECT to_regclass('{$table}')")->fetchColumn() === null) {
                    continue;
                }
                $out[$key] = (int) $pdo->query("SELECT count(*) FROM {$table}")->fetchColumn();
            } catch (\PDOException) {
            }
        }
        try {
            $estimate = $pdo->query("SELECT c.reltuples::bigint FROM pg_class c WHERE c.oid = to_regclass('public.modification_equipment')")->fetchColumn();
            if ($estimate !== false && $estimate !== null && (int) $estimate > 0) {
                $out['equipment'] = (int) $estimate;
            }
        } catch (\PDOException) {
        }

        return $out;
    }

    private static function store(array $counts): void
    {
        $path = self::path();
        $dir = dirname($path);
        if (! is_dir($dir) || (is_file($path) ? ! is_writable($path) : ! is_writable($dir))) {
            return;
        }
        file_put_contents($path, json_encode($counts), LOCK_EX);
    }

    private static function path(): string
    {
        return dirname(__DIR__, 2).'/cache/public-counts.json';
    }
}
