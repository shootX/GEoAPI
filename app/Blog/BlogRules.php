<?php

declare(strict_types=1);

namespace App\Blog;

final class BlogRules
{
    /**
     * @return array<string, mixed>
     */
    public static function post(bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'slug' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'status' => [$req, 'in:draft,published,scheduled'],
            'publish_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'cover_alt' => ['nullable', 'string', 'max:200'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'exists:blog_categories,id'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', 'exists:blog_tags,id'],
            'translations' => [$req, 'array'],
            'translations.ka' => ['sometimes', 'array'],
            'translations.en' => ['sometimes', 'array'],
            'translations.*.title' => ['nullable', 'string', 'max:200'],
            'translations.*.excerpt' => ['nullable', 'string', 'max:500'],
            'translations.*.body' => ['nullable', 'string', 'max:200000'],
            'translations.*.meta_title' => ['nullable', 'string', 'max:70'],
            'translations.*.meta_description' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function taxonomy(bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'slug' => ['sometimes', 'nullable', 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => [$req, 'array'],
            'name.ka' => ['nullable', 'string', 'max:80'],
            'name.en' => ['nullable', 'string', 'max:80'],
        ];
    }
}
