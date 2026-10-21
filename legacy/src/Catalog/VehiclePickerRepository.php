<?php

declare(strict_types=1);

namespace Carapi\Catalog;

use PDO;
use PDOException;

final class VehiclePickerRepository
{
    public function __construct(private PDO $pdo) {}

    /** @return array{rows:list<array<string, mixed>>, total:int} */
    public function brands(int $limit, int $offset, string $term = ''): array
    {
        $cacheKey = 'brands:'.$limit.':'.$offset.':'.$term;
        $cached = $this->recall($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $where = '';
        if ($term !== '') {
            $where = 'WHERE name ILIKE :term OR slug ILIKE :term';
        }
        $totalStmt = $this->pdo->prepare('SELECT count(*) FROM public.brands '.$where);
        if ($term !== '') {
            $totalStmt->bindValue('term', '%'.$term.'%');
        }
        $totalStmt->execute();
        $total = (int) $totalStmt->fetchColumn();
        $stmt = $this->pdo->prepare(<<<SQL
            SELECT id::text AS id, name
            FROM public.brands
            $where
            ORDER BY name, id
            LIMIT :limit OFFSET :offset
        SQL);
        if ($term !== '') {
            $stmt->bindValue('term', '%'.$term.'%');
        }
        $this->bindLimit($stmt, $limit, $offset);
        $stmt->execute();
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'id' => (string) $row['id'],
                'name' => self::text($row['name']) ?? '',
            ];
        }

        return $this->store($cacheKey, ['rows' => $rows, 'total' => $total]);
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function models(string $brandId, int $limit, int $offset): ?array
    {
        $cacheKey = 'models:'.$brandId.':'.$limit.':'.$offset;
        $cached = $this->recall($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        if (! $this->exists('public.brands', $brandId)) {
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

        return $this->store($cacheKey, ['rows' => $rows, 'total' => (int) $totalStmt->fetchColumn()]);
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function years(string $modelId): ?array
    {
        $cacheKey = 'years:v2:'.$modelId;
        $cached = $this->recall($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $choices = PickerSelection::years($this->pdo, $modelId);
        if ($choices === null) {
            return null;
        }
        $rows = [];
        foreach ($choices['rows'] as $row) {
            $rows[] = ['id' => $row['id'], 'name' => $row['name']];
        }

        return $this->store($cacheKey, ['rows' => $rows, 'total' => count($rows)]);
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function generations(string $modelId, int $year, int $limit, int $offset): ?array
    {
        $cacheKey = 'generations:v2:'.$modelId.':'.$year.':'.$limit.':'.$offset;
        $cached = $this->recall($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $choices = PickerSelection::generations($this->pdo, $modelId, $year);
        if ($choices === null) {
            return null;
        }
        $rows = array_slice($choices['rows'], $offset, $limit);

        return $this->store($cacheKey, ['rows' => $rows, 'total' => $choices['total']]);
    }

    /** @return array{rows:list<array<string, mixed>>, total:int}|null */
    public function modifications(string $generationId, int $year, int $limit, int $offset): ?array
    {
        $choices = PickerSelection::modifications($this->pdo, $generationId, $year);
        if ($choices === null) {
            return null;
        }

        return [
            'rows' => array_slice($choices['rows'], $offset, $limit),
            'total' => $choices['total'],
        ];
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
        if (! is_array($row)) {
            return null;
        }
        $specs = PickerSelection::allowSpecs($row['specs'] ?? null);
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
        $matchesPhoto = $this->photoMatchesGeneration((string) $row['model_id'], $generationId);

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
                'type' => null,
            ],
            'power_hp' => self::number($specs['power_hp'] ?? null),
            'power_kw' => null,
            'torque_nm' => null,
            'fuel' => self::text($specs['fuel_type'] ?? null),
            'transmission' => self::transmission(self::text($row['transmission'])),
            'drive' => self::drive(self::text($row['drive_type'])),
            'consumption' => [
                'city_l' => self::number($specs['fuel_city_l'] ?? null),
                'highway_l' => self::number($specs['fuel_highway_l'] ?? null),
                'combined_l' => self::number($specs['fuel_combined_l'] ?? null),
            ],
            'dimensions_mm' => ['length' => null, 'width' => null, 'height' => null],
            'weight_kg' => null,
            'seats' => null,
            'tank_l' => null,
            'max_speed_kmh' => null,
            'trunk_l' => null,
            'clearance_mm' => null,
            'front_brakes' => null,
            'rear_brakes' => null,
            'front_suspension' => null,
            'rear_suspension' => null,
            'front_tires' => null,
            'rear_tires' => null,
            'has_photo' => $matchesPhoto,
            'photo_matches_generation' => $matchesPhoto,
            'equipment' => $this->equipment($id),
        ];
    }

    /** @return array{mime:string, bytes:string}|null */
    public function photo(string $modelId, ?string $generationId = null): ?array
    {
        if (! $this->photoMatchesGeneration($modelId, $generationId)) {
            return null;
        }
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
        if (! is_array($row)) {
            return null;
        }
        $mime = self::text($row['mime']);
        $bytes = self::bytes($row['image']);
        if ($mime === null || $bytes === null || $bytes === '') {
            return null;
        }
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
            return null;
        }

        return ['mime' => $mime, 'bytes' => $bytes];
    }

    /** @return array{standard:list<array<string, mixed>>, optional:list<array<string, mixed>>, not_available:list<array<string, mixed>>, unknown:list<array<string, mixed>>} */
    private function equipment(string $modificationId): array
    {
        return PickerSelection::equipment($this->pdo, $modificationId);
    }

    private function exists(string $table, string $id): bool
    {
        if (! in_array($table, ['public.brands', 'public.models', 'public.generations'], true)) {
            throw new UnavailableException;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM '.$table.' WHERE id = :id');
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
            throw new UnavailableException;
        }
    }

    private function photoMatchesGeneration(string $modelId, ?string $generationId): bool
    {
        if ($generationId === null || $generationId === '' || ! ctype_digit($generationId)) {
            return false;
        }
        try {
            $stmt = $this->pdo->prepare(<<<'SQL'
                SELECT 1
                FROM public.model_photos
                WHERE model_id = :model
                  AND verified = true
                  AND image IS NOT NULL
                  AND generation_id = :generation
                LIMIT 1
            SQL);
            $stmt->bindValue('model', $modelId);
            $stmt->bindValue('generation', $generationId);
            $stmt->execute();

            return $stmt->fetchColumn() !== false;
        } catch (PDOException) {
            return false;
        }
    }

    private function cacheFile(string $key): string
    {
        return dirname(__DIR__, 2).'/cache/picker/'.hash('sha256', $key).'.json';
    }

    /** @return array<string, mixed>|null */
    private function recall(string $key): ?array
    {
        $file = $this->cacheFile($key);
        if (! is_file($file) || time() - (int) filemtime($file) > 120) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @param array<string, mixed> $value */
    private function store(string $key, array $value): array
    {
        $file = $this->cacheFile($key);
        $dir = dirname($file);
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($file, json_encode($value, JSON_UNESCAPED_UNICODE));

        return $value;
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

        return number_format($cc / 1000, 1, '.', '').' L';
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
            return $start.'–';
        }

        return $start.'–'.$end;
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
        if (! is_string($value) || $value === '') {
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
        if (! is_string($value) || $value === '') {
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
        if (! is_string($value)) {
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
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    private static function number(mixed $value): int|float|null
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
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
