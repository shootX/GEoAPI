<?php

declare(strict_types=1);

namespace App\Access;

use App\Models\User;
use Electrik\Support\Operators;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Who may use the admin panel, internal docs and admin API.
 *
 * An operator email alone is not enough: a later account that differs only in letter case
 * (OPS@… vs ops@…) must not inherit operator rights. The account must have a verified email,
 * or be the oldest account holding that address.
 */
final class AdminAccess
{
    public static function allows(?User $user): bool
    {
        return $user instanceof User && (self::operator($user) || self::hasAdminRole($user));
    }

    public static function operator(?User $user): bool
    {
        if (! $user instanceof User || ! Operators::check($user)) {
            return false;
        }
        if ($user->hasVerifiedEmail()) {
            return true;
        }

        return ! User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim((string) $user->email))])
            ->where('id', '<', $user->getKey())
            ->exists();
    }

    public static function hasAdminRole(User $user): bool
    {
        try {
            return DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', $user->getMorphClass())
                ->where('model_has_roles.model_id', $user->getKey())
                ->where('roles.name', 'admin')
                ->exists();
        } catch (Throwable) {
            return false;
        }
    }
}
