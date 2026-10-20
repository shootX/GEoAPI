<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\AdminApi\OpenApi;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

final class DocsController extends Controller
{
    public function ui(OpenApi $openApi): View
    {
        $spec = $openApi->document();

        return view('api.docs', [
            'specUrl' => url('/api/docs/openapi.json'),
            'operations' => $openApi->operations($spec),
        ]);
    }

    public function spec(OpenApi $openApi): JsonResponse
    {
        return response()->json($openApi->document());
    }
}
