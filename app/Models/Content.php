<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    protected $table = 'contents';

    protected $fillable = [
        'slug',
        'locale',
        'title',
        'body',
        'type',
        'status',
    ];
}
