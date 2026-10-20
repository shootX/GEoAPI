<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\User;
use Closure;
use Electrik\Support\Operators;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ComingSoonGate
{
    public const COOKIE = 'carapi_preview';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enabled()) {
            return $next($request);
        }

        if ($this->exempt($request) || $this->isAdmin($request)) {
            return $next($request);
        }

        if ($this->previewGranted($request)) {
            $response = $next($request);
            if ($this->queryGrants($request)) {
                $response->headers->setCookie($this->previewCookie($request));
            }

            return $response;
        }

        return $this->hold($request);
    }

    private function enabled(): bool
    {
        try {
            $row = Setting::query()->find('coming_soon');
        } catch (Throwable) {
            return false;
        }

        return $row instanceof Setting && $row->value === true;
    }

    private function exempt(Request $request): bool
    {
        $path = $request->path();

        if ($path === 'login' || ($path === 'coming-soon' && $request->isMethod('POST'))) {
            return true;
        }

        if ($path === 'api/admin' || str_starts_with($path, 'api/admin/') || str_starts_with($path, 'api/docs')) {
            return true;
        }

        if (preg_match('#^locale/(ka|en)$#', $path) === 1 || $path === 'ka' || $path === 'en') {
            return true;
        }

        return str_starts_with($path, 'livewire/') && $request->user() === null;
    }

    public static function allows(Request $request): bool
    {
        return (new self)->isAdmin($request);
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return false;
        }

        if (Operators::check($user)) {
            return true;
        }

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

    private function previewGranted(Request $request): bool
    {
        return $this->queryGrants($request) || $this->cookieGrants($request);
    }

    private function queryGrants(Request $request): bool
    {
        $secret = $this->secret();
        $query = $request->query('preview');

        return $secret !== '' && is_string($query) && hash_equals($secret, $query);
    }

    private function cookieGrants(Request $request): bool
    {
        $secret = $this->secret();
        $cookie = $request->cookie(self::COOKIE);

        return $secret !== '' && is_string($cookie) && hash_equals($secret, $cookie);
    }

    private function secret(): string
    {
        $secret = config('coming_soon.preview_secret');

        return is_string($secret) ? $secret : '';
    }

    private function previewCookie(Request $request): Cookie
    {
        return cookie(
            self::COOKIE,
            $this->secret(),
            60 * 24 * 30,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax',
        );
    }

    private function hold(Request $request): Response
    {
        $headers = [
            'Retry-After' => (string) config('coming_soon.retry_after', 86400),
            'Cache-Control' => 'no-store',
        ];

        if ($this->machine($request)) {
            return response()->json(['error' => 'coming_soon'], 503, $headers);
        }

        return response()->view('coming-soon', [], 503, $headers);
    }

    private function machine(Request $request): bool
    {
        $path = strtolower($request->path());

        return $request->expectsJson()
            || str_starts_with($path, 'api/')
            || str_starts_with($path, 'widget/');
    }
}
