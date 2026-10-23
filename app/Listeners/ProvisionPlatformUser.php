<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Account\IdentitySync;
use App\Models\User;
use Geoapi\Platform\Connection;
use Geoapi\Platform\Letters;
use Geoapi\Platform\Mail;
use Geoapi\Platform\Session;
use Geoapi\Platform\Tokens;
use Geoapi\Platform\UnavailableException;
use Geoapi\Platform\UserRepository;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
        if ($existing !== null && ! $user->hasVerifiedEmail()) {
            // An unproven claim on someone else's platform account: registration validation refuses
            // this, so reaching it means a race or a new entry point. Never link it.
            report(new \RuntimeException('Registration matched an existing platform user; not linked.'));

            return;
        }
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
            [$subject, $text] = Letters::verifyEmail(Mail::appUrl().'/email/verify?token='.$token);
            Mail::make()->send(UserRepository::email($user->email), $subject, $text);
        } catch (UnavailableException) {
            // ფოსტა ამ გარემოში გამორთულია. ანგარიში მაინც იქმნება.
        } catch (\Throwable $e) {
            report($e);
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
        // Forgetting the cookie only clears this browser; the session file must go too (QA retest H1).
        $sid = request()->cookie('geoapi_platform');
        if (is_string($sid) && preg_match('/^[a-f0-9]{32}$/', $sid) === 1) {
            @unlink($this->sessionDir().'/sess_'.$sid);
        }
        Cookie::queue(Cookie::forget('geoapi_platform'));
    }

    public function verified(Verified $event): void
    {
        $user = $event->user;
        if (! $user instanceof User || $user->legacy_platform_user_id === null) {
            return;
        }
        if (! Schema::hasTable('platform.users')) {
            return;
        }
        DB::table('platform.users')
            ->where('id', $user->legacy_platform_user_id)
            ->whereNull('email_verified_at')
            ->update([
                'email_verified_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function writeLegacySession(int $legacyId, int $version): void
    {
        $dir = $this->sessionDir();
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            return;
        }
        // Rotate, never duplicate: the sid this browser came in with (e.g. through BridgeLegacySession)
        // must stop working, or logout could only revoke the newest copy.
        $old = request()->cookie('geoapi_platform');
        if (is_string($old) && preg_match('/^[a-f0-9]{32}$/', $old) === 1) {
            @unlink($dir.'/sess_'.$old);
        }
        $sid = bin2hex(random_bytes(16));
        $now = time();
        $payload = 'user_id|i:'.$legacyId.';auth_version|i:'.$version.';issued_at|i:'.$now.';last_seen|i:'.$now.';csrf|s:64:"'.bin2hex(random_bytes(32)).'";';
        file_put_contents($dir.'/sess_'.$sid, $payload, LOCK_EX);
        Cookie::queue(cookie('geoapi_platform', $sid, 0, '/', null, true, true, true, 'lax'));
    }

    private function sessionDir(): string
    {
        return Session::path();
    }
}
