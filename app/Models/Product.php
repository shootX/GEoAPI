<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'api_products';

    protected $fillable = [
        'slug',
        'name',
        'summary',
        'documentation_path',
        'status',
    ];

    public function plans(): HasMany
    {
        return $this->hasMany(Plan::class, 'api_product_id');
    }
}
