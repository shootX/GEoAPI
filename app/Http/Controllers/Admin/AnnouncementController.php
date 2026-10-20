<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use Electrik\Models\Announcement;
use Illuminate\Http\Request;

final class AnnouncementController extends CrudController
{
    protected function model(): string
    {
        return Announcement::class;
    }

    protected function searchColumns(): array
    {
        return ['title', 'body'];
    }

    protected function rules(bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'title' => [$req, 'string', 'max:200'],
            'body' => [$req, 'string', 'max:20000'],
            'audience' => ['sometimes', 'in:all,operators,plan'],
            'plan_price_id' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ];
    }

    protected function prepare(array $data, Request $request): array
    {
        if ($request->isMethod('POST')) {
            $data['created_by'] = $request->user()?->getKey();
        }

        return $data;
    }
}
