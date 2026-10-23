<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Carapi\Platform\Passwords;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * After a sign-in with a password shorter than Passwords::MIN, every page except
 * the password form, logout and the pages that form's guards send to redirects there until the password hash changes.
 */
final class RequireLongPassword
{
    private const KEY = 'password.too_short';

    public static function tooShort(string $password): bool
    {
        return mb_strlen($password, 'UTF-8') < Passwords::MIN;
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

        return redirect()->route('settings.security')
            ->with('status', __('Your password is shorter than 15 characters. Set a new one to continue.'));
    }

    private static function fingerprint(Authenticatable $user): string
    {
        return hash('sha256', (string) $user->getAuthPassword());
    }
}
