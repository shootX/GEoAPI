<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Access\AdminAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (AdminAccess::allows($user)) {
            $response = $next($request);
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

            return $response;
        }

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        abort(404);
    }
}
