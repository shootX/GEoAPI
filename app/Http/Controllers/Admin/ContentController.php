<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Content;

final class ContentController extends CrudController
{
    protected function model(): string
    {
        return Content::class;
    }

    protected function rules(bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'slug' => [$req, 'string', 'max:120', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'locale' => ['sometimes', 'string', 'max:8', 'regex:/^[a-z]{2}$/'],
            'title' => [$req, 'string', 'max:200'],
            'body' => [$req, 'string', 'max:200000'],
            'type' => ['sometimes', 'string', 'max:32', 'regex:/^[a-z0-9_-]+$/'],
            'status' => ['sometimes', 'in:draft,published'],
        ];
    }
}
