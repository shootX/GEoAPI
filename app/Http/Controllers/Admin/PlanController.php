<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Plan;

final class PlanController extends CrudController
{
    protected function model(): string
    {
        return Plan::class;
    }

    protected function rules(bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'api_product_id' => [$req, 'integer', 'exists:api_products,id'],
            'code' => [$req, 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => [$req, 'string', 'max:200'],
            'is_addon' => ['sometimes', 'boolean'],
            'per_minute' => [$req, 'integer', 'min:1', 'max:100000'],
            'monthly_quota' => [$req, 'integer', 'min:1', 'max:100000000'],
            'price_minor' => ['nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'interval' => ['sometimes', 'in:month,year'],
            'status' => [$req, 'in:draft,published'],
            'features' => ['sometimes', 'array'],
        ];
    }
}
