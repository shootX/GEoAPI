<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Correlation id for this request. The body and secrets are not written to the log context.
 */
final class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->headers->get('X-Request-Id');
        $id = is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{1,64}$/', $incoming) === 1
            ? $incoming
            : (string) Str::uuid();
        Log::shareContext(['request_id' => $id]);
        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
