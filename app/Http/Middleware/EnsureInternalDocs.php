<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Access\AdminAccess;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class EnsureInternalDocs
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken() !== null) {
            Auth::shouldUse('sanctum');
        }

        $user = $request->user();
        if (! $user instanceof User || ! $this->allowed($user)) {
            return response()->json([
                'error' => ['code' => 'not_found', 'message' => 'Not found.'],
            ], 404);
        }

        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }

    private function allowed(User $user): bool
    {
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken && ! $token->can('*')) {
            return false;
        }

        return AdminAccess::allows($user);
    }
}
