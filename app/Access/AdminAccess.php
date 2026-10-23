<?php

declare(strict_types=1);

namespace App\Access;

use App\Models\User;
use Electrik\Support\Operators;
use Illuminate\Database\Query\Builder;
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
            return self::adminRoleHolders()->where('model_has_roles.model_id', $user->getKey())->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * model_has_roles rows that grant platform admin. Roles are team-scoped and Electrik's ordinary
     * team role is also called "admin": a team owner can give it to any member (Teams\Members::updateRole,
     * invitations). Only the role held in a team the user owns counts; Electrik never lets an owner hold
     * a non-owner role in their own team, so only admin:issue-token creates such a row.
     */
    public static function adminRoleHolders(): Builder
    {
        return DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->join('teams', 'teams.id', '=', 'model_has_roles.team_id')
            ->whereColumn('teams.owner_id', 'model_has_roles.model_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->where('roles.name', 'admin');
    }
}
