<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ApplyChosenLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $choice = $request->session()->get('locale');
        if (! is_string($choice) || ! in_array($choice, ['ka', 'en'], true)) {
            $user = $request->user();
            $choice = is_object($user) ? ($user->locale ?? null) : null;
        }
        if (! is_string($choice) || ! in_array($choice, ['ka', 'en'], true)) {
            $choice = (string) config('geoapi.locale');
        }
        // Electrik only knows en/es/fr/ar/hi and would replace ka with en.
        if (in_array($choice, ['ka', 'en'], true)) {
            app()->setLocale($choice);
        }

        return $next($request);
    }
}
