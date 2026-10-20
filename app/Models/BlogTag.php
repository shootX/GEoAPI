<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BlogTag extends Model
{
    protected $fillable = ['slug', 'name'];

    protected $casts = [
        'name' => 'array',
    ];

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag');
    }

    public function label(?string $locale = null): string
    {
        $locale = $locale ?: app()->getLocale();
        $name = $this->name ?? [];

        return (string) ($name[$locale] ?? $name['ka'] ?? $name['en'] ?? $this->slug);
    }
}
