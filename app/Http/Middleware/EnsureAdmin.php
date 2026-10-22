<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Access\AdminAccess;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
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

        if (! AdminAccess::allows($user)) {
            return $this->deny(403, 'forbidden', 'ეს მოქმედება მხოლოდ ადმინის როლს შეუძლია.');
        }

        return $next($request);
    }

    private function deny(int $status, string $code, string $message): Response
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
