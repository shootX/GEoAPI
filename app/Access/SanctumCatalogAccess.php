<?php

declare(strict_types=1);

namespace App\Access;

use Geoapi\Catalog\DeniedException;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

final class SanctumCatalogAccess
{
    /**
     * @return array{key_id:int,project_id:int}|null
     */
    public static function allow(string $token): ?array
    {
        if (! str_contains($token, '|')) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($token);
        if ($accessToken === null || self::expired($accessToken)) {
            throw new DeniedException(401);
        }

        $abilities = $accessToken->abilities ?? [];
        $canCars = in_array('*', $abilities, true) || in_array('cars:read', $abilities, true);
        if (! $canCars) {
            throw new DeniedException(403);
        }

        $user = $accessToken->tokenable;
        $legacyUserId = is_object($user) && isset($user->legacy_platform_user_id)
            ? (int) $user->legacy_platform_user_id
            : 0;
        if ($legacyUserId < 1) {
            throw new DeniedException(403);
        }
        // Same owner rules as a geoapi_sk_ key (Geoapi\Platform\ApiKeys): active and verified.
        $owner = DB::table('platform.users')->where('id', $legacyUserId)->first(['status', 'email_verified_at']);
        if ($owner === null || (string) $owner->status !== 'active' || $owner->email_verified_at === null || $user->suspended_at !== null) {
            throw new DeniedException(403);
        }
        if (! ProductAccess::allowsUser($legacyUserId, 'cars')) {
            throw new DeniedException(403);
        }

        $projectHeader = $_SERVER['HTTP_X_GEOAPI_PROJECT'] ?? '';
        if (! is_string($projectHeader) || preg_match('/^[1-9][0-9]*$/', $projectHeader) !== 1) {
            throw new DeniedException(403);
        }

        $projectId = (int) $projectHeader;
        if (! ProductAccess::allowsProject($projectId, 'cars')) {
            throw new DeniedException(403);
        }

        $owner = DB::table('platform.projects')
            ->where('id', $projectId)
            ->where('user_id', $legacyUserId)
            ->where('status', 'active')
            ->exists();
        if (! $owner) {
            throw new DeniedException(403);
        }

        return ['key_id' => 0, 'project_id' => $projectId];
    }

    private static function expired(PersonalAccessToken $token): bool
    {
        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return true;
        }
        $minutes = config('sanctum.expiration');

        return is_numeric($minutes) && $token->created_at !== null && $token->created_at->lte(now()->subMinutes((int) $minutes));
    }
}
