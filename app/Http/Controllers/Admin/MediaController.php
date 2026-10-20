<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\MediaItem;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class MediaController extends CrudController
{
    protected function model(): string
    {
        return MediaItem::class;
    }

    protected function searchColumns(): array
    {
        return ['original_name', 'alt'];
    }

    protected function rules(bool $partial): array
    {
        return [
            'alt' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required_without:content_base64', 'file', 'max:8192'],
            'content_base64' => ['required_without:file', 'string'],
            'filename' => ['required_with:content_base64', 'string', 'max:200'],
            'mime' => ['required_with:content_base64', 'string', 'max:127'],
            'alt' => ['nullable', 'string', 'max:200'],
        ]);

        if ($request->hasFile('file')) {
            $upload = $request->file('file');
            $binary = (string) file_get_contents($upload->getRealPath());
            $name = $upload->getClientOriginalName();
            $mime = (string) $upload->getMimeType();
        } else {
            $binary = base64_decode((string) $data['content_base64'], true);
            if ($binary === false) {
                return response()->json([
                    'error' => ['code' => 'invalid', 'message' => 'content_base64 არ არის სწორი Base64.'],
                ], 422);
            }
            $name = (string) $data['filename'];
            $mime = (string) $data['mime'];
        }

        if (strlen($binary) > 8 * 1024 * 1024) {
            return response()->json([
                'error' => ['code' => 'invalid', 'message' => 'ფაილი 8 მბ-ზე დიდია.'],
            ], 422);
        }

        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?: 'file';
        $path = 'media/'.bin2hex(random_bytes(16)).'-'.$safe;
        Storage::disk('public')->put($path, $binary);

        $row = MediaItem::query()->create([
            'original_name' => $name,
            'mime' => $mime,
            'path' => $path,
            'bytes' => strlen($binary),
            'alt' => $data['alt'] ?? null,
        ]);

        return response()->json(['data' => $this->payload($row)], 201);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['data' => $this->payload($this->find($id))]);
    }

    public function index(Request $request): JsonResponse
    {
        $response = parent::index($request);
        $body = $response->getData(true);
        $body['data'] = array_map(fn (array $row): array => $this->withUrl($row), $body['data']);

        return response()->json($body);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $response = parent::update($request, $id);
        $body = $response->getData(true);
        $body['data'] = $this->withUrl($body['data']);

        return response()->json($body);
    }

    public function file(int $id): StreamedResponse
    {
        $row = $this->find($id);
        if (! $row instanceof MediaItem || ! Storage::disk('public')->exists($row->path)) {
            throw new HttpResponseException(response()->json([
                'error' => ['code' => 'not_found', 'message' => 'ფაილი ვერ მოიძებნა.'],
            ], 404));
        }

        return Storage::disk('public')->response($row->path, $row->original_name, [
            'Content-Type' => $row->mime,
        ]);
    }

    private function payload(mixed $row): array
    {
        $data = $row instanceof MediaItem ? $row->toArray() : (array) $row;

        return $this->withUrl($data);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function withUrl(array $row): array
    {
        $row['url'] = isset($row['path']) ? Storage::disk('public')->url((string) $row['path']) : null;

        return $row;
    }
}
