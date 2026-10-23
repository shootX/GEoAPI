<?php

use App\Http\Middleware\ApplyChosenLocale;
use App\Http\Middleware\BridgeLegacySession;
use App\Http\Middleware\ComingSoonGate;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminPanel;
use App\Http\Middleware\EnsureInternalDocs;
use App\Http\Middleware\RequestId;
use App\Http\Middleware\RequireStrongPassword;
use App\Http\Middleware\SecurityHeaders;
use Electrik\Http\Middleware\BindTeamFromAccessToken;
use Electrik\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->append(RequestId::class);
        $middleware->encryptCookies(except: [
            'geoapi_platform',
            'carapi_platform',
        ]);
        $middleware->web(append: [
            SecurityHeaders::class,
            BridgeLegacySession::class,
            SetLocale::class,
            ApplyChosenLocale::class,
            ComingSoonGate::class,
            RequireStrongPassword::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'login',
            'register',
            'logout',
            // Legacy platform pages check their own session `_csrf` token
            // (Carapi\Platform\Http) and answer 419 without it. Laravel pages
            // under /admin/blog, /admin/coming-soon and /account/* use @csrf.
            'account',
            'account/projects/*',
            'admin',
            'admin/users',
            'admin/users/*',
            'admin/projects',
            'admin/projects/*',
            'email/verify',
            'email/verification/resend',
            'forgot-password',
            'reset-password',
            'api/*',
            'widget/*',
            'billing/provider-webhook',
            'billing/bog/callback',
        ]);
        $middleware->api(append: [
            BindTeamFromAccessToken::class,
        ]);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'admin-panel' => EnsureAdminPanel::class,
            'internal-docs' => EnsureInternalDocs::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
