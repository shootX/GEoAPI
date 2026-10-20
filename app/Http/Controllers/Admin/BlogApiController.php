<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Blog\BlogCover;
use App\Blog\BlogRules;
use App\Blog\BlogStore;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BlogApiController extends Controller
{
    public function __construct(
        private readonly BlogStore $store,
        private readonly BlogCover $covers,
    ) {}

    public function posts(Request $request): JsonResponse
    {
        $posts = BlogPost::query()
            ->with(['categories', 'tags'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 20))));

        return response()->json([
            'data' => $posts->getCollection()->map(fn (BlogPost $post): array => $post->toApi())->values(),
            'meta' => ['page' => $posts->currentPage(), 'per_page' => $posts->perPage(), 'total' => $posts->total()],
        ]);
    }

    public function showPost(int $id): JsonResponse
    {
        return response()->json(['data' => $this->post($id)->toApi()]);
    }

    public function storePost(Request $request): JsonResponse
    {
        $post = $this->store->savePost(null, $request->validate(BlogRules::post(false)));

        return response()->json(['data' => $post->toApi()], 201);
    }

    public function updatePost(Request $request, int $id): JsonResponse
    {
        $current = $this->post($id);
        $input = $request->validate(BlogRules::post($request->isMethod('PATCH')));
        if ($request->isMethod('PATCH')) {
            $input['translations'] = $input['translations'] ?? $current->translations;
            $input['status'] = $input['status'] ?? $current->status;
            $input['publish_at'] = array_key_exists('publish_at', $input) ? $input['publish_at'] : $current->publish_at;
            $input['slug'] = $input['slug'] ?? $current->slug;
        }
        $post = $this->store->savePost($current, $input);

        return response()->json(['data' => $post->toApi()]);
    }

    public function destroyPost(int $id): JsonResponse
    {
        $post = $this->post($id);
        $this->covers->deleteFile($post->cover_path);
        $post->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function storeCover(Request $request, int $id): JsonResponse
    {
        $post = $this->covers->store($this->post($id), $request)->load(['categories', 'tags']);

        return response()->json(['data' => $post->toApi()]);
    }

    public function destroyCover(int $id): JsonResponse
    {
        $post = $this->post($id);
        $this->covers->delete($post);

        return response()->json(['data' => $post->load(['categories', 'tags'])->toApi()]);
    }

    public function categories(): JsonResponse
    {
        return response()->json(['data' => BlogCategory::query()->orderBy('slug')->get()]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->store->saveCategory(null, $request->validate(BlogRules::taxonomy(false)))], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = $this->category($id);
        $input = $request->validate(BlogRules::taxonomy($request->isMethod('PATCH')));
        $input['name'] = $input['name'] ?? $category->name;
        if (! array_key_exists('slug', $input) || $input['slug'] === null) {
            $input['slug'] = $category->slug;
        }

        return response()->json(['data' => $this->store->saveCategory($category, $input)]);
    }

    public function destroyCategory(int $id): JsonResponse
    {
        $this->category($id)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function tags(): JsonResponse
    {
        return response()->json(['data' => BlogTag::query()->orderBy('slug')->get()]);
    }

    public function storeTag(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->store->saveTag(null, $request->validate(BlogRules::taxonomy(false)))], 201);
    }

    public function updateTag(Request $request, int $id): JsonResponse
    {
        $tag = $this->tag($id);
        $input = $request->validate(BlogRules::taxonomy($request->isMethod('PATCH')));
        $input['name'] = $input['name'] ?? $tag->name;
        if (! array_key_exists('slug', $input) || $input['slug'] === null) {
            $input['slug'] = $tag->slug;
        }

        return response()->json(['data' => $this->store->saveTag($tag, $input)]);
    }

    public function destroyTag(int $id): JsonResponse
    {
        $this->tag($id)->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    private function post(int $id): BlogPost
    {
        $post = BlogPost::query()->with(['categories', 'tags'])->find($id);
        if ($post === null) {
            throw new HttpResponseException(response()->json([
                'error' => ['code' => 'not_found', 'message' => 'ჩანაწერი ვერ მოიძებნა.'],
            ], 404));
        }

        return $post;
    }

    private function category(int $id): BlogCategory
    {
        return BlogCategory::query()->find($id) ?? throw new HttpResponseException(response()->json([
            'error' => ['code' => 'not_found', 'message' => 'ჩანაწერი ვერ მოიძებნა.'],
        ], 404));
    }

    private function tag(int $id): BlogTag
    {
        return BlogTag::query()->find($id) ?? throw new HttpResponseException(response()->json([
            'error' => ['code' => 'not_found', 'message' => 'ჩანაწერი ვერ მოიძებნა.'],
        ], 404));
    }
}
