<?php

declare(strict_types=1);

namespace Carapi\Catalog;

use PDO;
use PDOException;

/**
 * Years, generations, and modifications shared by the public API, the picker, and the widget.
 * Year numbers follow the same bounds as GET /api/v1/models/{id}/years.
 */
final class PickerSelection
{
    /**
     * @return list<int>
     */
    public static function expand(mixed $start, mixed $end, int $currentYear): array
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

    /**
     * @return array{rows:list<array{id:string,name:string,bodies:list<string>}>, total:int}|null
     */
    public static function years(PDO $pdo, string $modelId, ?int $currentYear = null): ?array
    {
        if (! self::modelExists($pdo, $modelId)) {
            return null;
        }
        $currentYear ??= (int) gmdate('Y');
        $covered = [];
        foreach (self::spans($pdo, 'generations', $modelId) as $span) {
            self::cover($covered, $span, $currentYear);
        }
        foreach (self::spans($pdo, 'modifications', $modelId) as $span) {
            self::cover($covered, $span, $currentYear);
        }
        krsort($covered);
        $rows = [];
        foreach ($covered as $year => $bodies) {
            $rows[] = [
                'id' => (string) $year,
                'name' => (string) $year,
                'bodies' => self::bodies(array_keys($bodies)),
            ];
        }

        return ['rows' => $rows, 'total' => count($rows)];
    }

    /**
     * Public generation id is the smallest sibling id, matching the catalog API.
     *
     * @return array{rows:list<array<string, mixed>>, total:int}|null
     */
    public static function generations(PDO $pdo, string $modelId, int $year): ?array
    {
        if (! self::modelExists($pdo, $modelId)) {
            return null;
        }
        $columns = self::columns($pdo, 'generations');
        if ($columns === []) {
            return ['rows' => [], 'total' => 0];
        }
        $rows = self::select($pdo, 'SELECT '.self::generationSql($columns).' FROM '.self::table($pdo, 'generations').' WHERE model_id = :id', ['id' => $modelId]);
        $groups = [];
        foreach ($rows as $row) {
            $key = self::groupKey(self::text($row['chassis_code'] ?? null), self::text($row['name'] ?? null), (string) $row['id']);
            $id = (int) $row['id'];
            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'id' => $id,
                    'slug' => self::text($row['slug'] ?? null),
                    'name' => self::text($row['name'] ?? null),
                    'code' => self::text($row['chassis_code'] ?? null),
                    'body_type' => self::text($row['body_type'] ?? null),
                    'market' => self::text($row['market'] ?? null),
                    'steering' => self::text($row['steering_wheel'] ?? null),
                    'year_start' => null,
                    'year_end' => null,
                    'open_end' => false,
                    'members' => [],
                ];
            }
            $group = &$groups[$key];
            $group['members'][] = $id;
            if ($id < $group['id']) {
                $group['id'] = $id;
                $group['slug'] = self::text($row['slug'] ?? null);
                $group['name'] = self::text($row['name'] ?? null);
                $group['code'] = self::text($row['chassis_code'] ?? null);
                $group['body_type'] = self::text($row['body_type'] ?? null);
                $group['market'] = self::text($row['market'] ?? null);
                $group['steering'] = self::text($row['steering_wheel'] ?? null);
            }
            $start = self::intOrNull($row['year_start'] ?? null);
            if ($start !== null && ($group['year_start'] === null || $start < $group['year_start'])) {
                $group['year_start'] = $start;
            }
            if (self::intOrNull($row['year_end'] ?? null) === null) {
                $group['open_end'] = true;
                $group['year_end'] = null;
            } elseif (! $group['open_end'] && ($group['year_end'] === null || (int) $row['year_end'] > $group['year_end'])) {
                $group['year_end'] = (int) $row['year_end'];
            }
            unset($group);
        }
        $mods = self::modificationSpans($pdo, $modelId);
        $listed = [];
        foreach ($groups as $group) {
            $end = $group['open_end'] ? null : $group['year_end'];
            $covers = self::generationCovers($group['year_start'], $end, $year);
            if (! $covers) {
                foreach ($mods as $mod) {
                    if (! in_array((int) $mod['generation_id'], $group['members'], true)) {
                        continue;
                    }
                    if (self::rowCovers($mod['year_start'], $mod['year_end'], $year)) {
                        $covers = true;
                        break;
                    }
                }
            }
            if (! $covers) {
                continue;
            }
            $listed[] = self::generationRow($group, $end);
        }
        usort($listed, static function (array $left, array $right): int {
            return ($left['year_start'] ?? PHP_INT_MAX) <=> ($right['year_start'] ?? PHP_INT_MAX)
                ?: strcmp((string) $left['label'], (string) $right['label'])
                ?: ((int) $left['id']) <=> ((int) $right['id']);
        });
        $counts = [];
        foreach ($listed as $row) {
            $counts[$row['base']] = ($counts[$row['base']] ?? 0) + 1;
        }
        foreach ($listed as &$row) {
            if ($counts[$row['base']] > 1) {
                $row['label'] = self::join([$row['base'], $row['id']]);
                $row['display_label'] = $row['label'];
            }
            unset($row['base']);
        }
        unset($row);

        return ['rows' => $listed, 'total' => count($listed)];
    }

    /**
     * @return array{rows:list<array<string, mixed>>, total:int}|null
     */
    public static function modifications(PDO $pdo, string $generationId, int $year): ?array
    {
        $generation = self::one($pdo, 'SELECT id, model_id, name, chassis_code FROM '.self::table($pdo, 'generations').' WHERE id = :id', ['id' => $generationId]);
        if ($generation === null) {
            return null;
        }
        $modelId = (string) $generation['model_id'];
        $key = self::groupKey(self::text($generation['chassis_code'] ?? null), self::text($generation['name'] ?? null), (string) $generation['id']);
        $members = self::select($pdo, 'SELECT id, name, chassis_code FROM '.self::table($pdo, 'generations').' WHERE model_id = :id', ['id' => $modelId]);
        $ids = [];
        $publicId = null;
        foreach ($members as $member) {
            if (self::groupKey(self::text($member['chassis_code'] ?? null), self::text($member['name'] ?? null), (string) $member['id']) !== $key) {
                continue;
            }
            $ids[] = (int) $member['id'];
            $publicId = $publicId === null ? (int) $member['id'] : min($publicId, (int) $member['id']);
        }
        if ($publicId === null || (string) $publicId !== $generationId) {
            return null;
        }
        if (! self::hasTable($pdo, 'modifications') || $ids === []) {
            return ['rows' => [], 'total' => 0];
        }
        $placeholders = [];
        $params = ['model' => $modelId];
        foreach ($ids as $index => $id) {
            $placeholders[] = ':g'.$index;
            $params['g'.$index] = $id;
        }
        $specs = self::hasColumn($pdo, 'modification_specs', 'specs')
            ? 'LEFT JOIN '.self::table($pdo, 'modification_specs').' spec ON spec.modification_id = mod.id'
            : '';
        $specColumn = $specs === '' ? 'NULL AS specs' : 'spec.specs';
        $sql = 'SELECT mod.id, mod.name, mod.year_start, mod.year_end, mod.engine_volume_cc, mod.transmission, mod.drive_type, '.$specColumn
            .' FROM '.self::table($pdo, 'modifications').' mod '.$specs
            .' WHERE mod.model_id = :model AND mod.generation_id IN ('.implode(', ', $placeholders).')'
            .' ORDER BY mod.name, mod.id';
        $rows = [];
        foreach (self::select($pdo, $sql, $params) as $row) {
            if (! self::rowCovers(self::intOrNull($row['year_start'] ?? null), self::intOrNull($row['year_end'] ?? null), $year)) {
                continue;
            }
            $specs = self::allowSpecs($row['specs'] ?? null);
            $name = self::text($row['name']) ?? '';
            $volume = self::intOrNull($row['engine_volume_cc'] ?? null);
            $power = self::number($specs['power_hp'] ?? null);
            $transmission = self::transmission(self::text($row['transmission'] ?? null));
            $drive = self::drive(self::text($row['drive_type'] ?? null));
            $label = self::join([
                $name,
                self::liters($volume),
                $power === null ? null : self::trimNumber($power).' hp',
                $transmission,
                $drive,
            ]);
            $rows[] = [
                'id' => (string) $row['id'],
                'name' => $name,
                'display_label' => $label,
                'label' => $label,
                'engine_volume_cc' => $volume,
                'power_hp' => $power,
                'transmission' => $transmission,
                'drive' => $drive,
            ];
        }

        return ['rows' => $rows, 'total' => count($rows)];
    }

    /**
     * @return array{standard:list<array<string, mixed>>, optional:list<array<string, mixed>>, not_available:list<array<string, mixed>>, unknown:list<array<string, mixed>>}
     */
    public static function equipment(PDO $pdo, string $modificationId): array
    {
        $groups = ['standard' => [], 'optional' => [], 'not_available' => [], 'unknown' => []];
        foreach (['id', 'modification_id', 'category', 'name', 'status', 'value'] as $column) {
            if (! self::hasColumn($pdo, 'modification_equipment', $column)) {
                return $groups;
            }
        }
        $rows = self::select(
            $pdo,
            'SELECT id, category, name, status, value FROM '.self::table($pdo, 'modification_equipment').' WHERE modification_id = :id ORDER BY id',
            ['id' => $modificationId],
        );
        $seen = [];
        foreach ($rows as $row) {
            $name = self::text($row['name'] ?? null);
            if ($name === null) {
                continue;
            }
            $status = self::text($row['status'] ?? null);
            $bucket = match ($status) {
                'included' => 'standard',
                'option' => 'optional',
                'not_available' => 'not_available',
                default => 'unknown',
            };
            $category = self::text($row['category'] ?? null) ?? '';
            $dedupe = $category.'|'.$name.'|'.$bucket;
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $groups[$bucket][] = [
                'id' => (string) $row['id'],
                'section' => $category !== '' ? $category : 'General',
                'category' => $category !== '' ? $category : null,
                'name' => $name,
                'value' => self::text($row['value'] ?? null),
            ];
        }

        return $groups;
    }

    /** @return array<string, mixed> */
    public static function allowSpecs(mixed $value): array
    {
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

    /**
     * @param  array<int, array<string, true>>  $covered
     * @param  array<string, mixed>  $span
     */
    private static function cover(array &$covered, array $span, int $currentYear): void
    {
        $body = self::text($span['body_type'] ?? null);
        $body = $body === null ? null : strtolower($body);
        foreach (self::expand($span['year_start'] ?? null, $span['year_end'] ?? null, $currentYear) as $year) {
            if (! isset($covered[$year])) {
                $covered[$year] = [];
            }
            if ($body !== null) {
                $covered[$year][$body] = true;
            }
        }
    }

    /** @return list<array<string, mixed>> */
    private static function spans(PDO $pdo, string $table, string $modelId): array
    {
        if (! self::hasColumn($pdo, $table, 'year_start') || ! self::hasColumn($pdo, $table, 'model_id')) {
            return [];
        }
        $body = self::hasColumn($pdo, $table, 'body_type') ? 'body_type' : 'NULL AS body_type';

        return self::select(
            $pdo,
            'SELECT year_start, year_end, '.$body.' FROM '.self::table($pdo, $table).' WHERE model_id = :id',
            ['id' => $modelId],
        );
    }

    /** @return list<array{generation_id:int, year_start:?int, year_end:?int}> */
    private static function modificationSpans(PDO $pdo, string $modelId): array
    {
        if (! self::hasColumn($pdo, 'modifications', 'generation_id') || ! self::hasColumn($pdo, 'modifications', 'year_start')) {
            return [];
        }
        $out = [];
        foreach (self::select($pdo, 'SELECT generation_id, year_start, year_end FROM '.self::table($pdo, 'modifications').' WHERE model_id = :id', ['id' => $modelId]) as $row) {
            if ($row['generation_id'] === null || $row['generation_id'] === '') {
                continue;
            }
            $out[] = [
                'generation_id' => (int) $row['generation_id'],
                'year_start' => self::intOrNull($row['year_start'] ?? null),
                'year_end' => self::intOrNull($row['year_end'] ?? null),
            ];
        }

        return $out;
    }

    /** @param array<string, mixed> $group @return array<string, mixed> */
    private static function generationRow(array $group, ?int $end): array
    {
        $name = $group['name'];
        $code = $group['code'];
        $span = self::span($group['year_start'], $end);
        $base = self::join([
            $name ?? $code ?? (string) $group['id'],
            $name !== null ? $code : null,
            $span,
            $group['market'],
            $group['steering'],
        ]);

        return [
            'id' => (string) $group['id'],
            'name' => $name,
            'code' => $code,
            'slug' => $group['slug'],
            'year_start' => $group['year_start'],
            'year_end' => $end,
            'market' => $group['market'],
            'steering' => $group['steering'],
            'body_type' => $group['body_type'],
            'base' => $base,
            'label' => $base,
            'display_label' => $base,
        ];
    }

    /** @param list<string> $columns */
    private static function generationSql(array $columns): string
    {
        $have = array_flip($columns);
        $pick = static function (string $name) use ($have): string {
            return isset($have[$name]) ? $name : 'NULL AS '.$name;
        };

        return implode(', ', [
            'id',
            $pick('model_id'),
            $pick('slug'),
            $pick('name'),
            $pick('chassis_code'),
            $pick('body_type'),
            $pick('year_start'),
            $pick('year_end'),
            $pick('market'),
            $pick('steering_wheel'),
        ]);
    }

    private static function generationCovers(?int $start, ?int $end, int $year): bool
    {
        if ($start === null || $start > $year) {
            return false;
        }

        return $end === null || $end >= $year;
    }

    private static function rowCovers(?int $start, ?int $end, int $year): bool
    {
        if ($end !== null && $start !== null && $end < $start) {
            return false;
        }
        if ($start !== null && $start > $year) {
            return false;
        }
        if ($end !== null && $end < $year) {
            return false;
        }

        return true;
    }

    private static function modelExists(PDO $pdo, string $modelId): bool
    {
        if (! self::hasTable($pdo, 'models')) {
            return false;
        }
        $row = self::one($pdo, 'SELECT id FROM '.self::table($pdo, 'models').' WHERE id = :id', ['id' => $modelId]);

        return $row !== null;
    }

    private static function groupKey(?string $chassis, ?string $name, string $id): string
    {
        if ($chassis !== null && $chassis !== '') {
            return 'c:'.mb_strtoupper($chassis);
        }
        if ($name !== null && $name !== '') {
            return 'n:'.mb_strtolower($name);
        }

        return 'i:'.$id;
    }

    /** @param list<string|null> $parts */
    private static function join(array $parts): string
    {
        $kept = [];
        foreach ($parts as $part) {
            if ($part !== null && $part !== '') {
                $kept[] = $part;
            }
        }

        return implode(' · ', $kept);
    }

    private static function span(?int $start, ?int $end): ?string
    {
        if ($start === null) {
            return null;
        }
        if ($end === null) {
            return $start.'–';
        }

        return $start.'–'.$end;
    }

    /** @param list<string> $bodies @return list<string> */
    private static function bodies(array $bodies): array
    {
        $order = ['sedan', 'hatchback', 'liftback', 'wagon', 'coupe', 'minivan', 'van', 'pickup'];
        $rank = array_flip($order);
        $bodies = array_values(array_unique($bodies));
        usort($bodies, static function (string $left, string $right) use ($rank): int {
            return ($rank[$left] ?? 100) <=> ($rank[$right] ?? 100) ?: strcmp($left, $right);
        });

        return $bodies;
    }

    private static function liters(?int $cc): ?string
    {
        if ($cc === null || $cc <= 0) {
            return null;
        }

        return number_format($cc / 1000, 1, '.', '').' L';
    }

    private static function transmission(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $value = strtoupper($raw);
        if (str_starts_with($value, 'AMT')) {
            return 'robot';
        }
        if (str_contains($value, 'CVT')) {
            return 'CVT';
        }
        if (str_starts_with($value, 'MT')) {
            return 'manual';
        }
        if (str_starts_with($value, 'AT') || str_contains($value, 'SINGLE-SPEED')) {
            return 'automatic';
        }

        return $raw;
    }

    private static function drive(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $value = strtoupper($raw);
        if (str_contains($value, '4WD') || str_contains($value, 'AWD') || str_contains($value, '4X4')) {
            return 'AWD 4x4';
        }
        if (preg_match('/\bFWD\b/', $value) === 1) {
            return 'FWD';
        }
        if (preg_match('/\bRWD\b/', $value) === 1) {
            return 'RWD';
        }

        return $raw;
    }

    private static function trimNumber(int|float $value): string
    {
        $text = rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');

        return $text === '' ? '0' : $text;
    }

    private static function number(mixed $value): int|float|null
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        if (! is_finite($number)) {
            return null;
        }

        return fmod($number, 1.0) === 0.0 ? (int) $number : $number;
    }

    /** @param array<string, int|string> $params @return list<array<string, mixed>> */
    private static function select(PDO $pdo, string $sql, array $params): array
    {
        try {
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll();

            return is_array($rows) ? $rows : [];
        } catch (PDOException) {
            return [];
        }
    }

    /** @param array<string, int|string> $params @return array<string, mixed>|null */
    private static function one(PDO $pdo, string $sql, array $params): ?array
    {
        return self::select($pdo, $sql, $params)[0] ?? null;
    }

    private static function table(PDO $pdo, string $name): string
    {
        return self::driver($pdo) === 'pgsql' ? 'public.'.$name : $name;
    }

    private static function driver(PDO $pdo): string
    {
        return (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private static function hasTable(PDO $pdo, string $table): bool
    {
        if (! self::allowed($table)) {
            return false;
        }
        if (self::driver($pdo) === 'pgsql') {
            $row = self::one($pdo, "SELECT 1 AS ok FROM information_schema.tables WHERE table_schema = 'public' AND table_name = :t", ['t' => $table]);

            return $row !== null;
        }
        $row = self::one($pdo, "SELECT 1 AS ok FROM sqlite_master WHERE type = 'table' AND name = :t", ['t' => $table]);

        return $row !== null;
    }

    private static function hasColumn(PDO $pdo, string $table, string $column): bool
    {
        return in_array($column, self::columns($pdo, $table), true);
    }

    /** @return list<string> */
    private static function columns(PDO $pdo, string $table): array
    {
        if (! self::allowed($table) || ! self::hasTable($pdo, $table)) {
            return [];
        }
        if (self::driver($pdo) === 'pgsql') {
            $rows = self::select($pdo, "SELECT column_name AS name FROM information_schema.columns WHERE table_schema = 'public' AND table_name = :t", ['t' => $table]);

            return array_map(static fn (array $row): string => (string) $row['name'], $rows);
        }
        $rows = self::select($pdo, 'PRAGMA table_info('.$table.')', []);

        return array_map(static fn (array $row): string => (string) $row['name'], $rows);
    }

    private static function allowed(string $table): bool
    {
        return in_array($table, ['models', 'generations', 'modifications', 'modification_specs', 'modification_equipment'], true);
    }

    private static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
