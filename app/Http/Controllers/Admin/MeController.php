<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json([
                'error' => ['code' => 'unauthorized', 'message' => 'ავტორიზაცია ვერ შესრულდა.'],
            ], 401);
        }
        $token = $user->currentAccessToken();
        $roles = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', $user->getMorphClass())
            ->where('model_has_roles.model_id', $user->getKey())
            ->pluck('roles.name')
            ->unique()
            ->values();

        return response()->json([
            'data' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'token' => [
                    'name' => $token?->name,
                    'abilities' => $token?->abilities,
                ],
            ],
        ]);
    }
}
