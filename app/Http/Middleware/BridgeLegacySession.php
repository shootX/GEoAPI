<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Carapi\Platform\Session;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class BridgeLegacySession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            $sid = $request->cookie('geoapi_platform');
            $legacyId = is_string($sid) && $this->live($sid) ? $this->legacyUserId($sid) : null;
            if ($legacyId !== null) {
                $platform = DB::table('platform.users')->where('id', $legacyId)->first(['status', 'auth_version']);
                $user = User::query()->where('legacy_platform_user_id', $legacyId)->first();
                $version = $this->sessionVersion($sid);
                if (
                    $user instanceof User
                    && $platform !== null
                    && (string) $platform->status === 'active'
                    && $user->suspended_at === null
                    && $version === (int) $platform->auth_version
                ) {
                    Auth::login($user);
                }
            }
        }

        return $next($request);
    }

    private function legacyUserId(string $sid): ?int
    {
        if (preg_match('/^[a-f0-9]{32}$/', $sid) !== 1) {
            return null;
        }
        $file = $this->sessionFile($sid);
        if (! is_file($file)) {
            return null;
        }
        $raw = (string) file_get_contents($file);
        if (preg_match('/user_id\|i:(\d+);/', $raw, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function sessionVersion(string $sid): ?int
    {
        $file = $this->sessionFile($sid);
        if (! is_file($file)) {
            return null;
        }
        $raw = (string) file_get_contents($file);
        if (preg_match('/auth_version\|i:(\d+);/', $raw, $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    /** An expired session file is deleted, so the copied cookie can never sign in again. */
    private function live(string $sid): bool
    {
        if (preg_match('/^[a-f0-9]{32}$/', $sid) !== 1) {
            return false;
        }
        $file = $this->sessionFile($sid);
        if (! is_file($file)) {
            return false;
        }
        $raw = (string) file_get_contents($file);
        $issued = preg_match('/issued_at\|i:(\d+);/', $raw, $match) === 1 ? (int) $match[1] : 0;
        $seen = preg_match('/last_seen\|i:(\d+);/', $raw, $match) === 1 ? (int) $match[1] : 0;
        if (Session::expired($issued, $seen)) {
            @unlink($file);

            return false;
        }

        return true;
    }

    private function sessionFile(string $sid): string
    {
        return Session::path().'/sess_'.$sid;
    }
}
