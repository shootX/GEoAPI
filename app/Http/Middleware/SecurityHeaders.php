<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict Content-Security-Policy (QA GEO-QA-022).
 *
 * Scripts run only from this origin or with the per-request nonce: no 'unsafe-inline',
 * no third-party hosts, no inline event handlers. Livewire uses its CSP-safe Alpine build,
 * so there is no 'unsafe-eval' either, and reads the nonce from Vite::cspNonce(). Style
 * attributes stay allowed (style-src-attr) because markup and Livewire set them; <style>
 * blocks need the nonce.
 *
 * Exception: the Electrik/Slate UI kit (login, onboarding, settings, teams, ops) writes
 * Alpine methods inline, which the CSP-safe build cannot parse. A page that renders one of
 * those views calls needsEval(): it gets Livewire's standard build and 'unsafe-eval', still
 * with the nonce and without 'unsafe-inline'.
 *
 * The two OpenAPI viewers (Redoc, Swagger UI) are served from public/vendor and inject
 * their own <style> tags and a blob: search worker, so only those pages allow that.
 */
final class SecurityHeaders
{
    /** Pages that render a self-hosted OpenAPI viewer. */
    private const VIEWERS = ['api/v1/docs', 'api/docs'];

    private const EVAL = 'csp.unsafe_eval';

    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();
        config(['livewire.csp_safe' => true]);
        $request->attributes->remove(self::EVAL);
        $response = $next($request);
        foreach (self::headers($request) as $name => $value) {
            $response->headers->set($name, $value);
        }
        if ($request->isMethod('GET') && $request->is('guide', 'privacy', 'terms', 'pricing', 'demo')) {
            $response->headers->set('Cache-Control', 'private, max-age=60');
            $response->headers->set('Vary', 'Cookie');
        }

        return $response;
    }

    /**
     * Also sent by LegacyFrontController, whose legacy pages write their own output and exit
     * before this middleware sees a response.
     *
     * @return array<string, string>
     */
    public static function headers(Request $request): array
    {
        $nonce = Vite::cspNonce() ?? Vite::useCspNonce();

        return [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Content-Security-Policy' => self::policy($nonce, $request->is(...self::VIEWERS), $request->attributes->get(self::EVAL) === true),
        ];
    }

    /** Called while rendering a UI-kit view; see the class comment. */
    public static function needsEval(): void
    {
        request()->attributes->set(self::EVAL, true);
        config(['livewire.csp_safe' => false]);
    }

    public static function policy(string $nonce, bool $viewer = false, bool $eval = false): string
    {
        $styles = $viewer ? "'self' 'unsafe-inline'" : "'self' 'nonce-".$nonce."'";

        return implode('; ', [
            "default-src 'none'",
            "script-src 'self' 'nonce-".$nonce."'".($eval ? " 'unsafe-eval'" : ''),
            "script-src-attr 'none'",
            'style-src '.$styles,
            "style-src-attr 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "manifest-src 'self'",
            "media-src 'self'",
            'worker-src '.($viewer ? "'self' blob:" : "'none'"),
            "frame-src 'none'",
            "child-src 'none'",
            "object-src 'none'",
            "base-uri 'none'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            'upgrade-insecure-requests',
        ]);
    }
}
