<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Catalog\PublicSpec;
use Illuminate\Http\JsonResponse;

final class PublicOpenApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(PublicSpec::document());
    }
}
