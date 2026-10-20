<?php

declare(strict_types=1);

namespace App\Observers;

use App\Account\IdentitySync;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UserObserver
{
    public function saved(User $user): void
    {
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
}
