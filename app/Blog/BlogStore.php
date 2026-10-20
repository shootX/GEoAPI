<?php

declare(strict_types=1);

namespace App\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class BlogStore
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function savePost(?BlogPost $post, array $input): BlogPost
    {
        $translations = $this->translations($input['translations'] ?? []);
        if ($translations === []) {
            throw ValidationException::withMessages([
                'translations' => 'ერთ ენაზე მაინც საჭიროა სათაური და ტექსტი.',
            ]);
        }
        $status = (string) ($input['status'] ?? $post?->status ?? 'draft');
        $slug = $this->slug($input, $translations['en']['title'] ?? $translations['ka']['title'] ?? 'post', BlogPost::class, $post?->id);

        try {
            return DB::transaction(function () use ($post, $input, $translations, $status, $slug): BlogPost {
                $row = $post ?? new BlogPost;
                $publishAt = array_key_exists('publish_at', $input) ? $input['publish_at'] : $post?->publish_at;
                if ($status === 'published' && $publishAt === null) {
                    $publishAt = now();
                }
                $row->fill([
                    'slug' => $slug,
                    'status' => $status,
                    'publish_at' => $publishAt,
                    'cover_alt' => $input['cover_alt'] ?? $row->cover_alt,
                    'translations' => $translations,
                ]);
                $row->save();
                if (array_key_exists('category_ids', $input)) {
                    $row->categories()->sync(array_map('intval', (array) $input['category_ids']));
                }
                if (array_key_exists('tag_ids', $input)) {
                    $row->tags()->sync(array_map('intval', (array) $input['tag_ids']));
                }

                return $row->load(['categories', 'tags']);
            });
        } catch (QueryException $e) {
            $this->conflict($e);
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveCategory(?BlogCategory $category, array $input): BlogCategory
    {
        /** @var BlogCategory $row */
        $row = $this->saveTaxonomy($category, $input, BlogCategory::class);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function saveTag(?BlogTag $tag, array $input): BlogTag
    {
        /** @var BlogTag $row */
        $row = $this->saveTaxonomy($tag, $input, BlogTag::class);

        return $row;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  class-string<BlogCategory|BlogTag>  $class
     */
    private function saveTaxonomy(BlogCategory|BlogTag|null $row, array $input, string $class): BlogCategory|BlogTag
    {
        $name = $this->names($input['name'] ?? []);
        if ($name === []) {
            throw ValidationException::withMessages([
                'name' => 'სახელი საჭიროა ქართულად ან ინგლისურად.',
            ]);
        }
        $slug = $this->slug($input, $name['en'] ?? $name['ka'], $class, $row?->id);
        $model = $row ?? new $class;
        try {
            $model->fill(['slug' => $slug, 'name' => $name]);
            $model->save();
        } catch (QueryException $e) {
            $this->conflict($e);
        }

        return $model;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, array{title:string,excerpt:string,body:string,meta_title:string,meta_description:string}>
     */
    private function translations(array $input): array
    {
        $clean = [];
        foreach (['ka', 'en'] as $locale) {
            $row = $input[$locale] ?? null;
            if (! is_array($row)) {
                continue;
            }
            $title = trim((string) ($row['title'] ?? ''));
            $body = trim((string) ($row['body'] ?? ''));
            if ($title === '' && $body === '') {
                continue;
            }
            if ($title === '' || $body === '') {
                throw ValidationException::withMessages([
                    'translations.'.$locale => 'სათაური და ტექსტი ერთად უნდა იყოს.',
                ]);
            }
            $clean[$locale] = [
                'title' => $title,
                'excerpt' => trim((string) ($row['excerpt'] ?? '')),
                'body' => $body,
                'meta_title' => trim((string) ($row['meta_title'] ?? '')),
                'meta_description' => trim((string) ($row['meta_description'] ?? '')),
            ];
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  class-string<BlogPost|BlogCategory|BlogTag>  $class
     */
    private function slug(array $input, string $title, string $class, ?int $ignoreId): string
    {
        $provided = trim((string) ($input['slug'] ?? ''));
        if ($provided === '') {
            $base = BlogSlug::fromTitle($title);

            return match ($class) {
                BlogPost::class => BlogSlug::forPost($base, $ignoreId),
                BlogCategory::class => BlogSlug::forCategory($base, $ignoreId),
                default => BlogSlug::forTag($base, $ignoreId),
            };
        }
        $query = $class::query()->where('slug', $provided);
        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }
        if ($query->exists()) {
            throw new HttpResponseException(response()->json([
                'error' => ['code' => 'conflict', 'message' => 'ჩანაწერი ეწინააღმდეგება უნიკალურ ველს.'],
            ], 409));
        }

        return $provided;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, string>
     */
    private function names(array $input): array
    {
        $name = [];
        foreach (['ka', 'en'] as $locale) {
            $value = trim((string) ($input[$locale] ?? ''));
            if ($value !== '') {
                $name[$locale] = $value;
            }
        }

        return $name;
    }

    private function conflict(QueryException $e): never
    {
        $state = (string) $e->getCode();
        if ($state === '23505' || $state === '23000' || str_contains(strtolower($e->getMessage()), 'unique')) {
            throw new HttpResponseException(response()->json([
                'error' => ['code' => 'conflict', 'message' => 'ჩანაწერი ეწინააღმდეგება უნიკალურ ველს.'],
            ], 409));
        }

        throw $e;
    }
}
