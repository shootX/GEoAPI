<?php

declare(strict_types=1);

namespace App\Catalog;

use Illuminate\Database\Connection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PDOException;
use Throwable;

final class WritableCatalog
{
    /**
     * @var array<string, array{key:string,columns:array<string,string>,required:list<string>,search:list<string>,hidden:list<string>}>
     */
    public const TABLES = [
        'brands' => [
            'key' => 'id',
            'columns' => [
                'slug' => 'string',
                'name' => 'string',
                'synonyms' => 'json',
                'is_lcv_or_commercial' => 'bool',
                'logo_url_light' => 'string',
                'logo_url_dark' => 'string',
                'source_url' => 'string',
                'drom_firm_id' => 'int',
            ],
            'required' => ['slug', 'name', 'source_url'],
            'search' => ['slug', 'name'],
            'hidden' => [],
        ],
        'models' => [
            'key' => 'id',
            'columns' => [
                'brand_id' => 'int',
                'slug' => 'string',
                'name' => 'string',
                'source_url' => 'string',
                'rating_value' => 'number',
                'rating_count' => 'int',
                'body_types' => 'json',
                'body_type' => 'string',
            ],
            'required' => ['brand_id', 'slug', 'name', 'source_url'],
            'search' => ['slug', 'name'],
            'hidden' => [],
        ],
        'generations' => [
            'key' => 'id',
            'columns' => [
                'model_id' => 'int',
                'drom_generation_id' => 'int',
                'slug' => 'string',
                'name' => 'string',
                'chassis_code' => 'string',
                'body_type' => 'string',
                'year_start' => 'int',
                'year_end' => 'int',
                'period_text' => 'string',
                'market' => 'string',
                'steering_wheel' => 'string',
                'photo_urls' => 'json',
                'source_url' => 'string',
            ],
            'required' => ['model_id', 'drom_generation_id', 'slug', 'source_url'],
            'search' => ['slug', 'name', 'chassis_code'],
            'hidden' => [],
        ],
        'modifications' => [
            'key' => 'id',
            'columns' => [
                'generation_id' => 'int',
                'model_id' => 'int',
                'drom_complectation_id' => 'int',
                'name' => 'string',
                'trim_level' => 'string',
                'period_text' => 'string',
                'year_start' => 'int',
                'year_end' => 'int',
                'body_type' => 'string',
                'drive_type' => 'string',
                'transmission' => 'string',
                'engine_volume_cc' => 'int',
                'chassis_code' => 'string',
                'assembly_country' => 'string',
                'doors' => 'int',
                'source_url' => 'string',
            ],
            'required' => ['model_id', 'drom_complectation_id', 'name', 'source_url'],
            'search' => ['name', 'trim_level', 'chassis_code'],
            'hidden' => [],
        ],
        'modification_equipment' => [
            'key' => 'id',
            'columns' => [
                'modification_id' => 'int',
                'category' => 'string',
                'name' => 'string',
                'status' => 'string',
                'value' => 'string',
            ],
            'required' => ['modification_id', 'name'],
            'search' => ['name', 'category'],
            'hidden' => [],
        ],
        'model_photos' => [
            'key' => 'model_id',
            'columns' => [
                'model_id' => 'int',
                'color' => 'string',
                'prompt' => 'string',
                'generation_id' => 'int',
                'mime' => 'string',
                'width' => 'int',
                'height' => 'int',
                'verified' => 'bool',
                'content_base64' => 'binary',
            ],
            'required' => ['model_id', 'mime'],
            'search' => ['color', 'mime'],
            'hidden' => [],
        ],
        'photos' => [
            'key' => 'id',
            'columns' => [
                'entity_type' => 'string',
                'entity_drom_id' => 'string',
                'brand_slug' => 'string',
                'model_slug' => 'string',
                'generation_slug' => 'string',
                'sort_order' => 'int',
                'original_url' => 'string',
                'local_path' => 'string',
                'remote_path' => 'string',
                'width' => 'int',
                'height' => 'int',
                'bytes' => 'int',
                'sha256' => 'string',
            ],
            'required' => ['original_url'],
            'search' => ['original_url', 'brand_slug', 'model_slug'],
            'hidden' => [],
        ],
    ];

    /**
     * @return array{data:list<array<string,mixed>>,meta:array{page:int,per_page:int,total:int}}
     */
    public function paginate(string $table, int $page, int $perPage, string $term = '', ?string $parentColumn = null, ?int $parentId = null): array
    {
        $def = $this->definition($table);
        $query = $this->db()->table($table);
        if ($parentColumn !== null && $parentId !== null) {
            $this->assertColumn($def, $parentColumn);
            $query->where($parentColumn, $parentId);
        }
        if ($term !== '') {
            $like = $this->db()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where(function ($inner) use ($def, $like, $term): void {
                foreach ($def['search'] as $column) {
                    $inner->orWhere($column, $like, '%'.$term.'%');
                }
            });
        }
        $total = (clone $query)->count();
        $columns = $this->visibleColumns($def);
        $rows = $query->orderBy($def['key'])->forPage($page, $perPage)->get($columns);

        return [
            'data' => array_map(fn (object $row): array => $this->present($def, (array) $row), $rows->all()),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $table, int $id, bool $withBinary = false): array
    {
        $def = $this->definition($table);
        $row = $this->db()->table($table)->where($def['key'], $id)->first();
        if ($row === null) {
            $this->missing();
        }
        $data = $this->present($def, (array) $row);
        if ($withBinary && $table === 'model_photos' && isset($row->image)) {
            $raw = $row->image;
            if (is_resource($raw)) {
                $raw = stream_get_contents($raw) ?: '';
            }
            $data['content_base64'] = base64_encode((string) $raw);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function create(string $table, array $input): array
    {
        $def = $this->definition($table);
        $data = $this->validate($def, $input, false);
        $binary = $this->pullBinary($data);
        try {
            if ($table === 'model_photos') {
                $data['verified'] = $data['verified'] ?? false;
                $this->db()->table($table)->insert($data + $this->binaryColumns($binary));
                $id = (int) $data['model_id'];
            } else {
                $id = (int) $this->db()->table($table)->insertGetId($data, $def['key']);
            }
        } catch (PDOException $e) {
            $this->queryFailed($e);
        }

        return $this->find($table, $id);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function update(string $table, int $id, array $input, bool $partial): array
    {
        $def = $this->definition($table);
        $this->find($table, $id);
        $data = $this->validate($def, $input, $partial);
        $binary = $this->pullBinary($data);
        unset($data[$def['key']]);
        if ($data !== [] || $binary !== null) {
            try {
                $this->db()->table($table)->where($def['key'], $id)->update($data + $this->binaryColumns($binary));
            } catch (PDOException $e) {
                $this->queryFailed($e);
            }
        }

        return $this->find($table, $id);
    }

    public function delete(string $table, int $id): void
    {
        $def = $this->definition($table);
        $deleted = $this->db()->table($table)->where($def['key'], $id)->delete();
        if ($deleted === 0) {
            $this->missing();
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function specs(int $modificationId): ?array
    {
        $row = $this->db()->table('modification_specs')->where('modification_id', $modificationId)->first();
        if ($row === null) {
            return null;
        }
        $specs = $row->specs ?? null;
        if (is_string($specs)) {
            $specs = json_decode($specs, true);
        }

        return [
            'modification_id' => (int) $row->modification_id,
            'specs' => $specs,
        ];
    }

    /**
     * @param  array<string, mixed>  $specs
     * @return array<string, mixed>
     */
    public function saveSpecs(int $modificationId, array $specs): array
    {
        $this->find('modifications', $modificationId);
        $payload = json_encode($specs, JSON_UNESCAPED_UNICODE);
        $exists = $this->db()->table('modification_specs')->where('modification_id', $modificationId)->exists();
        if ($exists) {
            $this->db()->table('modification_specs')->where('modification_id', $modificationId)->update([
                'specs' => $payload,
            ]);
        } else {
            $this->db()->table('modification_specs')->insert([
                'modification_id' => $modificationId,
                'specs' => $payload,
            ]);
        }

        return $this->specs($modificationId) ?? ['modification_id' => $modificationId, 'specs' => $specs];
    }

    /**
     * @param  array<string, array{key:string,columns:array<string,string>,required:list<string>,search:list<string>,hidden:list<string>}>  $def
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function validate(array $def, array $input, bool $partial): array
    {
        $rules = [];
        foreach ($def['columns'] as $column => $type) {
            $presence = (! $partial && in_array($column, $def['required'], true)) ? 'required' : 'sometimes';
            $rules[$column] = array_values(array_filter([$presence, 'nullable', $this->typeRule($type)]));
        }
        $validated = Validator::make($input, $rules)->validate();
        $clean = [];
        foreach ($validated as $column => $value) {
            if (! array_key_exists($column, $def['columns'])) {
                continue;
            }
            if ($def['columns'][$column] === 'binary') {
                $clean[$column] = $value;

                continue;
            }
            if ($value === null) {
                $clean[$column] = null;

                continue;
            }
            if ($def['columns'][$column] === 'json') {
                $clean[$column] = json_encode($value, JSON_UNESCAPED_UNICODE);

                continue;
            }
            $clean[$column] = $def['columns'][$column] === 'bool' ? (bool) $value : $value;
        }

        return $clean;
    }

    private function typeRule(string $type): string
    {
        return match ($type) {
            'int' => 'integer',
            'bool' => 'boolean',
            'number' => 'numeric',
            'json' => 'array',
            'binary' => 'string',
            default => 'string',
        };
    }

    /**
     * @param  array<string, array{key:string,columns:array<string,string>,required:list<string>,search:list<string>,hidden:list<string>}>  $def
     * @return list<string>
     */
    private function visibleColumns(array $def): array
    {
        $columns = [$def['key']];
        foreach (array_keys($def['columns']) as $column) {
            if ($def['columns'][$column] === 'binary' || in_array($column, $def['hidden'], true)) {
                continue;
            }
            $columns[] = $column;
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param  array<string, array{key:string,columns:array<string,string>,required:list<string>,search:list<string>,hidden:list<string>}>  $def
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function present(array $def, array $row): array
    {
        foreach ($def['columns'] as $column => $type) {
            if ($type !== 'json' || ! array_key_exists($column, $row) || ! is_string($row[$column])) {
                continue;
            }
            $decoded = json_decode($row[$column], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $row[$column] = $decoded;
            }
        }
        unset($row['image']);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function pullBinary(array &$data): ?string
    {
        if (! array_key_exists('content_base64', $data)) {
            return null;
        }
        $raw = $data['content_base64'];
        unset($data['content_base64']);
        if ($raw === null || $raw === '') {
            return null;
        }
        $binary = base64_decode((string) $raw, true);
        if ($binary === false) {
            throw ValidationException::withMessages([
                'content_base64' => 'content_base64 არ არის სწორი Base64.',
            ]);
        }

        return $binary;
    }

    /**
     * @return array<string, mixed>
     */
    private function binaryColumns(?string $binary): array
    {
        return $binary === null ? [] : ['image' => $binary];
    }

    /**
     * @param  array<string, array{key:string,columns:array<string,string>,required:list<string>,search:list<string>,hidden:list<string>}>  $def
     */
    private function assertColumn(array $def, string $column): void
    {
        if (! array_key_exists($column, $def['columns']) && $column !== $def['key']) {
            $this->missing();
        }
    }

    /**
     * @return array{key:string,columns:array<string,string>,required:list<string>,search:list<string>,hidden:list<string>}
     */
    private function definition(string $table): array
    {
        if (! array_key_exists($table, self::TABLES)) {
            $this->missing();
        }

        return self::TABLES[$table];
    }

    private function db(): Connection
    {
        try {
            $connection = DB::connection('catalog');
            if ($connection->getDriverName() === 'pgsql') {
                $connection->statement("SET statement_timeout = '15s'");
                $connection->statement("SET lock_timeout = '2s'");
            }

            return $connection;
        } catch (Throwable) {
            throw new HttpResponseException(response()->json([
                'error' => ['code' => 'unavailable', 'message' => 'კატალოგის ჩაწერის კავშირი მიუწვდომელია.'],
            ], 503));
        }
    }

    private function missing(): never
    {
        throw new HttpResponseException(response()->json([
            'error' => ['code' => 'not_found', 'message' => 'ჩანაწერი ვერ მოიძებნა.'],
        ], 404));
    }

    private function queryFailed(PDOException $e): never
    {
        $sqlState = (string) $e->getCode();
        if ($sqlState === '23505' || $sqlState === '23000') {
            throw new HttpResponseException(response()->json([
                'error' => ['code' => 'conflict', 'message' => 'ჩანაწერი ეწინააღმდეგება უნიკალურ ველს.'],
            ], 409));
        }
        if ($sqlState === '23503') {
            throw new HttpResponseException(response()->json([
                'error' => ['code' => 'invalid', 'message' => 'დაკავშირებული ჩანაწერი ვერ მოიძებნა.'],
            ], 422));
        }
        throw new HttpResponseException(response()->json([
            'error' => ['code' => 'unavailable', 'message' => 'კატალოგის მოთხოვნა ვერ შესრულდა.'],
        ], 503));
    }
}
