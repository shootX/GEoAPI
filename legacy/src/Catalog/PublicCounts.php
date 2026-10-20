<?php

declare(strict_types=1);

namespace Carapi\Catalog;

final class PublicCounts
{
    private const TTL = 120;

    /** @return array{status:string, brands?:int, models?:int, generations?:int, models_without_generations?:int} */
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
            $generations = (int) $pdo->query('SELECT count(*) FROM public.generations')->fetchColumn();
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
            'models_without_generations' => (int) $coverage['models_without_generations_count'],
        ];
        self::store($counts);

        return $counts;
    }

    /** @return array{status:string, brands:int, models:int, generations:int, models_without_generations:int}|null */
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
        foreach (['brands', 'models', 'generations', 'models_without_generations'] as $key) {
            $min = $key === 'models_without_generations' ? 0 : 1;
            if (! isset($decoded[$key]) || ! is_int($decoded[$key]) || $decoded[$key] < $min) {
                return null;
            }
        }

        return $decoded;
    }

    /** @param array{status:string, brands:int, models:int, generations:int, models_without_generations:int} $counts */
    private static function store(array $counts): void
    {
        $path = self::path();
        $dir = dirname($path);
        if (! is_dir($dir)) {
            return;
        }
        file_put_contents($path, json_encode($counts), LOCK_EX);
    }

    private static function path(): string
    {
        return dirname(__DIR__, 2).'/cache/public-counts.json';
    }
}
