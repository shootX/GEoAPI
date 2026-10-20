<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class CrudController extends Controller
{
    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * @return array<string, mixed>
     */
    abstract protected function rules(bool $partial): array;

    /**
     * @return list<string>
     */
    protected function searchColumns(): array
    {
        return ['title', 'name', 'slug'];
    }

    public function index(Request $request): JsonResponse
    {
        [$page, $perPage] = $this->page($request);
        $query = $this->newQuery();
        $this->applySearch($query, $request);
        $total = (clone $query)->count();
        $rows = $query->orderBy('id')->forPage($page, $perPage)->get();

        return response()->json([
            'data' => $rows,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->rules(false));
        $row = $this->model()::query()->create($this->prepare($data, $request));

        return response()->json(['data' => $row], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->find($id)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $row = $this->find($id);
        $partial = $request->isMethod('PATCH');
        $data = $request->validate($this->rules($partial));
        $row->fill($this->prepare($data, $request));
        $row->save();

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->find($id)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepare(array $data, Request $request): array
    {
        return $data;
    }

    protected function newQuery(): Builder
    {
        return $this->model()::query();
    }

    protected function find(int $id): Model
    {
        $row = $this->newQuery()->find($id);
        if ($row === null) {
            throw new HttpResponseException(response()->json([
                'error' => ['code' => 'not_found', 'message' => 'ჩანაწერი ვერ მოიძებნა.'],
            ], 404));
        }

        return $row;
    }

    protected function applySearch(Builder $query, Request $request): void
    {
        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            return;
        }
        $columns = array_values(array_filter(
            $this->searchColumns(),
            fn (string $column): bool => in_array($column, $query->getModel()->getFillable(), true)
        ));
        if ($columns === []) {
            return;
        }
        $like = $query->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $query->where(function (Builder $inner) use ($columns, $like, $term): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, $like, '%'.$term.'%');
            }
        });
    }

    /**
     * @return array{0:int,1:int}
     */
    protected function page(Request $request): array
    {
        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        return [(int) ($data['page'] ?? 1), (int) ($data['per_page'] ?? 50)];
    }
}
