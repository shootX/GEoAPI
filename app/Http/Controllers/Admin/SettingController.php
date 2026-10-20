<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 50);
        $query = Setting::query()->orderBy('key');
        $term = trim((string) $request->query('q', ''));
        if ($term !== '') {
            $like = Setting::query()->getConnection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';
            $query->where('key', $like, '%'.$term.'%');
        }
        $total = (clone $query)->count();

        return response()->json([
            'data' => $query->forPage($page, $perPage)->get(),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, true);
        if (Setting::query()->where('key', $data['key'])->exists()) {
            return response()->json([
                'error' => ['code' => 'conflict', 'message' => 'ეს პარამეტრი უკვე არსებობს. გამოიყენეთ PUT.'],
            ], 409);
        }
        $row = Setting::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function show(string $key): JsonResponse
    {
        return response()->json(['data' => $this->find($key)]);
    }

    public function update(Request $request, string $key): JsonResponse
    {
        $row = $this->find($key);
        $data = $this->validated($request, false);
        $row->value = $data['value'];
        $row->save();

        return response()->json(['data' => $row->fresh()]);
    }

    public function destroy(string $key): JsonResponse
    {
        $this->find($key)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * @return array{key?:string,value:mixed}
     */
    private function validated(Request $request, bool $withKey): array
    {
        $rules = [
            'value' => ['present'],
        ];
        if ($withKey) {
            $rules['key'] = ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9_.:-]+$/'];
        }
        $data = $request->validate($rules);
        $this->rejectSecret((string) ($data['key'] ?? $request->route('key')));

        return $data;
    }

    private function rejectSecret(string $key): void
    {
        if (preg_match('/password|secret|token|credential/i', $key) === 1) {
            throw new HttpResponseException(response()->json([
                'error' => ['code' => 'forbidden', 'message' => 'საიდუმლო პარამეტრი ამ API-ით არ ინახება.'],
            ], 422));
        }
    }

    private function find(string $key): Setting
    {
        if (preg_match('/^[A-Za-z0-9_.:-]+$/', $key) !== 1) {
            throw new HttpResponseException(response()->json([
                'error' => ['code' => 'not_found', 'message' => 'პარამეტრი ვერ მოიძებნა.'],
            ], 404));
        }
        $row = Setting::query()->find($key);
        if (! $row instanceof Setting) {
            throw new HttpResponseException(response()->json([
                'error' => ['code' => 'not_found', 'message' => 'პარამეტრი ვერ მოიძებნა.'],
            ], 404));
        }

        return $row;
    }
}
