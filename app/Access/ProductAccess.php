<?php

declare(strict_types=1);

namespace App\Access;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ProductAccess
{
    public static function allowsProject(int $projectId, string $productSlug): bool
    {
        $userId = DB::table('platform.projects')->where('id', $projectId)->value('user_id');
        if (! is_numeric($userId)) {
            return false;
        }

        return self::allowsUser((int) $userId, $productSlug);
    }

    public static function allowsUser(int $legacyUserId, string $productSlug): bool
    {
        $row = DB::table('api_entitlements as e')
            ->join('api_products as p', 'p.id', '=', 'e.api_product_id')
            ->where('e.legacy_user_id', $legacyUserId)
            ->where('p.slug', $productSlug)
            ->first(['e.status', 'e.ends_at', 'e.cancelled_at', 'p.status as product_status']);

        if ($row === null) {
            return false;
        }

        if ((string) $row->product_status !== 'published') {
            return false;
        }

        if ((string) $row->status !== 'active' || $row->cancelled_at !== null) {
            return false;
        }

        if ($row->ends_at !== null && strtotime((string) $row->ends_at) <= time()) {
            return false;
        }

        return true;
    }

    /**
     * Abilities the user may attach to a new token.
     *
     * @return array<string, string>
     */
    public static function tokenAbilitiesFor(?User $user): array
    {
        if ($user === null || $user->legacy_platform_user_id === null) {
            return [];
        }

        $rows = DB::table('api_products')
            ->where('status', 'published')
            ->orderBy('id')
            ->get(['id', 'slug', 'name']);

        $abilities = [];
        foreach ($rows as $row) {
            if (! self::allowsUser((int) $user->legacy_platform_user_id, (string) $row->slug)) {
                continue;
            }
            $abilities[$row->slug.':read'] = (string) $row->name;
        }

        return $abilities;
    }
}
