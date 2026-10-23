<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Geoapi\Platform\Passwords;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a sign-in with a password that breaks Geoapi\Platform\Passwords, every page except
 * the password form, logout and the pages that form's guards send to redirects there until the password hash changes.
 */
final class RequireStrongPassword
{
    private const KEY = 'password.weak';

    public static function weak(string $password): bool
    {
        return Passwords::error($password) !== null;
    }

    public static function flag(Authenticatable $user): void
    {
        session([self::KEY => self::fingerprint($user)]);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $flag = $request->hasSession() ? $request->session()->get(self::KEY) : null;
        if (! is_string($flag)) {
            return $next($request);
        }
        $user = $request->user();
        if ($user === null || ! hash_equals($flag, self::fingerprint($user))) {
            $request->session()->forget(self::KEY);

            return $next($request);
        }
        // The password form sits behind verified, team and onboarding guards; their pages stay open so the redirects cannot loop.
        if ($request->routeIs('settings.security', 'logout', 'verification.*', 'teams.create', 'onboarding')) {
            return $next($request);
        }
        // A Livewire call runs this again as persistent middleware, against the page the component is on.
        // Redirecting the call itself here blocked the password form's own submit.
        if ($request->routeIs('*livewire.update')) {
            return $next($request);
        }

        return redirect()->route('settings.security')
            ->with('password_notice', __('Your password does not meet the new rules. Set a new one to continue.'));
    }

    private static function fingerprint(Authenticatable $user): string
    {
        return hash('sha256', (string) $user->getAuthPassword());
    }
}
