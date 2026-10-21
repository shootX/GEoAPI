<?php

declare(strict_types=1);

namespace Carapi\Catalog;

use PDO;
use PDOException;

final class CatalogRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array{rows:list<array<string, mixed>>, total:int} */
    public function brands(int $limit, int $offset): array
    {
        $total = $this->count('SELECT count(*) FROM public.brands');
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT id::text AS id, slug, name, synonyms,
                   is_lcv_or_commercial, logo_url_light, logo_url_dark
            FROM public.brands
            ORDER BY name, id
            LIMIT :limit OFFSET :offset
        SQL);
        $this->bindLimit($stmt, $limit, $offset);
        $stmt->execute();

        return ['rows' => array_map(self::brand(...), $stmt->fetchAll() ?: []), 'total' => $total];
    }

    public function brandExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM public.brands WHERE slug = :slug');
        $stmt->execute(['slug' => $slug]);

        return $stmt->fetchColumn() !== false;
    }

    /** @return array{rows:list<array<string, mixed>>, total:int} */
    public function models(string $slug, int $limit, int $offset): array
    {
        $totalStmt = $this->pdo->prepare(<<<'SQL'
            SELECT count(*)
            FROM public.models m
            JOIN public.brands b ON b.id = m.brand_id
            WHERE b.slug = :slug
        SQL);
        $totalStmt->execute(['slug' => $slug]);
        $total = (int) $totalStmt->fetchColumn();

        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT m.id::text AS id,
                   m.brand_id::text AS brand_id,
                   m.slug,
                   m.name,
                   COALESCE(
                       (SELECT json_agg(mm.market_slug ORDER BY mm.market_slug)
                        FROM public.model_markets mm
                        WHERE mm.model_id = m.id),
                       '[]'::json
                   ) AS markets,
                   (SELECT count(*) FROM (
                        SELECT 1
                        FROM public.generations g
                        WHERE g.model_id = m.id
                        GROUP BY CASE
                            WHEN nullif(btrim(g.chassis_code), '') IS NOT NULL
                            THEN 'c:' || upper(btrim(g.chassis_code))
                            ELSE 'n:' || lower(btrim(coalesce(g.name, '')))
                        END
                   )) AS generations_count
            FROM public.models m
            JOIN public.brands b ON b.id = m.brand_id
            WHERE b.slug = :slug
            ORDER BY m.name, m.id
            LIMIT :limit OFFSET :offset
        SQL);
        $stmt->bindValue('slug', $slug);
        $this->bindLimit($stmt, $limit, $offset);
        $stmt->execute();

        return ['rows' => array_map(self::model(...), $stmt->fetchAll() ?: []), 'total' => $total];
    }

    public function modelExists(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM public.models WHERE id = :id');
        $stmt->bindValue('id', $id);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /** @return array{rows:list<array<string, mixed>>, total:int} */
    public function generations(string $modelId, int $limit, int $offset): array
    {
        $totalStmt = $this->pdo->prepare(<<<'SQL'
            SELECT count(*) FROM (
                SELECT 1
                FROM public.generations g
                WHERE g.model_id = :id
                GROUP BY CASE
                    WHEN nullif(btrim(g.chassis_code), '') IS NOT NULL
                    THEN 'c:' || upper(btrim(g.chassis_code))
                    ELSE 'n:' || lower(btrim(coalesce(g.name, '')))
                END
            ) grouped
        SQL);
        $totalStmt->bindValue('id', $modelId);
        $totalStmt->execute();
        $total = (int) $totalStmt->fetchColumn();

        $stmt = $this->pdo->prepare(<<<'SQL'
            WITH raw AS (
                SELECT
                    g.id,
                    g.model_id,
                    g.slug,
                    btrim(g.name) AS name,
                    g.year_start,
                    g.year_end,
                    NULLIF(btrim(g.body_type), '') AS body_type,
                    NULLIF(btrim(g.chassis_code), '') AS chassis_code,
                    NULLIF(btrim(g.steering_wheel), '') AS steering_wheel,
                    CASE
                        WHEN nullif(btrim(g.chassis_code), '') IS NOT NULL
                        THEN 'c:' || upper(btrim(g.chassis_code))
                        ELSE 'n:' || lower(btrim(coalesce(g.name, '')))
                    END AS group_key
                FROM public.generations g
                WHERE g.model_id = :id
            ),
            grouped AS (
                SELECT
                    min(id) AS id,
                    min(model_id) AS model_id,
                    (array_agg(slug ORDER BY id))[1] AS slug,
                    COALESCE(
                        (array_agg(chassis_code ORDER BY id) FILTER (WHERE chassis_code IS NOT NULL))[1],
                        (array_agg(name ORDER BY id) FILTER (WHERE name IS NOT NULL AND name <> ''))[1]
                    ) AS name,
                    min(year_start) AS year_start,
                    CASE WHEN bool_or(year_end IS NULL) THEN NULL ELSE max(year_end) END AS year_end,
                    CASE
                        WHEN count(DISTINCT body_type) FILTER (WHERE body_type IS NOT NULL) = 1
                         AND count(*) FILTER (WHERE body_type IS NULL) = 0
                        THEN min(body_type)
                        ELSE NULL
                    END AS body_type,
                    (array_agg(chassis_code ORDER BY id) FILTER (WHERE chassis_code IS NOT NULL))[1] AS chassis_code,
                    CASE
                        WHEN count(DISTINCT steering_wheel) FILTER (WHERE steering_wheel IS NOT NULL) = 1
                        THEN min(steering_wheel)
                        ELSE NULL
                    END AS steering_wheel
                FROM raw
                GROUP BY group_key
            ),
            labeled AS (
                SELECT *,
                    concat_ws(' · ',
                        NULLIF(name, ''),
                        CASE
                            WHEN year_start IS NULL THEN NULL
                            WHEN year_end IS NULL THEN year_start::text || '–'
                            ELSE year_start::text || '–' || year_end::text
                        END
                    ) AS base_label
                FROM grouped
            )
            SELECT id::text AS id,
                   model_id::text AS model_id,
                   slug,
                   name,
                   year_start,
                   year_end,
                   CASE
                       WHEN year_start IS NULL THEN NULL
                       WHEN year_end IS NULL THEN year_start::text || '–'
                       ELSE year_start::text || '–' || year_end::text
                   END AS period_text,
                   body_type,
                   chassis_code,
                   steering_wheel,
                   NULL::text AS market,
                   CASE
                       WHEN count(*) OVER (PARTITION BY base_label) > 1
                       THEN concat_ws(' · ', NULLIF(base_label, ''), id::text)
                       ELSE COALESCE(NULLIF(base_label, ''), id::text)
                   END AS display_label
            FROM labeled
            ORDER BY year_start NULLS LAST, display_label, id
            LIMIT :limit OFFSET :offset
        SQL);
        $stmt->bindValue('id', $modelId);
        $this->bindLimit($stmt, $limit, $offset);
        $stmt->execute();

        return ['rows' => array_map(self::generation(...), $stmt->fetchAll() ?: []), 'total' => $total];
    }

    /**
     * Modifications for the public generation id.
     * The id is the minimum generations.id in the same group used by generations().
     * A sibling row id is not a public id.
     *
     * @return array{rows:list<array<string, mixed>>, total:int}|null
     */
    public function modifications(string $generationId, int $limit, int $offset): ?array
    {
        $resolved = $this->pdo->prepare($this->generationGroupSql().<<<'SQL'
            SELECT r.id::text AS requested_id, m.public_id::text AS public_id
            FROM requested r
            JOIN members m ON m.id = r.id
        SQL);
        $resolved->bindValue('id', $generationId);
        $resolved->execute();
        $identity = $resolved->fetch();
        if (! is_array($identity) || $identity['requested_id'] !== $identity['public_id']) {
            return null;
        }

        $count = $this->pdo->prepare($this->generationGroupSql().<<<'SQL'
            SELECT count(*)
            FROM public.modifications mod
            WHERE mod.generation_id IN (SELECT id FROM members)
        SQL);
        $count->bindValue('id', $generationId);
        $count->execute();
        $total = (int) $count->fetchColumn();

        $stmt = $this->pdo->prepare($this->generationGroupSql().<<<'SQL'
            SELECT mod.id::text AS id,
                   btrim(mod.name) AS name,
                   mod.engine_volume_cc,
                   NULLIF(btrim(mod.transmission), '') AS transmission,
                   mod.year_start,
                   mod.year_end
            FROM public.modifications mod
            WHERE mod.generation_id IN (SELECT id FROM members)
            ORDER BY mod.year_start NULLS LAST, mod.engine_volume_cc NULLS LAST, mod.transmission NULLS LAST, mod.name, mod.id
            LIMIT :limit OFFSET :offset
        SQL);
        $stmt->bindValue('id', $generationId);
        $this->bindLimit($stmt, $limit, $offset);
        $stmt->execute();
        $publicId = (string) $identity['public_id'];
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'id' => (string) $row['id'],
                'generation_id' => $publicId,
                'name' => self::text($row['name']) ?? '',
                'engine_volume_cc' => self::year($row['engine_volume_cc']),
                'transmission' => self::text($row['transmission']),
                'year_start' => self::year($row['year_start']),
                'year_end' => self::year($row['year_end']),
            ];
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * One modification by modifications.id.
     * generation.id is the public minimum id of the same group, never a sibling row id.
     *
     * @return array<string, mixed>|null
     */
    public function modification(string $id): ?array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT mod.id::text AS id,
                   btrim(mod.name) AS name,
                   mod.engine_volume_cc,
                   NULLIF(btrim(mod.transmission), '') AS transmission,
                   NULLIF(btrim(mod.drive_type), '') AS drive_type,
                   NULLIF(btrim(mod.body_type), '') AS body_type,
                   mod.year_start,
                   mod.year_end,
                   b.id::text AS brand_id,
                   b.slug AS brand_slug,
                   b.name AS brand_name,
                   mo.id::text AS model_id,
                   mo.slug AS model_slug,
                   mo.name AS model_name,
                   pub.public_id::text AS generation_id,
                   pub.generation_name
            FROM public.modifications mod
            JOIN public.models mo ON mo.id = mod.model_id
            JOIN public.brands b ON b.id = mo.brand_id
            LEFT JOIN LATERAL (
                SELECT min(g2.id) AS public_id,
                       (array_agg(g2.name ORDER BY g2.id))[1] AS generation_name
                FROM public.generations g
                JOIN public.generations g2
                  ON g2.model_id = g.model_id
                 AND CASE
                        WHEN nullif(btrim(g2.chassis_code), '') IS NOT NULL
                        THEN 'c:' || upper(btrim(g2.chassis_code))
                        ELSE 'n:' || lower(btrim(coalesce(g2.name, '')))
                     END
                   = CASE
                        WHEN nullif(btrim(g.chassis_code), '') IS NOT NULL
                        THEN 'c:' || upper(btrim(g.chassis_code))
                        ELSE 'n:' || lower(btrim(coalesce(g.name, '')))
                     END
                WHERE g.id = mod.generation_id
            ) pub ON true
            WHERE mod.id = :id
        SQL);
        $stmt->bindValue('id', $id);
        $stmt->execute();
        $row = $stmt->fetch();
        if (! is_array($row)) {
            return null;
        }

        $generationId = self::text($row['generation_id']);

        return [
            'id' => (string) $row['id'],
            'name' => self::text($row['name']) ?? '',
            'engine_volume_cc' => self::year($row['engine_volume_cc']),
            'transmission' => self::text($row['transmission']),
            'drive_type' => self::text($row['drive_type']),
            'body_type' => self::text($row['body_type']),
            'year_start' => self::year($row['year_start']),
            'year_end' => self::year($row['year_end']),
            'brand' => [
                'id' => (string) $row['brand_id'],
                'slug' => self::text($row['brand_slug']) ?? '',
                'name' => self::text($row['brand_name']) ?? '',
            ],
            'model' => [
                'id' => (string) $row['model_id'],
                'slug' => self::text($row['model_slug']) ?? '',
                'name' => self::text($row['model_name']) ?? '',
            ],
            'generation' => $generationId === null ? null : [
                'id' => $generationId,
                'name' => self::text($row['generation_name']),
            ],
        ];
    }

    /** Same chassis/name grouping as generations(). :id is used once. */
    private function generationGroupSql(): string
    {
        return <<<'SQL'
            WITH requested AS (
                SELECT
                    g.id,
                    g.model_id,
                    CASE
                        WHEN nullif(btrim(g.chassis_code), '') IS NOT NULL
                        THEN 'c:' || upper(btrim(g.chassis_code))
                        ELSE 'n:' || lower(btrim(coalesce(g.name, '')))
                    END AS group_key
                FROM public.generations g
                WHERE g.id = :id
            ),
            members AS (
                SELECT
                    g.id,
                    min(g.id) OVER (
                        PARTITION BY g.model_id,
                        CASE
                            WHEN nullif(btrim(g.chassis_code), '') IS NOT NULL
                            THEN 'c:' || upper(btrim(g.chassis_code))
                            ELSE 'n:' || lower(btrim(coalesce(g.name, '')))
                        END
                    ) AS public_id
                FROM public.generations g
                JOIN requested r ON r.model_id = g.model_id
                WHERE CASE
                    WHEN nullif(btrim(g.chassis_code), '') IS NOT NULL
                    THEN 'c:' || upper(btrim(g.chassis_code))
                    ELSE 'n:' || lower(btrim(coalesce(g.name, '')))
                END = r.group_key
            )
        SQL;
    }

    /** @return array{rows:list<array{id:string,name:string,bodies:list<string>}>, total:int} */
    public function yearChoices(string $modelId, ?int $currentYear = null): array
    {
        $choices = PickerSelection::years($this->pdo, $modelId, $currentYear);

        return $choices ?? ['rows' => [], 'total' => 0];
    }

    /** @return array<string, int|string> */
    public function coverage(): array
    {
        $row = $this->pdo->query(<<<'SQL'
            SELECT
                (SELECT count(*) FROM public.brands) AS brands_count,
                (SELECT count(*) FROM public.models) AS models_count,
                (SELECT count(*) FROM (
                    SELECT 1
                    FROM public.generations g
                    GROUP BY g.model_id, CASE
                        WHEN nullif(btrim(g.chassis_code), '') IS NOT NULL
                        THEN 'c:' || upper(btrim(g.chassis_code))
                        ELSE 'n:' || lower(btrim(coalesce(g.name, '')))
                    END
                ) grouped) AS generations_count,
                (SELECT count(DISTINCT model_id) FROM public.generations) AS models_with_generations_count,
                (SELECT count(*) FROM public.models m WHERE NOT EXISTS (
                    SELECT 1 FROM public.generations g WHERE g.model_id = m.id
                )) AS models_without_generations_count,
                (SELECT count(*) FROM public.modifications) AS modifications_count,
                (SELECT count(*) FROM public.photos) AS photos_count,
                (SELECT count(*) FROM public.generations
                 WHERE jsonb_typeof(photo_urls) = 'array'
                   AND jsonb_array_length(photo_urls) > 0) AS generations_with_photo_urls_count,
                to_char(now() AT TIME ZONE 'UTC', 'YYYY-MM-DD"T"HH24:MI:SS"Z"') AS measured_at
        SQL)->fetch();

        if (! is_array($row)) {
            throw new UnavailableException;
        }

        $out = [];
        foreach ([
            'brands_count',
            'models_count',
            'generations_count',
            'models_with_generations_count',
            'models_without_generations_count',
            'modifications_count',
            'photos_count',
            'generations_with_photo_urls_count',
        ] as $key) {
            $out[$key] = (int) $row[$key];
        }
        $out['measured_at'] = (string) $row['measured_at'];

        return $out;
    }

    private function count(string $sql): int
    {
        try {
            return (int) $this->pdo->query($sql)->fetchColumn();
        } catch (PDOException) {
            error_log('[catalog] query failed');
            throw new UnavailableException;
        }
    }

    private function bindLimit(\PDOStatement $stmt, int $limit, int $offset): void
    {
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function brand(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'slug' => self::text($row['slug']) ?? '',
            'name' => self::text($row['name']) ?? '',
            'synonyms' => self::stringList($row['synonyms']),
            'is_lcv_or_commercial' => self::bool($row['is_lcv_or_commercial']),
            'logo_url_light' => self::text($row['logo_url_light']),
            'logo_url_dark' => self::text($row['logo_url_dark']),
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function model(array $row): array
    {
        $count = (int) $row['generations_count'];

        return [
            'id' => (string) $row['id'],
            'brand_id' => (string) $row['brand_id'],
            'slug' => self::text($row['slug']) ?? '',
            'name' => self::text($row['name']) ?? '',
            'markets' => self::stringList($row['markets']),
            'has_generations' => $count > 0,
            'generations_count' => $count,
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private static function generation(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'model_id' => (string) $row['model_id'],
            'slug' => self::text($row['slug']) ?? '',
            'name' => self::text($row['name']),
            'year_start' => self::year($row['year_start']),
            'year_end' => self::year($row['year_end']),
            'period_text' => self::text($row['period_text']),
            'body_type' => self::text($row['body_type']),
            'chassis_code' => self::text($row['chassis_code']),
            'steering_wheel' => self::text($row['steering_wheel']),
            'market' => self::text($row['market']),
            'display_label' => self::text($row['display_label']) ?? (string) $row['id'],
        ];
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function year(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private static function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($value) || ! array_is_list($value)) {
            return [];
        }
        $out = [];
        foreach ($value as $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                continue;
            }
            $text = trim((string) $item);
            if ($text !== '') {
                $out[] = $text;
            }
        }

        return $out;
    }
}
