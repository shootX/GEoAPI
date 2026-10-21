<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Readiness is SELECT 1. The body is only ok, never SQL, a host, or an exception message.
 */
final class ReadyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
        } catch (Throwable) {
            return response()->json(['ok' => false], 503);
        }

        return response()->json(['ok' => true]);
    }
}
