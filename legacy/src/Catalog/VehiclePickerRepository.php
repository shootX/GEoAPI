<?php

declare(strict_types=1);

namespace Carapi\Catalog;

use PDO;
use PDOException;

final class VehiclePickerRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{rows:list<array<string, mixed>>, total:int} */
    public function brands(int $limit, int $offset): array
    {
        $total = $this->count('SELECT count(*) FROM public.brands');
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT id::text AS id, name
            FROM public.brands
            ORDER BY name, id
            LIMIT :limit OFFSET :offset
        SQL);
        $this->bindLimit($stmt, $limit, $offset);
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'id' => (string) $row['id'],
                'name' => self::text($row['name']) ?? '',
            ];
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function models(string $brandId, int $limit, int $offset): ?array
    {
        if (!$this->exists('public.brands', $brandId)) {
            return null;
        }
        $totalStmt = $this->pdo->prepare('SELECT count(*) FROM public.models WHERE brand_id = :id');
        $totalStmt->bindValue('id', $brandId);
        $totalStmt->execute();
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT id::text AS id, name, NULLIF(btrim(body_type), '') AS body_type
            FROM public.models
            WHERE brand_id = :id
            ORDER BY name, id
            LIMIT :limit OFFSET :offset
        SQL);
        $stmt->bindValue('id', $brandId);
        $this->bindLimit($stmt, $limit, $offset);
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'id' => (string) $row['id'],
                'name' => self::text($row['name']) ?? '',
                'body_type' => self::text($row['body_type']),
            ];
        }

        return ['rows' => $rows, 'total' => (int) $totalStmt->fetchColumn()];
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function years(string $modelId): ?array
    {
        if (!$this->exists('public.models', $modelId)) {
            return null;
        }
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT DISTINCT y.year::int AS year
            FROM public.generations g
            CROSS JOIN LATERAL generate_series(
                g.year_start,
                LEAST(
                    COALESCE(g.year_end, EXTRACT(YEAR FROM CURRENT_DATE)::int),
                    EXTRACT(YEAR FROM CURRENT_DATE)::int
                )
            ) AS y(year)
            WHERE g.model_id = :id
              AND g.year_start IS NOT NULL
              AND g.year_start <= EXTRACT(YEAR FROM CURRENT_DATE)::int
              AND COALESCE(g.year_end, g.year_start) >= g.year_start
            ORDER BY year DESC
        SQL);
        $stmt->bindValue('id', $modelId);
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $year = (string) $row['year'];
            $rows[] = ['id' => $year, 'name' => $year];
        }

        return ['rows' => $rows, 'total' => count($rows)];
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function generations(string $modelId, int $year, int $limit, int $offset): ?array
    {
        if (!$this->exists('public.models', $modelId)) {
            return null;
        }
        $filter = <<<'SQL'
            FROM public.generations g
            WHERE g.model_id = :id
              AND g.year_start IS NOT NULL
              AND g.year_start <= :year_from
              AND (g.year_end IS NULL OR g.year_end >= :year_to)
        SQL;
        $totalStmt = $this->pdo->prepare('SELECT count(*) ' . $filter);
        $totalStmt->bindValue('id', $modelId);
        $totalStmt->bindValue('year_from', $year, PDO::PARAM_INT);
        $totalStmt->bindValue('year_to', $year, PDO::PARAM_INT);
        $totalStmt->execute();
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT g.id::text AS id,
                   NULLIF(btrim(g.name), '') AS name,
                   NULLIF(btrim(g.chassis_code), '') AS code,
                   g.year_start,
                   g.year_end
            $filter
            ORDER BY g.year_start, g.name NULLS LAST, g.id
            LIMIT :limit OFFSET :offset
        SQL);
        $stmt->bindValue('id', $modelId);
        $stmt->bindValue('year_from', $year, PDO::PARAM_INT);
        $stmt->bindValue('year_to', $year, PDO::PARAM_INT);
        $this->bindLimit($stmt, $limit, $offset);
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $name = self::text($row['name']);
            $code = self::text($row['code']);
            $start = self::year($row['year_start']);
            $end = self::year($row['year_end']);
            $rows[] = [
                'id' => (string) $row['id'],
                'name' => $name,
                'code' => $code,
                'year_start' => $start,
                'year_end' => $end,
                'label' => self::join([
                    $name ?? $code ?? (string) $row['id'],
                    $name !== null ? $code : null,
                    self::span($start, $end),
                ]),
            ];
        }

        return ['rows' => $rows, 'total' => (int) $totalStmt->fetchColumn()];
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function modifications(string $generationId, int $year, int $limit, int $offset): ?array
    {
        if (!$this->exists('public.generations', $generationId)) {
            return null;
        }
        $filter = <<<'SQL'
            FROM public.modifications mod
            WHERE mod.generation_id = :id
              AND (mod.year_start IS NULL OR mod.year_start <= :year_from)
              AND (mod.year_end IS NULL OR mod.year_end >= :year_to)
        SQL;
        $totalStmt = $this->pdo->prepare('SELECT count(*) ' . $filter);
        $totalStmt->bindValue('id', $generationId);
        $totalStmt->bindValue('year_from', $year, PDO::PARAM_INT);
        $totalStmt->bindValue('year_to', $year, PDO::PARAM_INT);
        $totalStmt->execute();
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT page.id,
                   page.name,
                   page.engine_volume_cc,
                   page.transmission,
                   page.drive_type,
                   NULLIF(spec.specs->>'power_hp', '') AS power_hp
            FROM (
                SELECT mod.id::text AS id,
                       btrim(mod.name) AS name,
                       mod.engine_volume_cc,
                       NULLIF(btrim(mod.transmission), '') AS transmission,
                       NULLIF(btrim(mod.drive_type), '') AS drive_type
                $filter
                ORDER BY mod.name, mod.id
                LIMIT :limit OFFSET :offset
            ) page
            LEFT JOIN public.modification_specs spec ON spec.modification_id = page.id::bigint
            ORDER BY page.name, page.id
        SQL);
        $stmt->bindValue('id', $generationId);
        $stmt->bindValue('year_from', $year, PDO::PARAM_INT);
        $stmt->bindValue('year_to', $year, PDO::PARAM_INT);
        $this->bindLimit($stmt, $limit, $offset);
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $name = self::text($row['name']) ?? '';
            $volume = self::year($row['engine_volume_cc']);
            $power = self::number($row['power_hp']);
            $transmission = self::transmission(self::text($row['transmission']));
            $drive = self::drive(self::text($row['drive_type']));
            $rows[] = [
                'id' => (string) $row['id'],
                'name' => $name,
                'engine_volume_cc' => $volume,
                'power_hp' => $power,
                'transmission' => $transmission,
                'drive' => $drive,
                'label' => self::join([
                    $name,
                    self::liters($volume),
                    $power === null ? null : self::trimNumber($power) . ' hp',
                    $transmission,
                    $drive,
                ]),
            ];
        }

        return ['rows' => $rows, 'total' => (int) $totalStmt->fetchColumn()];
    }

    /** @return array<string, mixed>|null */
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
                   b.name AS brand_name,
                   mo.id::text AS model_id,
                   mo.name AS model_name,
                   NULLIF(btrim(mo.body_type), '') AS model_body_type,
                   mo.body_types,
                   g.id::text AS generation_id,
                   NULLIF(btrim(g.name), '') AS generation_name,
                   NULLIF(btrim(g.chassis_code), '') AS generation_code,
                   NULLIF(btrim(g.body_type), '') AS generation_body_type,
                   g.year_start AS generation_year_start,
                   g.year_end AS generation_year_end,
                   spec.specs,
                   EXISTS (
                       SELECT 1
                       FROM public.model_photos photo
                       WHERE photo.model_id = mo.id
                         AND photo.verified = true
                         AND photo.image IS NOT NULL
                   ) AS has_photo
            FROM public.modifications mod
            JOIN public.models mo ON mo.id = mod.model_id
            JOIN public.brands b ON b.id = mo.brand_id
            LEFT JOIN public.generations g ON g.id = mod.generation_id
            LEFT JOIN public.modification_specs spec ON spec.modification_id = mod.id
            WHERE mod.id = :id
        SQL);
        $stmt->bindValue('id', $id);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        $specs = self::jsonObject($row['specs']);
        $modStart = self::year($row['year_start']);
        $modEnd = self::year($row['year_end']);
        $genStart = self::year($row['generation_year_start']);
        $genEnd = self::year($row['generation_year_end']);
        $yearStart = $modStart ?? $genStart;
        $yearEnd = $modStart !== null || $modEnd !== null ? $modEnd : $genEnd;
        $generationId = self::text($row['generation_id']);
        $body = self::text($row['body_type'])
            ?? self::text($row['generation_body_type'])
            ?? self::text($row['model_body_type']);
        if ($body === null) {
            $types = self::pgArray($row['body_types']);
            $body = $types === [] ? null : implode(', ', $types);
        }

        return [
            'id' => (string) $row['id'],
            'name' => self::text($row['name']) ?? '',
            'brand' => [
                'id' => (string) $row['brand_id'],
                'name' => self::text($row['brand_name']) ?? '',
            ],
            'model' => [
                'id' => (string) $row['model_id'],
                'name' => self::text($row['model_name']) ?? '',
            ],
            'generation' => $generationId === null ? null : [
                'id' => $generationId,
                'name' => self::text($row['generation_name']),
                'code' => self::text($row['generation_code']),
                'year_start' => $genStart,
                'year_end' => $genEnd,
            ],
            'year_start' => $yearStart,
            'year_end' => $yearEnd,
            'body_type' => $body,
            'engine' => [
                'volume_cc' => self::year($row['engine_volume_cc']),
                'type' => self::specText($specs, 'engine_type'),
            ],
            'power_hp' => self::specNumber($specs, 'power_hp'),
            'power_kw' => self::specNumber($specs, 'power_kw'),
            'torque_nm' => self::specNumber($specs, 'torque_nm'),
            'fuel' => self::specText($specs, 'fuel_type'),
            'transmission' => self::transmission(self::text($row['transmission'])),
            'drive' => self::drive(self::text($row['drive_type'])),
            'consumption' => [
                'city_l' => self::specNumber($specs, 'fuel_city_l'),
                'highway_l' => self::specNumber($specs, 'fuel_highway_l'),
                'combined_l' => self::specNumber($specs, 'fuel_combined_l'),
            ],
            'dimensions_mm' => [
                'length' => self::specNumber($specs, 'length_mm'),
                'width' => self::specNumber($specs, 'width_mm'),
                'height' => self::specNumber($specs, 'height_mm'),
            ],
            'weight_kg' => self::specNumber($specs, 'curb_weight_kg'),
            'seats' => self::specNumber($specs, 'seats'),
            'tank_l' => self::specNumber($specs, 'tank_l'),
            'max_speed_kmh' => self::specNumber($specs, 'max_speed_kmh'),
            'trunk_l' => self::specNumber($specs, 'trunk_l'),
            'clearance_mm' => self::specNumber($specs, 'clearance_mm'),
            'front_brakes' => self::specText($specs, 'front_brakes'),
            'rear_brakes' => self::specText($specs, 'rear_brakes'),
            'front_suspension' => self::specText($specs, 'front_suspension'),
            'rear_suspension' => self::specText($specs, 'rear_suspension'),
            'front_tires' => self::specText($specs, 'front_tires'),
            'rear_tires' => self::specText($specs, 'rear_tires'),
            'has_photo' => $row['has_photo'] === true || $row['has_photo'] === 't' || $row['has_photo'] === '1' || $row['has_photo'] === 1,
            'equipment' => $this->equipment($id),
        ];
    }

    /** @return array{mime:string, bytes:string}|null */
    public function photo(string $modelId): ?array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT mime, image
            FROM public.model_photos
            WHERE model_id = :id
              AND verified = true
              AND image IS NOT NULL
        SQL);
        $stmt->bindValue('id', $modelId);
        $stmt->execute();
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }
        $mime = self::text($row['mime']);
        $bytes = self::bytes($row['image']);
        if ($mime === null || $bytes === null || $bytes === '') {
            return null;
        }
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return null;
        }

        return ['mime' => $mime, 'bytes' => $bytes];
    }

    /** @return array{standard:list<array<string, string|null>>, optional:list<array<string, string|null>>, not_available:list<array<string, string|null>>} */
    private function equipment(string $modificationId): array
    {
        $stmt = $this->pdo->prepare(<<<'SQL'
            SELECT NULLIF(btrim(category), '') AS category,
                   NULLIF(btrim(name), '') AS name,
                   NULLIF(btrim(status), '') AS status,
                   NULLIF(btrim(value), '') AS value
            FROM public.modification_equipment
            WHERE modification_id = :id
            ORDER BY category NULLS LAST, name NULLS LAST, id
        SQL);
        $stmt->bindValue('id', $modificationId);
        $stmt->execute();
        $groups = ['standard' => [], 'optional' => [], 'not_available' => []];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $section = match (self::text($row['status'])) {
                'included' => 'standard',
                'option' => 'optional',
                'not_available' => 'not_available',
                default => null,
            };
            if ($section === null) {
                continue;
            }
            $groups[$section][] = [
                'section' => self::text($row['category']) ?? 'General',
                'name' => self::text($row['name']) ?? '',
                'value' => self::text($row['value']),
            ];
        }

        return $groups;
    }

    private function exists(string $table, string $id): bool
    {
        if (!in_array($table, ['public.brands', 'public.models', 'public.generations'], true)) {
            throw new UnavailableException();
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM ' . $table . ' WHERE id = :id');
        $stmt->bindValue('id', $id);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    private function count(string $sql): int
    {
        try {
            return (int) $this->pdo->query($sql)->fetchColumn();
        } catch (PDOException) {
            error_log('[catalog] query failed');
            throw new UnavailableException();
        }
    }

    private function bindLimit(\PDOStatement $stmt, int $limit, int $offset): void
    {
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    }

    /** AMT is an automated manual gearbox, shown as robot. */
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

    private static function liters(?int $cc): ?string
    {
        if ($cc === null || $cc <= 0) {
            return null;
        }

        return number_format($cc / 1000, 1, '.', '') . ' L';
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
        if ($start === null && $end === null) {
            return null;
        }
        if ($start === null) {
            return (string) $end;
        }
        if ($end === null) {
            return $start . '–';
        }

        return $start . '–' . $end;
    }

    /** @param array<string, mixed> $specs */
    private static function specText(array $specs, string $key): ?string
    {
        return self::text($specs[$key] ?? null);
    }

    /** @param array<string, mixed> $specs */
    private static function specNumber(array $specs, string $key): int|float|null
    {
        return array_key_exists($key, $specs) ? self::number($specs[$key]) : null;
    }

    /** @return array<string, mixed> */
    private static function jsonObject(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function bytes(mixed $value): ?string
    {
        if (is_resource($value)) {
            $bytes = stream_get_contents($value);

            return is_string($bytes) ? $bytes : null;
        }
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (str_starts_with($value, '\\x')) {
            $bin = hex2bin(substr($value, 2));

            return $bin === false ? null : $bin;
        }

        return $value;
    }

    /** @return list<string> */
    private static function pgArray(mixed $value): array
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $item) {
                $text = self::text($item);
                if ($text !== null) {
                    $out[] = $text;
                }
            }

            return $out;
        }
        if (!is_string($value)) {
            return [];
        }
        $value = trim($value);
        if ($value === '' || $value === '{}' || $value[0] !== '{') {
            return [];
        }
        $inner = substr($value, 1, -1);
        if ($inner === '') {
            return [];
        }
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|([^,]+)/', $inner, $matches, PREG_SET_ORDER);
        $out = [];
        foreach ($matches as $part) {
            $text = ($part[1] ?? '') !== '' ? stripcslashes($part[1]) : trim($part[2] ?? '');
            if ($text !== '' && strtoupper($text) !== 'NULL') {
                $out[] = $text;
            }
        }

        return $out;
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
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private static function number(mixed $value): int|float|null
    {
        if ($value === null || $value === '' || !is_numeric($value)) {
            return null;
        }
        $number = (float) $value;
        if (floor($number) === $number && abs($number) < 1e15) {
            return (int) $number;
        }

        return $number;
    }

    private static function trimNumber(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        $text = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $text === '' ? '0' : $text;
    }
}
