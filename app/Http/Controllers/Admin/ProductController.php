<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Product;

final class ProductController extends CrudController
{
    protected function model(): string
    {
        return Product::class;
    }

    protected function rules(bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'slug' => [$req, 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'name' => [$req, 'string', 'max:200'],
            'summary' => [$req, 'string', 'max:2000'],
            'documentation_path' => ['nullable', 'string', 'max:200'],
            'status' => [$req, 'in:draft,published'],
        ];
    }
}
