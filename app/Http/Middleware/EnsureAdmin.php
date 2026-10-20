<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Electrik\Support\Operators;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if (! $user instanceof User || ! $token instanceof PersonalAccessToken) {
            return $this->deny(401, 'unauthorized', 'ავტორიზაცია ვერ შესრულდა. საჭიროა Sanctum-ის Personal Access Token.');
        }

        if (! $token->can('*')) {
            return $this->deny(403, 'forbidden', 'ტოკენს სჭირდება უფლება *.');
        }

        if (! Operators::check($user) && ! $this->hasAdminRole($user)) {
            return $this->deny(403, 'forbidden', 'ეს მოქმედება მხოლოდ ადმინის როლს შეუძლია.');
        }

        return $next($request);
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

    private function deny(int $status, string $code, string $message): Response
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
