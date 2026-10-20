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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class BlogPanelController extends Controller
{
    public function __construct(
        private readonly BlogStore $store,
        private readonly BlogCover $covers,
    ) {}

    public function index(): View
    {
        return view('admin.blog.index', [
            'posts' => BlogPost::query()->with(['categories', 'tags'])->orderByDesc('id')->paginate(20),
            'categories' => BlogCategory::query()->orderBy('slug')->get(),
            'tags' => BlogTag::query()->orderBy('slug')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.form', $this->formData(new BlogPost([
            'status' => 'draft',
            'translations' => ['ka' => [], 'en' => []],
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $post = $this->persist(null, $request);

        return redirect('/admin/blog/'.$post->id.'/edit');
    }

    public function edit(int $id): View
    {
        return view('admin.blog.form', $this->formData(BlogPost::query()->with(['categories', 'tags'])->findOrFail($id)));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->persist(BlogPost::query()->findOrFail($id), $request);

        return redirect('/admin/blog/'.$id.'/edit');
    }

    public function destroy(int $id): RedirectResponse
    {
        $post = BlogPost::query()->findOrFail($id);
        $this->covers->deleteFile($post->cover_path);
        $post->delete();

        return redirect('/admin/blog');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $this->guard(fn () => $this->store->saveCategory(null, $request->validate(BlogRules::taxonomy(false))));

        return redirect('/admin/blog');
    }

    public function destroyCategory(int $id): RedirectResponse
    {
        BlogCategory::query()->findOrFail($id)->delete();

        return redirect('/admin/blog');
    }

    public function storeTag(Request $request): RedirectResponse
    {
        $this->guard(fn () => $this->store->saveTag(null, $request->validate(BlogRules::taxonomy(false))));

        return redirect('/admin/blog');
    }

    public function destroyTag(int $id): RedirectResponse
    {
        BlogTag::query()->findOrFail($id)->delete();

        return redirect('/admin/blog');
    }

    private function persist(?BlogPost $post, Request $request): BlogPost
    {
        $request->merge([
            'publish_at' => $request->input('publish_at') ?: null,
            'slug' => $request->input('slug') ?: null,
            'category_ids' => $request->input('category_ids', []),
            'tag_ids' => $request->input('tag_ids', []),
        ]);
        $saved = $this->guard(fn () => $this->store->savePost($post, $request->validate(BlogRules::post(false))));
        if ($request->hasFile('file')) {
            $saved = $this->covers->store($saved, $request);
        }

        return $saved;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(BlogPost $post): array
    {
        return [
            'post' => $post,
            'categories' => BlogCategory::query()->orderBy('slug')->get(),
            'tags' => BlogTag::query()->orderBy('slug')->get(),
        ];
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function guard(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (HttpResponseException $e) {
            if ($e->getResponse()->getStatusCode() === 409) {
                throw ValidationException::withMessages([
                    'slug' => 'ეს სლაგი უკვე გამოყენებულია.',
                ]);
            }

            throw $e;
        }
    }
}
