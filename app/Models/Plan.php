<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plan extends Model
{
    protected $table = 'api_plans';

    protected $fillable = [
        'api_product_id',
        'code',
        'name',
        'is_addon',
        'per_minute',
        'monthly_quota',
        'price_minor',
        'currency',
        'interval',
        'status',
        'features',
    ];

    protected function casts(): array
    {
        return [
            'is_addon' => 'boolean',
            'features' => 'array',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'api_product_id');
    }
}
