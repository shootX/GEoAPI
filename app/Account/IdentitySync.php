<?php

declare(strict_types=1);

namespace App\Account;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class IdentitySync
{
    public static bool $fromLegacy = false;

    public static function markLaravelVerified(int $legacyUserId): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'legacy_platform_user_id')) {
            return;
        }
        User::query()
            ->where('legacy_platform_user_id', $legacyUserId)
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public static function ensure(int $legacyUserId): void
    {
        $row = DB::table('platform.users')->where('id', $legacyUserId)->first();
        if ($row === null) {
            return;
        }

        $email = mb_strtolower(trim((string) $row->email));
        $name = trim((string) $row->first_name.' '.(string) $row->last_name);
        $hash = (string) $row->password_hash;
        $suspended = (string) $row->status === 'suspended';

        $user = User::query()->where('legacy_platform_user_id', $legacyUserId)->first()
            ?? User::query()->whereRaw('lower(email) = ?', [$email])->first();

        self::$fromLegacy = true;
        try {
            if ($user === null) {
                $user = new User;
            }

            $user->forceFill([
                'name' => $name !== '' ? $name : $email,
                'email' => $email,
                'password' => $hash,
                'email_verified_at' => $row->email_verified_at,
                'legacy_platform_user_id' => $legacyUserId,
                'onboarding_completed_at' => $user->onboarding_completed_at ?? now(),
                'suspended_at' => $suspended ? ($user->suspended_at ?? now()) : null,
            ]);
            $user->save();

            if (method_exists($user, 'ownedTeams') && $user->ownedTeams()->count() === 0 && method_exists($user, 'createOwnedTeam')) {
                $user->createOwnedTeam(['name' => $name !== '' ? $name : $email], true);
            }

            DB::table('legacy_id_maps')->updateOrInsert(
                ['entity' => 'user', 'old_id' => $legacyUserId],
                ['new_id' => $user->id, 'updated_at' => now(), 'created_at' => now()]
            );
        } finally {
            self::$fromLegacy = false;
        }
    }
}
