<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Electrik\Support\Operators;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        return Operators::check($user) || $this->hasAdminRole($user);
    }

    private function hasAdminRole(User $user): bool
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->getKey())
            ->where('roles.name', 'admin')
            ->exists();
    }
}
