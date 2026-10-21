<?php

declare(strict_types=1);

namespace App\Catalog;

use Carapi\Catalog\CatalogRepository;
use PDO;

/**
 * Read-only catalog queries for /api/v1.
 * PostgreSQL in production, SQLite in tests. Only columns that already exist are selected.
 */
final class PublicCatalog
{
    private string $driver;

    /** @var array<string, bool> */
    private array $columns = [];

    public function __construct(private PDO $pdo)
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->driver = is_string($driver) ? $driver : 'pgsql';
    }

    public function hasUpdatedAt(string $table): bool
    {
        return $this->hasColumn($table, 'updated_at');
    }

    /** @return array{rows:list<array<string, mixed>>, total:int} */
    public function brands(ListQuery $query): array
    {
        [$where, $params] = $this->baseFilters('brands', 'b', $query, ['name', 'slug']);
        $total = $this->count('brands', 'b', $where, $params);
        $order = $this->order([
            'name' => 'b.name',
            'id' => 'b.id',
            'modified' => 'b.updated_at',
        ], $query, 'b.name ASC, b.id ASC');
        $rows = $this->select(
            'SELECT b.id, b.slug, b.name, b.synonyms, b.is_lcv_or_commercial, b.logo_url_light, b.logo_url_dark, '.$this->modifiedSql('b', 'brands').'
             FROM '.$this->table('brands').' b '.$this->where($where).' ORDER BY '.$order.' LIMIT :limit OFFSET :offset',
            $params,
            $query,
        );

        return ['rows' => array_map(fn (array $row): array => [
            'id' => (string) $row['id'],
            'slug' => $this->text($row['slug']) ?? '',
            'name' => $this->text($row['name']) ?? '',
            'synonyms' => $this->stringList($row['synonyms']),
            'is_lcv_or_commercial' => $this->bool($row['is_lcv_or_commercial']),
            'logo_url_light' => $this->text($row['logo_url_light']),
            'logo_url_dark' => $this->text($row['logo_url_dark']),
            'modified' => $this->modified($row['modified'] ?? null),
        ], $rows), 'total' => $total];
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function models(string $slug, ListQuery $query): ?array
    {
        $brand = $this->one('SELECT id FROM '.$this->table('brands').' WHERE slug = :slug', ['slug' => $slug]);
        if ($brand === null) {
            return null;
        }
        [$where, $params] = $this->baseFilters('models', 'm', $query, ['name', 'slug']);
        $where[] = 'm.brand_id = :brand_id';
        $params['brand_id'] = (string) $brand['id'];
        if (isset($query->filters['body'])) {
            $where[] = 'lower('.$this->trimmed('m.body_type').') = :body';
            $params['body'] = mb_strtolower((string) $query->filters['body']);
        }
        $total = $this->count('models', 'm', $where, $params);
        $order = $this->order([
            'name' => 'm.name',
            'id' => 'm.id',
            'modified' => 'm.updated_at',
        ], $query, 'm.name ASC, m.id ASC');
        $rows = $this->select(
            'SELECT m.id, m.brand_id, m.slug, m.name, '.$this->modifiedSql('m', 'models').'
             FROM '.$this->table('models').' m '.$this->where($where).' ORDER BY '.$order.' LIMIT :limit OFFSET :offset',
            $params,
            $query,
        );
        $ids = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $groups = $this->generationCounts($ids);
        $markets = $this->markets($ids);

        return ['rows' => array_map(function (array $row) use ($groups, $markets): array {
            $id = (string) $row['id'];
            $count = $groups[$id] ?? 0;

            return [
                'id' => $id,
                'brand_id' => (string) $row['brand_id'],
                'slug' => $this->text($row['slug']) ?? '',
                'name' => $this->text($row['name']) ?? '',
                'markets' => $markets[$id] ?? [],
                'has_generations' => $count > 0,
                'generations_count' => $count,
                'modified' => $this->modified($row['modified'] ?? null),
            ];
        }, $rows), 'total' => $total];
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function generations(string $modelId, ListQuery $query): ?array
    {
        if ($this->one('SELECT id FROM '.$this->table('models').' WHERE id = :id', ['id' => $modelId]) === null) {
            return null;
        }
        $params = ['id' => $modelId];
        $where = ['g.model_id = :id'];
        $this->applyCommon($where, $params, 'g', 'generations', $query, ['name', 'chassis_code']);
        if (isset($query->filters['body'])) {
            $where[] = 'lower('.$this->trimmed('g.body_type').') = :body';
            $params['body'] = mb_strtolower((string) $query->filters['body']);
        }
        $this->yearOverlap($where, $params, 'g', $query);
        $raw = $this->all(
            'SELECT g.id, g.model_id, g.slug, g.name, g.year_start, g.year_end, g.body_type, g.chassis_code, g.steering_wheel, '.$this->modifiedSql('g', 'generations').'
             FROM '.$this->table('generations').' g '.$this->where($where),
            $params,
        );
        $grouped = $this->groupGenerations($raw);
        $this->sortRows($grouped, $query, [
            'year_start' => 'year_start',
            'name' => 'name',
            'id' => 'id',
            'modified' => 'modified',
        ], function (array $left, array $right): int {
            return $this->nullsLast($left['year_start'], $right['year_start'])
                ?: strcmp((string) $left['display_label'], (string) $right['display_label'])
                ?: strcmp((string) $left['id'], (string) $right['id']);
        });

        return $this->slice($grouped, $query);
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function modificationsForGeneration(string $generationId, ListQuery $query): ?array
    {
        $members = $this->generationMembers($generationId);
        if ($members === null) {
            return null;
        }
        [$where, $params] = $this->modificationFilters($query, false);
        $placeholders = [];
        foreach ($members['ids'] as $index => $id) {
            $key = 'gen_'.$index;
            $placeholders[] = ':'.$key;
            $params[$key] = $id;
        }
        $where[] = 'mod.generation_id IN ('.implode(', ', $placeholders).')';
        $spec = $this->needsSpec($query);
        $total = $this->countJoined($where, $params, false, $spec);
        $order = $this->modificationOrder($query);
        $rows = $this->modificationRows($where, $params, $order, $query, false, $spec);
        $publicId = $members['public_id'];

        return ['rows' => array_map(function (array $row) use ($publicId): array {
            return [
                'id' => (string) $row['id'],
                'generation_id' => $publicId,
                'name' => $this->text($row['name']) ?? '',
                'engine_volume_cc' => $this->intOrNull($row['engine_volume_cc']),
                'transmission' => $this->text($row['transmission']),
                'year_start' => $this->intOrNull($row['year_start']),
                'year_end' => $this->intOrNull($row['year_end']),
                'modified' => $this->modified($row['modified'] ?? null),
            ];
        }, $rows), 'total' => $total];
    }

    /** @return array{rows:list<array<string, mixed>>, total:int} */
    public function searchModifications(ListQuery $query): array
    {
        [$where, $params] = $this->modificationFilters($query, true);
        $total = $this->countJoined($where, $params, true, true);
        $rows = $this->modificationRows($where, $params, $this->modificationOrder($query), $query, true, true);
        $publicIds = $this->publicGenerationIds(array_column($rows, 'generation_id'));

        return ['rows' => array_map(function (array $row) use ($publicIds): array {
            $generationId = $this->text($row['generation_id']);

            return [
                'id' => (string) $row['id'],
                'name' => $this->text($row['name']) ?? '',
                'brand_id' => (string) $row['brand_id'],
                'model_id' => (string) $row['model_id'],
                'generation_id' => $generationId === null ? null : ($publicIds[$generationId] ?? $generationId),
                'engine_volume_cc' => $this->intOrNull($row['engine_volume_cc']),
                'transmission' => $this->text($row['transmission']),
                'drive_type' => $this->text($row['drive_type']),
                'body_type' => $this->text($row['body_type']),
                'fuel' => $this->text($row['fuel'] ?? null),
                'year_start' => $this->intOrNull($row['year_start']),
                'year_end' => $this->intOrNull($row['year_end']),
                'modified' => $this->modified($row['modified'] ?? null),
            ];
        }, $rows), 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function modification(string $id): ?array
    {
        $row = $this->one(
            'SELECT mod.id, mod.name, mod.engine_volume_cc, mod.transmission, mod.drive_type, mod.body_type,
                    mod.year_start, mod.year_end, mod.generation_id, mo.id AS model_id, mo.slug AS model_slug, mo.name AS model_name,
                    b.id AS brand_id, b.slug AS brand_slug, b.name AS brand_name, '.$this->modifiedSql('mod', 'modifications').'
             FROM '.$this->table('modifications').' mod
             JOIN '.$this->table('models').' mo ON mo.id = mod.model_id
             JOIN '.$this->table('brands').' b ON b.id = mo.brand_id
             WHERE mod.id = :id',
            ['id' => $id],
        );
        if ($row === null) {
            return null;
        }
        $generation = null;
        $generationId = $this->text($row['generation_id']);
        if ($generationId !== null) {
            $members = $this->generationMembers($generationId, true);
            if ($members !== null) {
                $generation = [
                    'id' => $members['public_id'],
                    'name' => $members['name'],
                ];
            }
        }

        return [
            'id' => (string) $row['id'],
            'name' => $this->text($row['name']) ?? '',
            'engine_volume_cc' => $this->intOrNull($row['engine_volume_cc']),
            'transmission' => $this->text($row['transmission']),
            'drive_type' => $this->text($row['drive_type']),
            'body_type' => $this->text($row['body_type']),
            'year_start' => $this->intOrNull($row['year_start']),
            'year_end' => $this->intOrNull($row['year_end']),
            'brand' => [
                'id' => (string) $row['brand_id'],
                'slug' => $this->text($row['brand_slug']) ?? '',
                'name' => $this->text($row['brand_name']) ?? '',
            ],
            'model' => [
                'id' => (string) $row['model_id'],
                'slug' => $this->text($row['model_slug']) ?? '',
                'name' => $this->text($row['model_name']) ?? '',
            ],
            'generation' => $generation,
            'modified' => $this->modified($row['modified'] ?? null),
        ];
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function years(string $modelId, ListQuery $query, ?int $currentYear = null): ?array
    {
        if ($this->one('SELECT id FROM '.$this->table('models').' WHERE id = :id', ['id' => $modelId]) === null) {
            return null;
        }
        $currentYear ??= (int) gmdate('Y');
        $spans = $this->all(
            'SELECT year_start, year_end FROM '.$this->table('generations').' WHERE model_id = :id',
            ['id' => $modelId],
        );
        if ($this->hasTable('modifications') && $this->hasColumn('modifications', 'year_start')) {
            $spans = array_merge($spans, $this->all(
                'SELECT year_start, year_end FROM '.$this->table('modifications').' WHERE model_id = :id',
                ['id' => $modelId],
            ));
        }
        $years = [];
        foreach ($spans as $span) {
            foreach ($this->expandYears($span['year_start'] ?? null, $span['year_end'] ?? null, $currentYear) as $year) {
                $years[$year] = $year;
            }
        }
        $rows = [];
        foreach ($years as $year) {
            $rows[] = ['id' => (string) $year, 'year' => $year];
        }
        $this->sortRows($rows, $query, ['year' => 'year', 'id' => 'year'], function (array $left, array $right): int {
            return ((int) $left['year']) <=> ((int) $right['year']);
        });

        return $this->slice($rows, $query);
    }

    /** @return array<string, mixed>|null */
    public function modificationSpecs(string $id): ?array
    {
        if (! $this->hasTable('modifications')) {
            return null;
        }
        $row = $this->one(
            'SELECT id, engine_volume_cc, transmission, drive_type FROM '.$this->table('modifications').' WHERE id = :id',
            ['id' => $id],
        );
        if ($row === null) {
            return null;
        }
        $stored = $this->storedSpecs($id);

        return [
            'modification_id' => (string) $row['id'],
            'engine' => [
                'volume_cc' => $this->intOrNull($row['engine_volume_cc']),
                'fuel' => $this->text($stored['fuel_type'] ?? null),
                'power_hp' => $this->number($stored['power_hp'] ?? null),
            ],
            'transmission' => [
                'type' => $this->text($row['transmission']),
                'drive' => $this->text($row['drive_type']),
            ],
            'dimensions' => null,
            'performance' => null,
            'consumption' => [
                'city_l' => $this->number($stored['fuel_city_l'] ?? null),
                'highway_l' => $this->number($stored['fuel_highway_l'] ?? null),
                'combined_l' => $this->number($stored['fuel_combined_l'] ?? null),
                'unit' => 'L/100km',
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public function modificationEquipment(string $id): ?array
    {
        if ($this->one('SELECT id FROM '.$this->table('modifications').' WHERE id = :id', ['id' => $id]) === null) {
            return null;
        }
        $groups = [
            'modification_id' => (string) $id,
            'standard' => [],
            'optional' => [],
            'not_available' => [],
            'unknown' => [],
        ];
        if (! $this->equipmentReadable()) {
            return $groups;
        }
        $rows = $this->all(
            'SELECT id, category, name, status, value FROM '.$this->table('modification_equipment').' WHERE modification_id = :id ORDER BY id',
            ['id' => $id],
        );
        $seen = [];
        foreach ($rows as $row) {
            $name = $this->text($row['name']);
            if ($name === null) {
                continue;
            }
            $bucket = match ($this->text($row['status'])) {
                'included' => 'standard',
                'option' => 'optional',
                'not_available' => 'not_available',
                default => 'unknown',
            };
            $category = $this->text($row['category']);
            $key = $bucket.'|'.mb_strtolower($category ?? '').'|'.mb_strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $groups[$bucket][] = [
                'id' => (string) $row['id'],
                'category' => $category,
                'name' => $name,
                'value' => $this->text($row['value']),
            ];
        }

        return $groups;
    }

    /** @return array{rows:list<array<string, mixed>>, total:int} */
    public function engines(ListQuery $query): array
    {
        [$where, $params] = $this->modificationFilters($query, true);
        $fuel = $this->spec('fuel_type');
        $power = $this->spec('power_hp');
        $where[] = '(mod.engine_volume_cc IS NOT NULL OR '.$fuel.' IS NOT NULL)';
        if (isset($query->filters['volume_from'])) {
            $where[] = 'mod.engine_volume_cc >= :volume_from';
            $params['volume_from'] = (int) $query->filters['volume_from'];
        }
        if (isset($query->filters['volume_to'])) {
            $where[] = 'mod.engine_volume_cc <= :volume_to';
            $params['volume_to'] = (int) $query->filters['volume_to'];
        }
        $from = $this->joinedFrom(true, true);
        $total = (int) $this->value('SELECT count(*) '.$from.$this->where($where), $params);
        $order = $this->order([
            'volume_cc' => 'mod.engine_volume_cc',
            'id' => 'mod.id',
            'modified' => 'mod.updated_at',
            'power_hp' => $power,
        ], $query, 'mod.engine_volume_cc ASC, mod.id ASC');
        $rows = $this->select(
            'SELECT mod.id, mod.engine_volume_cc, mo.brand_id, mod.model_id, '.$fuel.' AS fuel, '.$power.' AS power_hp, '.$this->modifiedSql('mod', 'modifications').' '.$from.$this->where($where).' ORDER BY '.$order.' LIMIT :limit OFFSET :offset',
            $params,
            $query,
        );

        return ['rows' => array_map(fn (array $row): array => [
            'id' => (string) $row['id'],
            'volume_cc' => $this->intOrNull($row['engine_volume_cc']),
            'fuel' => $this->text($row['fuel']),
            'power_hp' => $this->number($row['power_hp']),
            'brand_id' => (string) $row['brand_id'],
            'model_id' => (string) $row['model_id'],
            'modified' => $this->modified($row['modified'] ?? null),
        ], $rows), 'total' => $total];
    }

    /** @return array{rows:list<array<string, mixed>>, total:int} */
    public function bodies(ListQuery $query): array
    {
        $counts = [];
        foreach (['modifications', 'generations', 'models'] as $table) {
            if (! $this->hasColumn($table, 'body_type')) {
                continue;
            }
            $rows = $this->all(
                'SELECT lower('.$this->trimmed('body_type').') AS name, count(*) AS total FROM '.$this->table($table).' WHERE '.$this->trimmed('body_type')." <> '' GROUP BY 1",
                [],
            );
            foreach ($rows as $row) {
                $name = $this->text($row['name']);
                if ($name === null) {
                    continue;
                }
                $counts[$name] = ($counts[$name] ?? 0) + (int) $row['total'];
            }
        }
        $list = [];
        foreach ($counts as $name => $total) {
            if ($query->q !== null && ! str_contains($name, mb_strtolower($query->q))) {
                continue;
            }
            $list[] = ['id' => $name, 'name' => $name, 'count' => $total, 'modified' => null];
        }
        $this->sortRows($list, $query, ['name' => 'name', 'id' => 'id', 'count' => 'count'], function (array $left, array $right): int {
            return strcmp((string) $left['name'], (string) $right['name']);
        });

        return $this->slice($list, $query);
    }

    /** @return array{rows:list<array<string, mixed>>, total:int} */
    public function fuel(ListQuery $query): array
    {
        [$where, $params] = $this->modificationFilters($query, true);
        $city = $this->spec('fuel_city_l');
        $highway = $this->spec('fuel_highway_l');
        $combined = $this->spec('fuel_combined_l');
        $where[] = '('.$city.' IS NOT NULL OR '.$highway.' IS NOT NULL OR '.$combined.' IS NOT NULL)';
        $from = $this->joinedFrom(true, true);
        $total = (int) $this->value('SELECT count(*) '.$from.$this->where($where), $params);
        $order = $this->order([
            'combined_l' => $combined,
            'id' => 'mod.id',
            'modified' => 'mod.updated_at',
        ], $query, 'mod.id ASC');
        $rows = $this->select(
            'SELECT mod.id, mo.brand_id, mod.model_id, '.$this->spec('fuel_type').' AS fuel, '.$city.' AS city_l, '.$highway.' AS highway_l, '.$combined.' AS combined_l, '.$this->modifiedSql('mod', 'modifications').' '.$from.$this->where($where).' ORDER BY '.$order.' LIMIT :limit OFFSET :offset',
            $params,
            $query,
        );

        return ['rows' => array_map(fn (array $row): array => [
            'id' => (string) $row['id'],
            'fuel' => $this->text($row['fuel']),
            'city_l' => $this->number($row['city_l']),
            'highway_l' => $this->number($row['highway_l']),
            'combined_l' => $this->number($row['combined_l']),
            'brand_id' => (string) $row['brand_id'],
            'model_id' => (string) $row['model_id'],
            'modified' => $this->modified($row['modified'] ?? null),
        ], $rows), 'total' => $total];
    }

    /** @return array{rows:list<array<string, mixed>>, total:int} */
    public function colors(ListQuery $query): array
    {
        $where = [$this->trimmed('photo.color')." <> ''", $this->verifiedSql('photo')];
        $params = [];
        if ($query->q !== null) {
            $where[] = 'lower('.$this->trimmed('photo.color').") LIKE :q ESCAPE '\\'";
            $params['q'] = $this->like(mb_strtolower($query->q));
        }
        if (isset($query->filters['model_id'])) {
            $where[] = 'photo.model_id = :model_id';
            $params['model_id'] = (string) $query->filters['model_id'];
        }
        if (isset($query->filters['brand_id'])) {
            $where[] = 'mo.brand_id = :brand_id';
            $params['brand_id'] = (string) $query->filters['brand_id'];
        }
        if ($query->modifiedAfter !== null && $this->hasUpdatedAt('model_photos')) {
            $where[] = 'photo.updated_at > :modified_after';
            $params['modified_after'] = $query->modifiedAfter;
        }
        $from = ' FROM '.$this->table('model_photos').' photo JOIN '.$this->table('models').' mo ON mo.id = photo.model_id ';
        $total = (int) $this->value('SELECT count(*)'.$from.$this->where($where), $params);
        $order = $this->order([
            'name' => 'photo.color',
            'id' => 'photo.model_id',
            'modified' => 'photo.updated_at',
        ], $query, 'photo.color ASC, photo.model_id ASC');
        $rows = $this->select(
            'SELECT photo.model_id, photo.color, photo.verified, mo.brand_id, '.$this->modifiedSql('photo', 'model_photos').$from.$this->where($where).' ORDER BY '.$order.' LIMIT :limit OFFSET :offset',
            $params,
            $query,
        );

        return ['rows' => array_map(fn (array $row): array => [
            'id' => (string) $row['model_id'],
            'model_id' => (string) $row['model_id'],
            'brand_id' => (string) $row['brand_id'],
            'name' => $this->text($row['color']) ?? '',
            'verified' => $this->bool($row['verified']),
            'modified' => $this->modified($row['modified'] ?? null),
        ], $rows), 'total' => $total];
    }

    /** @return array<string, int|string> */
    public function coverage(): array
    {
        if ($this->driver === 'pgsql') {
            return (new CatalogRepository($this->pdo))->coverage();
        }
        $generations = $this->all('SELECT model_id, chassis_code, name FROM '.$this->table('generations'), []);
        $groups = [];
        $modelsWith = [];
        foreach ($generations as $row) {
            $modelsWith[(string) $row['model_id']] = true;
            $groups[(string) $row['model_id'].'|'.$this->groupKey($this->text($row['chassis_code']), $this->text($row['name']))] = true;
        }
        $models = (int) $this->value('SELECT count(*) FROM '.$this->table('models'), []);

        return [
            'brands_count' => (int) $this->value('SELECT count(*) FROM '.$this->table('brands'), []),
            'models_count' => $models,
            'generations_count' => count($groups),
            'models_with_generations_count' => count($modelsWith),
            'models_without_generations_count' => max(0, $models - count($modelsWith)),
            'modifications_count' => (int) $this->value('SELECT count(*) FROM '.$this->table('modifications'), []),
            'photos_count' => $this->hasTable('photos') ? (int) $this->value('SELECT count(*) FROM '.$this->table('photos'), []) : 0,
            'generations_with_photo_urls_count' => 0,
            'measured_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * @param  list<string>  $names
     * @return array{0:list<string>,1:array<string, int|string>}
     */
    private function baseFilters(string $table, string $alias, ListQuery $query, array $names): array
    {
        $where = [];
        $params = [];
        $this->applyCommon($where, $params, $alias, $table, $query, $names);

        return [$where, $params];
    }

    /**
     * @param  list<string>  $where
     * @param  array<string, int|string>  $params
     * @param  list<string>  $names
     */
    private function applyCommon(array &$where, array &$params, string $alias, string $table, ListQuery $query, array $names): void
    {
        if ($query->q !== null && $names !== []) {
            $parts = [];
            foreach ($names as $name) {
                $parts[] = 'lower('.$this->trimmed($alias.'.'.$name).") LIKE :q ESCAPE '\\'";
            }
            $where[] = '('.implode(' OR ', $parts).')';
            $params['q'] = $this->like(mb_strtolower($query->q));
        }
        if ($query->modifiedAfter !== null && $this->hasUpdatedAt($table)) {
            $where[] = $alias.'.updated_at > :modified_after';
            $params['modified_after'] = $query->modifiedAfter;
        }
    }

    /**
     * @param  list<string>  $where
     * @param  array<string, int|string>  $params
     */
    private function yearOverlap(array &$where, array &$params, string $alias, ListQuery $query): void
    {
        if (isset($query->filters['year_from'])) {
            $where[] = '('.$alias.'.year_end IS NULL OR '.$alias.'.year_end >= :year_from)';
            $params['year_from'] = (int) $query->filters['year_from'];
        }
        if (isset($query->filters['year_to'])) {
            $where[] = '('.$alias.'.year_start IS NULL OR '.$alias.'.year_start <= :year_to)';
            $params['year_to'] = (int) $query->filters['year_to'];
        }
    }

    /**
     * @return array{0:list<string>,1:array<string, int|string>}
     */
    private function modificationFilters(ListQuery $query, bool $chain): array
    {
        $where = [];
        $params = [];
        $this->applyCommon($where, $params, 'mod', 'modifications', $query, ['name']);
        $this->yearOverlap($where, $params, 'mod', $query);
        foreach (['body' => 'mod.body_type', 'drive' => 'mod.drive_type', 'transmission' => 'mod.transmission'] as $filter => $column) {
            if (! isset($query->filters[$filter])) {
                continue;
            }
            $where[] = 'lower('.$this->trimmed($column).') = :'.$filter;
            $params[$filter] = mb_strtolower((string) $query->filters[$filter]);
        }
        if (isset($query->filters['fuel'])) {
            $where[] = 'lower('.$this->spec('fuel_type').') = :fuel';
            $params['fuel'] = mb_strtolower((string) $query->filters['fuel']);
        }
        if ($chain && isset($query->filters['brand_id'])) {
            $where[] = 'mo.brand_id = :brand_id';
            $params['brand_id'] = (string) $query->filters['brand_id'];
        }
        if ($chain && isset($query->filters['model_id'])) {
            $where[] = 'mod.model_id = :model_id';
            $params['model_id'] = (string) $query->filters['model_id'];
        }

        return [$where, $params];
    }

    private function needsSpec(ListQuery $query): bool
    {
        return isset($query->filters['fuel']);
    }

    private function modificationOrder(ListQuery $query): string
    {
        return $this->order([
            'name' => 'mod.name',
            'id' => 'mod.id',
            'year_start' => 'mod.year_start',
            'engine_volume_cc' => 'mod.engine_volume_cc',
            'modified' => 'mod.updated_at',
        ], $query, $this->nullOrder('mod.year_start').', '.$this->nullOrder('mod.engine_volume_cc').', '.$this->nullOrder('mod.transmission').', mod.name ASC, mod.id ASC');
    }

    /**
     * @param  list<string>  $where
     * @param  array<string, int|string>  $params
     */
    private function countJoined(array $where, array $params, bool $models, bool $spec): int
    {
        return (int) $this->value('SELECT count(*) '.$this->joinedFrom($models, $spec).$this->where($where), $params);
    }

    /**
     * @param  list<string>  $where
     * @param  array<string, int|string>  $params
     * @return list<array<string, mixed>>
     */
    private function modificationRows(array $where, array $params, string $order, ListQuery $query, bool $withChain, bool $spec): array
    {
        $extra = $withChain
            ? ', mo.brand_id, mod.model_id, mod.generation_id, mod.drive_type, mod.body_type, '.$this->spec('fuel_type').' AS fuel'
            : '';

        return $this->select(
            'SELECT mod.id, mod.name, mod.engine_volume_cc, mod.transmission, mod.year_start, mod.year_end, '.$this->modifiedSql('mod', 'modifications').$extra.' '.$this->joinedFrom($withChain, $spec || $withChain).$this->where($where).' ORDER BY '.$order.' LIMIT :limit OFFSET :offset',
            $params,
            $query,
        );
    }

    private function joinedFrom(bool $models, bool $spec): string
    {
        $sql = ' FROM '.$this->table('modifications').' mod ';
        if ($models) {
            $sql .= ' JOIN '.$this->table('models').' mo ON mo.id = mod.model_id JOIN '.$this->table('brands').' b ON b.id = mo.brand_id ';
        }
        if ($spec && $this->specsReadable()) {
            $sql .= ' LEFT JOIN '.$this->table('modification_specs').' spec ON spec.modification_id = mod.id ';
        }

        return $sql;
    }

    private function spec(string $key): string
    {
        if (! in_array($key, ['fuel_type', 'fuel_city_l', 'fuel_highway_l', 'fuel_combined_l', 'power_hp'], true)) {
            throw new \InvalidArgumentException($key);
        }
        if (! $this->specsReadable()) {
            return 'NULL';
        }
        if ($this->driver === 'pgsql') {
            return "NULLIF(btrim(spec.specs->>'".$key."'), '')";
        }

        return "NULLIF(trim(json_extract(spec.specs, '$.".$key."')), '')";
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<array<string, mixed>>
     */
    private function groupGenerations(array $raw): array
    {
        $groups = [];
        foreach ($raw as $row) {
            $key = $this->groupKey($this->text($row['chassis_code']), $this->text($row['name']));
            $groups[$key][] = $row;
        }
        $labeled = [];
        foreach ($groups as $rows) {
            usort($rows, static fn (array $a, array $b): int => ((int) $a['id']) <=> ((int) $b['id']));
            $first = $rows[0];
            $chassis = null;
            $name = null;
            $yearStart = null;
            $yearEnd = null;
            $yearEndOpen = false;
            $bodies = [];
            $bodyMissing = false;
            $steering = [];
            $modified = null;
            foreach ($rows as $row) {
                $code = $this->text($row['chassis_code']);
                $rowName = $this->text($row['name']);
                if ($chassis === null && $code !== null) {
                    $chassis = $code;
                }
                if ($name === null && $rowName !== null) {
                    $name = $rowName;
                }
                if ($row['year_start'] !== null && $row['year_start'] !== '') {
                    $start = (int) $row['year_start'];
                    $yearStart = $yearStart === null ? $start : min($yearStart, $start);
                }
                if ($row['year_end'] === null || $row['year_end'] === '') {
                    $yearEndOpen = true;
                } else {
                    $end = (int) $row['year_end'];
                    $yearEnd = $yearEnd === null ? $end : max($yearEnd, $end);
                }
                $body = $this->text($row['body_type']);
                if ($body === null) {
                    $bodyMissing = true;
                } else {
                    $bodies[$body] = true;
                }
                $wheel = $this->text($row['steering_wheel']);
                if ($wheel !== null) {
                    $steering[$wheel] = true;
                }
                $stamp = $this->modified($row['modified'] ?? null);
                if ($stamp !== null && ($modified === null || $stamp > $modified)) {
                    $modified = $stamp;
                }
            }
            $publicName = $chassis ?? $name;
            $period = null;
            if ($yearStart !== null) {
                $period = $yearEndOpen || $yearEnd === null ? $yearStart.'–' : $yearStart.'–'.$yearEnd;
            }
            $base = implode(' · ', array_values(array_filter([$publicName, $period], static fn (?string $part): bool => $part !== null && $part !== '')));
            $id = (string) $first['id'];
            $labeled[] = [
                'id' => $id,
                'model_id' => (string) $first['model_id'],
                'slug' => $this->text($first['slug']) ?? '',
                'name' => $publicName,
                'year_start' => $yearStart,
                'year_end' => $yearEndOpen ? null : $yearEnd,
                'period_text' => $period,
                'body_type' => ! $bodyMissing && count($bodies) === 1 ? array_key_first($bodies) : null,
                'chassis_code' => $chassis,
                'steering_wheel' => count($steering) === 1 ? array_key_first($steering) : null,
                'market' => null,
                'base_label' => $base,
                'modified' => $modified,
            ];
        }
        $dupes = [];
        foreach ($labeled as $row) {
            $dupes[$row['base_label']] = ($dupes[$row['base_label']] ?? 0) + 1;
        }
        foreach ($labeled as &$row) {
            $base = $row['base_label'] !== '' ? $row['base_label'] : $row['id'];
            $row['display_label'] = ($dupes[$row['base_label']] ?? 0) > 1 ? trim($base.' · '.$row['id']) : $base;
            unset($row['base_label']);
        }

        return $labeled;
    }

    /** @return array{public_id:string, ids:list<string>, name:?string}|null */
    private function generationMembers(string $id, bool $allowSibling = false): ?array
    {
        $row = $this->one('SELECT id, model_id, name, chassis_code FROM '.$this->table('generations').' WHERE id = :id', ['id' => $id]);
        if ($row === null) {
            return null;
        }
        $siblings = $this->all(
            'SELECT id, name, chassis_code FROM '.$this->table('generations').' WHERE model_id = :model',
            ['model' => (string) $row['model_id']],
        );
        $key = $this->groupKey($this->text($row['chassis_code']), $this->text($row['name']));
        $ids = [];
        $name = null;
        foreach ($siblings as $sibling) {
            if ($this->groupKey($this->text($sibling['chassis_code']), $this->text($sibling['name'])) !== $key) {
                continue;
            }
            $ids[] = (string) $sibling['id'];
            if ($name === null) {
                $name = $this->text($sibling['name']);
            }
        }
        $public = $ids === [] ? (string) $row['id'] : (string) min(array_map('intval', $ids));
        if (! $allowSibling && (string) $row['id'] !== $public) {
            return null;
        }
        foreach ($siblings as $sibling) {
            if ((string) $sibling['id'] === $public) {
                $name = $this->text($sibling['name']);
            }
        }

        return ['public_id' => $public, 'ids' => $ids, 'name' => $name];
    }

    /**
     * @param  list<mixed>  $ids
     * @return array<string, string>
     */
    private function publicGenerationIds(array $ids): array
    {
        $ids = array_values(array_filter(array_map(static fn (mixed $id): string => trim((string) $id), $ids), static fn (string $id): bool => $id !== ''));
        $map = [];
        foreach (array_unique($ids) as $id) {
            $members = $this->generationMembers($id, true);
            if ($members !== null) {
                $map[$id] = $members['public_id'];
            }
        }

        return $map;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, int>
     */
    private function generationCounts(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $params = [];
        $holders = [];
        foreach ($ids as $index => $id) {
            $key = 'model_'.$index;
            $holders[] = ':'.$key;
            $params[$key] = $id;
        }
        $rows = $this->all('SELECT id, model_id, chassis_code, name FROM '.$this->table('generations').' WHERE model_id IN ('.implode(', ', $holders).')', $params);
        $seen = [];
        foreach ($rows as $row) {
            $seen[(string) $row['model_id'].'|'.$this->groupKey($this->text($row['chassis_code']), $this->text($row['name']))] = true;
        }
        $counts = [];
        foreach (array_keys($seen) as $key) {
            $modelId = explode('|', $key, 2)[0];
            $counts[$modelId] = ($counts[$modelId] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  list<string>  $ids
     * @return array<string, list<string>>
     */
    private function markets(array $ids): array
    {
        if ($ids === [] || ! $this->hasTable('model_markets')) {
            return [];
        }
        $params = [];
        $holders = [];
        foreach ($ids as $index => $id) {
            $key = 'market_model_'.$index;
            $holders[] = ':'.$key;
            $params[$key] = $id;
        }
        $rows = $this->all('SELECT model_id, market_slug FROM '.$this->table('model_markets').' WHERE model_id IN ('.implode(', ', $holders).') ORDER BY market_slug', $params);
        $markets = [];
        foreach ($rows as $row) {
            $slug = $this->text($row['market_slug']);
            if ($slug !== null) {
                $markets[(string) $row['model_id']][] = $slug;
            }
        }

        return $markets;
    }

    /** @param array<string, string> $map */
    private function order(array $map, ListQuery $query, string $fallback): string
    {
        if ($query->sort === null) {
            return $fallback;
        }
        $column = $map[$query->sort] ?? null;
        if ($column === null) {
            return $fallback;
        }
        $direction = $query->direction === 'desc' ? 'DESC' : 'ASC';
        if (str_starts_with($column, 'photo.')) {
            $tie = 'photo.model_id';
        } elseif (str_contains($column, '(')) {
            $tie = 'mod.id';
        } elseif (str_contains($column, '.')) {
            $tie = explode('.', $column, 2)[0].'.id';
        } else {
            $tie = 'id';
        }

        return $column.' '.$direction.', '.$tie.' ASC';
    }

    /**
     * Brand and model sorts must not append mod.id. Callers that are not modifications pass a fallback only.
     * The trailing id uses the sorted expression's table when it starts with an alias.
     */
    private function nullOrder(string $column): string
    {
        return 'CASE WHEN '.$column.' IS NULL THEN 1 ELSE 0 END, '.$column.' ASC';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, string>  $fields
     * @param  callable(array<string, mixed>, array<string, mixed>): int  $fallback
     */
    private function sortRows(array &$rows, ListQuery $query, array $fields, callable $fallback): void
    {
        usort($rows, function (array $left, array $right) use ($query, $fields, $fallback): int {
            if ($query->sort === null || ! isset($fields[$query->sort])) {
                return $fallback($left, $right);
            }
            $field = $fields[$query->sort];
            $cmp = $this->compare($left[$field] ?? null, $right[$field] ?? null);
            if ($query->direction === 'desc') {
                $cmp = -$cmp;
            }

            return $cmp !== 0 ? $cmp : strcmp((string) ($left['id'] ?? ''), (string) ($right['id'] ?? ''));
        });
    }

    private function compare(mixed $left, mixed $right): int
    {
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return 1;
        }
        if ($right === null) {
            return -1;
        }
        if (is_int($left) && is_int($right)) {
            return $left <=> $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    private function nullsLast(mixed $left, mixed $right): int
    {
        return $this->compare($left, $right);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows:list<array<string, mixed>>, total:int}
     */
    private function slice(array $rows, ListQuery $query): array
    {
        return [
            'rows' => array_values(array_slice($rows, $query->offset(), $query->limit)),
            'total' => count($rows),
        ];
    }

    private function groupKey(?string $chassis, ?string $name): string
    {
        if ($chassis !== null && $chassis !== '') {
            return 'c:'.mb_strtoupper($chassis);
        }

        return 'n:'.mb_strtolower($name ?? '');
    }

    /** @param list<string> $where */
    private function where(array $where): string
    {
        return $where === [] ? '' : ' WHERE '.implode(' AND ', $where);
    }

    /** @param list<string> $where @param array<string, int|string> $params */
    private function count(string $table, string $alias, array $where, array $params): int
    {
        return (int) $this->value('SELECT count(*) FROM '.$this->table($table).' '.$alias.$this->where($where), $params);
    }

    /** @param array<string, int|string> $params @return list<array<string, mixed>> */
    private function select(string $sql, array $params, ListQuery $query): array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue('limit', $query->limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $query->offset(), PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, int|string> $params @return list<array<string, mixed>> */
    private function all(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string, int|string> $params @return array<string, mixed>|null */
    private function one(string $sql, array $params): ?array
    {
        $rows = $this->all($sql, $params);

        return $rows[0] ?? null;
    }

    /** @param array<string, int|string> $params */
    private function value(string $sql, array $params): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchColumn();
    }

    /** @return list<int> */
    private function expandYears(mixed $start, mixed $end, int $currentYear): array
    {
        if ($start === null || $start === '' || ! is_numeric($start)) {
            return [];
        }
        $from = (int) $start;
        if ($from < 1886 || $from > $currentYear) {
            return [];
        }
        if ($end === null || $end === '' || ! is_numeric($end)) {
            $to = $currentYear;
        } else {
            $to = min((int) $end, $currentYear);
        }
        if ($to < $from) {
            return [];
        }
        $years = [];
        for ($year = $from; $year <= $to; $year++) {
            $years[] = $year;
        }

        return $years;
    }

    /** @return array<string, mixed> */
    private function storedSpecs(string $id): array
    {
        if (! $this->specsReadable()) {
            return [];
        }
        $row = $this->one(
            'SELECT specs FROM '.$this->table('modification_specs').' WHERE modification_id = :id',
            ['id' => $id],
        );
        $value = $row['specs'] ?? null;
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : null;
        }
        if (! is_array($value)) {
            return [];
        }
        $stored = [];
        foreach (['fuel_type', 'fuel_city_l', 'fuel_highway_l', 'fuel_combined_l', 'power_hp'] as $key) {
            if (array_key_exists($key, $value)) {
                $stored[$key] = $value[$key];
            }
        }

        return $stored;
    }

    private function specsReadable(): bool
    {
        return $this->hasTable('modification_specs') && $this->hasColumn('modification_specs', 'specs');
    }

    private function equipmentReadable(): bool
    {
        if (! $this->hasTable('modification_equipment')) {
            return false;
        }
        foreach (['id', 'modification_id', 'category', 'name', 'status', 'value'] as $column) {
            if (! $this->hasColumn('modification_equipment', $column)) {
                return false;
            }
        }

        return true;
    }

    private function verifiedSql(string $alias): string
    {
        if (! $this->hasColumn('model_photos', 'verified')) {
            return '1 = 0';
        }

        return $this->driver === 'pgsql' ? $alias.'.verified IS TRUE' : $alias.'.verified = 1';
    }

    private function table(string $name): string
    {
        return $this->driver === 'pgsql' ? 'public.'.$name : $name;
    }

    private function trimmed(string $expression): string
    {
        return $this->driver === 'pgsql' ? 'btrim('.$expression.')' : 'trim('.$expression.')';
    }

    private function modifiedSql(string $alias, string $table): string
    {
        if (! $this->hasUpdatedAt($table)) {
            return 'NULL AS modified';
        }

        return $alias.'.updated_at AS modified';
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = $table.'.'.$column;
        if (array_key_exists($key, $this->columns)) {
            return $this->columns[$key];
        }
        if ($this->driver === 'sqlite') {
            $rows = $this->pdo->query('PRAGMA table_info('.$table.')');
            $found = false;
            foreach ($rows ? $rows->fetchAll() : [] as $row) {
                if (($row['name'] ?? '') === $column) {
                    $found = true;
                }
            }
        } else {
            $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = 'public' AND table_name = :table AND column_name = :column");
            $stmt->execute(['table' => $table, 'column' => $column]);
            $found = $stmt->fetchColumn() !== false;
        }

        return $this->columns[$key] = $found;
    }

    private function hasTable(string $table): bool
    {
        if ($this->driver === 'sqlite') {
            $stmt = $this->pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :name");
            $stmt->execute(['name' => $table]);

            return $stmt->fetchColumn() !== false;
        }
        $stmt = $this->pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :name");
        $stmt->execute(['name' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    private function like(string $value): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value).'%';
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private function number(mixed $value): int|float|null
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $number = (float) $value;

        return floor($number) === $number ? (int) $number : $number;
    }

    private function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }

    private function modified(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $stamp = strtotime((string) $value);

        return $stamp === false ? null : gmdate('Y-m-d\TH:i:s\Z', $stamp);
    }

    /** @return list<string> */
    private function stringList(mixed $value): array
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
