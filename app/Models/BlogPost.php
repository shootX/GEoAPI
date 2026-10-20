<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;

class BlogPost extends Model
{
    protected $fillable = [
        'slug',
        'status',
        'publish_at',
        'cover_path',
        'cover_alt',
        'translations',
    ];

    protected $casts = [
        'publish_at' => 'datetime',
        'translations' => 'array',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BlogCategory::class, 'blog_post_category');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where(function (Builder $inner): void {
            $inner->where('status', 'published')
                ->orWhere(function (Builder $scheduled): void {
                    $scheduled->where('status', 'scheduled')->where('publish_at', '<=', now());
                });
        });
    }

    public function scopeTranslated(Builder $query, string $locale): Builder
    {
        $path = '$.'.$locale.'.title';
        if ($query->getConnection()->getDriverName() === 'pgsql') {
            return $query->whereRaw(
                "coalesce(translations->?->>'title', '') <> ''",
                [$locale]
            );
        }

        return $query->whereRaw(
            "coalesce(json_extract(translations, ?), '') <> ''",
            [$path]
        );
    }

    public function isVisible(): bool
    {
        if ($this->status === 'published') {
            return true;
        }

        return $this->status === 'scheduled'
            && $this->publish_at !== null
            && $this->publish_at->lessThanOrEqualTo(now());
    }

    public function hasLocale(string $locale): bool
    {
        return trim((string) ($this->translations[$locale]['title'] ?? '')) !== '';
    }

    /**
     * @return array{title:string,excerpt:string,body:string,meta_title:string,meta_description:string}
     */
    public function translation(string $locale): array
    {
        $row = $this->translations[$locale] ?? [];

        return [
            'title' => (string) ($row['title'] ?? ''),
            'excerpt' => (string) ($row['excerpt'] ?? ''),
            'body' => (string) ($row['body'] ?? ''),
            'meta_title' => (string) ($row['meta_title'] ?? ''),
            'meta_description' => (string) ($row['meta_description'] ?? ''),
        ];
    }

    public function readingMinutes(string $locale): int
    {
        $words = preg_split('/\s+/u', trim($this->translation($locale)['body']), -1, PREG_SPLIT_NO_EMPTY);

        return max(1, (int) ceil((is_array($words) ? count($words) : 0) / 200));
    }

    public function coverUrl(): ?string
    {
        if ($this->cover_path === null || $this->cover_path === '') {
            return null;
        }

        return Storage::disk('public')->url($this->cover_path);
    }

    public function publicUrl(string $locale): string
    {
        return url('/'.$locale.'/blog/'.$this->slug);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApi(): array
    {
        return [
            'id' => (int) $this->id,
            'slug' => $this->slug,
            'status' => $this->status,
            'publish_at' => $this->publish_at?->toIso8601String(),
            'cover_path' => $this->cover_path,
            'cover_url' => $this->coverUrl(),
            'cover_alt' => $this->cover_alt,
            'translations' => $this->translations,
            'category_ids' => $this->categories->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'tag_ids' => $this->tags->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'reading_minutes' => [
                'ka' => $this->hasLocale('ka') ? $this->readingMinutes('ka') : null,
                'en' => $this->hasLocale('en') ? $this->readingMinutes('en') : null,
            ],
        ];
    }
}
