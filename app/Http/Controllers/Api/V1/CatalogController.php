<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Catalog\CatalogApi;
use App\Http\Controllers\Controller;
use Carapi\Catalog\Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CatalogController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $_GET = $request->query->all();
        $_SERVER['REQUEST_METHOD'] = $request->getMethod();
        $_SERVER['HTTP_AUTHORIZATION'] = (string) $request->header('Authorization', '');
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = (string) $request->header('Accept-Language', '');
        $_SERVER['REMOTE_ADDR'] = (string) ($request->ip() ?: '127.0.0.1');
        $result = (new CatalogApi)->handle('/'.ltrim($request->path(), '/'));

        return response()
            ->json(Http::translate($result['body']), $result['status'], $result['headers'])
            ->header('Cache-Control', 'no-store');
    }
}
