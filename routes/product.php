<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DisableComingSoonController;
use App\Http\Controllers\ComingSoonSignupController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PhotoReportController;
use App\Http\Controllers\PublicPageController;
use App\Livewire\Account\Errors;
use App\Livewire\Account\Products;
use App\Livewire\Account\Projects;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch')->where('locale', 'ka|en');
    Route::get('/{locale}', function (string $locale) {
        session(['locale' => $locale]);

        return redirect('/');
    })->where('locale', 'ka|en');
    Route::get('/', [PublicPageController::class, 'home']);
    Route::get('/demo', [PublicPageController::class, 'demo']);
    Route::get('/guide', [PublicPageController::class, 'guide']);
    Route::get('/privacy', [PublicPageController::class, 'privacy']);
    Route::get('/terms', [PublicPageController::class, 'terms']);
    Route::redirect('/docs', '/guide');
    Route::post('/admin/coming-soon', DisableComingSoonController::class)
        ->middleware('auth')
        ->name('admin.coming-soon.disable');
    Route::post('/coming-soon', [ComingSoonSignupController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('coming-soon.store');
    Route::post('/api/models/{id}/photo/report', PhotoReportController::class)
        ->where('id', '[0-9]+')
        ->middleware('throttle:6,1');
});

Route::get('/account', Projects::class)
    ->middleware(['web', 'auth'])
    ->name('account.home');

Route::get('/account/errors', Errors::class)
    ->middleware(['web', 'auth'])
    ->name('account.errors');

Route::get('/account/products', Products::class)
    ->middleware(['web', 'auth'])
    ->name('account.products');

Route::post('/billing/provider-webhook', PaymentWebhookController::class)
    ->name('billing.provider-webhook');
