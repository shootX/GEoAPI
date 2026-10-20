<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Catalog\WritableCatalog;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CatalogController extends Controller
{
    public function __construct(private readonly WritableCatalog $catalog) {}

    public function index(Request $request, string|int|null $parentId = null): JsonResponse
    {
        [$page, $perPage] = $this->page($request);
        $table = $this->table($request);
        $parentColumn = $this->optionalRouteString($request, 'parentColumn');
        if ($table === 'modification_equipment' && $parentColumn === null) {
            $filter = $request->validate([
                'modification_id' => ['required', 'integer', 'min:1'],
            ]);
            $parentColumn = 'modification_id';
            $parentId = (int) $filter['modification_id'];
        }
        $filters = $request->validate([
            'model_id' => ['sometimes', 'integer', 'min:1'],
            'brand_id' => ['sometimes', 'integer', 'min:1'],
        ]);

        return response()->json($this->catalog->paginate(
            $table,
            $page,
            $perPage,
            trim((string) $request->query('q', '')),
            $parentColumn,
            $parentId === null ? null : (int) $parentId,
            $filters,
        ));
    }

    public function store(Request $request, string|int|null $parentId = null): JsonResponse
    {
        $input = $request->all();
        $parentColumn = $this->optionalRouteString($request, 'parentColumn');
        if ($parentColumn !== null && $parentId !== null) {
            $input[$parentColumn] = (int) $parentId;
        }
        $table = $this->table($request);
        if ($table === 'model_photos' && ! array_key_exists('verified', $input)) {
            $input['verified'] = false;
        }

        return response()->json(['data' => $this->catalog->create($table, $input)], 201);
    }

    public function show(Request $request, string|int $id): JsonResponse
    {
        $withBinary = $request->query('include') === 'file';

        return response()->json(['data' => $this->catalog->find($this->table($request), (int) $id, $withBinary)]);
    }

    public function update(Request $request, string|int $id): JsonResponse
    {
        return response()->json([
            'data' => $this->catalog->update($this->table($request), (int) $id, $request->all(), $request->isMethod('PATCH')),
        ]);
    }

    public function destroy(Request $request, string|int $id): JsonResponse
    {
        $this->catalog->delete($this->table($request), (int) $id);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function specs(string|int $id): JsonResponse
    {
        $this->catalog->find('modifications', (int) $id);
        $specs = $this->catalog->specs((int) $id);

        return response()->json(['data' => $specs ?? ['modification_id' => (int) $id, 'specs' => null]]);
    }

    public function saveSpecs(Request $request, string|int $id): JsonResponse
    {
        $data = $request->validate([
            'specs' => ['present', 'array'],
        ]);

        return response()->json(['data' => $this->catalog->saveSpecs((int) $id, $data['specs'])]);
    }

    private function table(Request $request): string
    {
        $table = $request->route('table');
        if (! is_string($table) || ! array_key_exists($table, WritableCatalog::TABLES)) {
            abort(404);
        }

        return $table;
    }

    private function optionalRouteString(Request $request, string $name): ?string
    {
        $value = $request->route($name);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function page(Request $request): array
    {
        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return [(int) ($data['page'] ?? 1), (int) ($data['per_page'] ?? 50)];
    }
}
