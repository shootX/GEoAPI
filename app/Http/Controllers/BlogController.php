<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Access\AdminAccess;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class BlogController extends Controller
{
    public function index(Request $request, ?string $locale = null): View
    {
        $locale = $this->locale($locale);
        $category = trim((string) $request->query('category', ''));
        $tag = trim((string) $request->query('tag', ''));
        $posts = BlogPost::query()
            ->visible()
            ->translated($locale)
            ->with(['categories', 'tags'])
            ->when($category !== '', fn ($query) => $query->whereHas('categories', fn ($inner) => $inner->where('slug', $category)))
            ->when($tag !== '', fn ($query) => $query->whereHas('tags', fn ($inner) => $inner->where('slug', $tag)))
            ->orderByDesc('publish_at')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('public.blog.index', [
            'posts' => $posts,
            'locale' => $locale,
            'category' => $category,
            'tag' => $tag,
            'categories' => BlogCategory::query()->orderBy('slug')->get(),
            'tags' => BlogTag::query()->orderBy('slug')->get(),
        ]);
    }

    public function show(string $slug): View
    {
        return $this->render(null, $slug);
    }

    public function showLocalized(string $locale, string $slug): View
    {
        return $this->render($locale, $slug);
    }

    private function render(?string $locale, string $slug): View
    {
        $locale = $this->locale($locale);
        $post = BlogPost::query()->with(['categories', 'tags'])->where('slug', $slug)->first();
        if ($post === null || ! $post->hasLocale($locale) || (! $post->isVisible() && ! $this->admin())) {
            abort(404);
        }
        $related = BlogPost::query()
            ->visible()
            ->translated($locale)
            ->whereKeyNot($post->id)
            ->when($post->categories->isNotEmpty(), function ($query) use ($post) {
                $query->whereHas('categories', fn ($inner) => $inner->whereIn('blog_categories.id', $post->categories->pluck('id')));
            })
            ->orderByDesc('publish_at')
            ->orderByDesc('id')
            ->limit(3)
            ->get();
        if ($related->isEmpty()) {
            $related = BlogPost::query()->visible()->translated($locale)->whereKeyNot($post->id)->orderByDesc('id')->limit(3)->get();
        }

        return view('public.blog.show', [
            'post' => $post,
            'locale' => $locale,
            'translation' => $post->translation($locale),
            'body' => Str::markdown($post->translation($locale)['body'], [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
            'related' => $related,
            'draft' => ! $post->isVisible(),
        ]);
    }

    private function locale(?string $locale): string
    {
        if ($locale === 'ka' || $locale === 'en') {
            session(['locale' => $locale]);
            app()->setLocale($locale);

            return $locale;
        }
        $current = app()->getLocale();

        return $current === 'en' ? 'en' : 'ka';
    }

    private function admin(): bool
    {
        $user = auth()->user();

        return $user instanceof User && AdminAccess::allows($user);
    }
}
