<?php

declare(strict_types=1);

namespace App\Observers;

use App\Account\IdentitySync;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class UserObserver
{
    public function saved(User $user): void
    {
        if ($user->wasChanged('password')) {
            self::endOtherSessions($user);
        }
        if (IdentitySync::$fromLegacy || $user->legacy_platform_user_id === null || DB::getDriverName() !== 'pgsql') {
            return;
        }

        $updates = ['updated_at' => now()];
        if ($user->wasChanged('password')) {
            $updates['password_hash'] = $user->password;
            $updates['auth_version'] = DB::raw('auth_version + 1');
        }
        if ($user->wasChanged('suspended_at')) {
            $updates['status'] = $user->suspended_at === null ? 'active' : 'suspended';
        }
        if (count($updates) === 1) {
            return;
        }

        DB::table('platform.users')->where('id', $user->legacy_platform_user_id)->update($updates);
    }

    /**
     * A new password must end every other web session and "remember me" cookie of this account,
     * not only the legacy ones (auth_version). The session that changed the password stays.
     */
    private static function endOtherSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            $current = app()->bound('session.store') && request()->hasSession() ? request()->session()->getId() : null;
            DB::connection(config('session.connection'))
                ->table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->when($current !== null, static fn ($query) => $query->where('id', '!=', $current))
                ->delete();
        }
        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }
}
