<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Account\IdentitySync;
use App\Models\User;
use Carapi\Platform\Connection;
use Carapi\Platform\Mail;
use Carapi\Platform\Tokens;
use Carapi\Platform\UnavailableException;
use Carapi\Platform\UserRepository;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;

final class ProvisionPlatformUser
{
    public function registered(Registered $event): void
    {
        $user = $event->user;
        if (! $user instanceof User) {
            return;
        }
        $parts = preg_split('/\s+/', trim($user->name)) ?: [];
        $first = (string) ($parts[0] ?? $user->email);
        $last = trim(implode(' ', array_slice($parts, 1)));
        $repo = new UserRepository(Connection::pdo());
        $existing = $repo->findByEmail($user->email);
        if ($existing === null) {
            $repo->insert([
                'first_name' => $first,
                'last_name' => $last !== '' ? $last : $first,
                'email' => UserRepository::email($user->email),
                'password_hash' => (string) $user->password,
                'terms_version' => '2026-09-26',
            ]);
        }
        $legacyId = (int) DB::table('platform.users')->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])->value('id');
        IdentitySync::ensure($legacyId);
        try {
            $token = (new Tokens(Connection::pdo()))->issue($legacyId, 'verify_email', UserRepository::email($user->email));
            Mail::make()->send(UserRepository::email($user->email), 'ელფოსტის დადასტურება', Mail::appUrl().'/email/verify?token='.$token);
        } catch (UnavailableException) {
            // ფოსტა ამ გარემოში გამორთულია. ანგარიში მაინც იქმნება.
        }
    }

    public function login(Login $event): void
    {
        $user = $event->user;
        if (! $user instanceof User || $user->legacy_platform_user_id === null) {
            return;
        }
        $platform = DB::table('platform.users')->where('id', $user->legacy_platform_user_id)->first(['auth_version', 'email_verified_at']);
        if ($platform === null) {
            return;
        }
        if ($platform->email_verified_at !== null && $user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => $platform->email_verified_at])->save();
        }
        $this->writeLegacySession((int) $user->legacy_platform_user_id, (int) $platform->auth_version);
    }

    public function logout(Logout $event): void
    {
        Cookie::queue(Cookie::forget('carapi_platform'));
    }

    private function writeLegacySession(int $legacyId, int $version): void
    {
        $dir = carapi_env('CARAPI_PLATFORM_SESSION_PATH');
        $dir = is_string($dir) && $dir !== '' ? $dir : storage_path('framework/carapi-sessions');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return;
        }
        $sid = bin2hex(random_bytes(16));
        $now = time();
        $payload = 'user_id|i:'.$legacyId.';auth_version|i:'.$version.';issued_at|i:'.$now.';last_seen|i:'.$now.';csrf|s:64:"'.bin2hex(random_bytes(32)).'";';
        file_put_contents($dir.'/sess_'.$sid, $payload, LOCK_EX);
        Cookie::queue(cookie('carapi_platform', $sid, 0, '/', null, true, true, false, 'lax'));
    }
}
