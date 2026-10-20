<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaItem extends Model
{
    protected $fillable = [
        'original_name',
        'mime',
        'path',
        'bytes',
        'alt',
    ];

    protected static function booted(): void
    {
        static::deleted(function (MediaItem $item): void {
            Storage::disk('public')->delete($item->path);
        });
    }
}
