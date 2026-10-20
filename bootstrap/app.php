<?php

use App\Http\Middleware\ApplyChosenLocale;
use App\Http\Middleware\BridgeLegacySession;
use App\Http\Middleware\ComingSoonGate;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureInternalDocs;
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

        $middleware->web(append: [
            BridgeLegacySession::class,
            SetLocale::class,
            ApplyChosenLocale::class,
            ComingSoonGate::class,
        ]);
        $middleware->validateCsrfTokens(except: [
            'login',
            'register',
            'logout',
            'account',
            'account/*',
            'admin',
            'admin/*',
            'email/*',
            'forgot-password',
            'reset-password',
            'api/*',
            'widget/*',
            'billing/provider-webhook',
        ]);
        $middleware->api(append: [
            BindTeamFromAccessToken::class,
        ]);
        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'internal-docs' => EnsureInternalDocs::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
