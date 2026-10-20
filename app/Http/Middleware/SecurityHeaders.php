<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
        if ($request->isMethod('GET') && $request->is('guide', 'privacy', 'terms', 'pricing', 'demo')) {
            $response->headers->set('Cache-Control', 'private, max-age=60');
            $response->headers->set('Vary', 'Cookie');
        }

        return $response;
    }
}
