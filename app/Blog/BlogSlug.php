<?php

declare(strict_types=1);

namespace App\Blog;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class BlogSlug
{
    public static function fromTitle(string $title): string
    {
        $slug = Str::slug($title);
        if ($slug === '') {
            $slug = Str::slug(self::transliterate($title));
        }

        return $slug !== '' ? $slug : 'post';
    }

    public static function unique(string $base, string $model, ?int $ignoreId = null): string
    {
        $slug = $base;
        $n = 2;
        while (self::taken($model, $slug, $ignoreId)) {
            $slug = $base.'-'.$n;
            $n++;
        }

        return $slug;
    }

    private static function taken(string $model, string $slug, ?int $ignoreId): bool
    {
        /** @var class-string<Model> $model */
        $query = $model::query()->where('slug', $slug);
        if ($ignoreId !== null) {
            $query->whereKeyNot($ignoreId);
        }

        return $query->exists();
    }

    public static function forPost(string $base, ?int $ignoreId = null): string
    {
        return self::unique($base, BlogPost::class, $ignoreId);
    }

    public static function forCategory(string $base, ?int $ignoreId = null): string
    {
        return self::unique($base, BlogCategory::class, $ignoreId);
    }

    public static function forTag(string $base, ?int $ignoreId = null): string
    {
        return self::unique($base, BlogTag::class, $ignoreId);
    }

    private static function transliterate(string $title): string
    {
        $map = [
            'ა' => 'a', 'ბ' => 'b', 'გ' => 'g', 'დ' => 'd', 'ე' => 'e', 'ვ' => 'v', 'ზ' => 'z',
            'თ' => 't', 'ი' => 'i', 'კ' => 'k', 'ლ' => 'l', 'მ' => 'm', 'ნ' => 'n', 'ო' => 'o',
            'პ' => 'p', 'ჟ' => 'zh', 'რ' => 'r', 'ს' => 's', 'ტ' => 't', 'უ' => 'u', 'ფ' => 'p',
            'ქ' => 'k', 'ღ' => 'gh', 'ყ' => 'q', 'შ' => 'sh', 'ჩ' => 'ch', 'ც' => 'ts', 'ძ' => 'dz',
            'წ' => 'ts', 'ჭ' => 'ch', 'ხ' => 'kh', 'ჯ' => 'j', 'ჰ' => 'h',
        ];

        return strtr(mb_strtolower($title), $map);
    }
}
