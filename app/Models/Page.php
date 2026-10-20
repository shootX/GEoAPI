<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Page extends Model
{
    protected $fillable = [
        'slug',
        'locale',
        'title',
        'body',
        'status',
    ];

    public static function published(string $slug): ?self
    {
        if (! Schema::hasTable((new self())->getTable())) {
            return null;
        }

        $locale = app()->getLocale();

        return self::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->orderByRaw('case when locale = ? then 0 else 1 end', [$locale])
            ->first();
    }
}
