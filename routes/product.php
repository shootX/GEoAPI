<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\BlogPanelController;
use App\Http\Controllers\Admin\DisableComingSoonController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BlogFeedController;
use App\Http\Controllers\BogCallbackController;
use App\Http\Controllers\ComingSoonSignupController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MockPaymentController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\PhotoReportController;
use App\Http\Controllers\PublicOpenApiController;
use App\Http\Controllers\PublicPageController;
use App\Http\Controllers\PublicPostmanController;
use App\Http\Controllers\ReadyController;
use App\Http\Controllers\SitemapController;
use App\Livewire\Account\Billing;
use App\Livewire\Account\Errors;
use App\Livewire\Account\Products;
use App\Livewire\Account\Projects;
use Electrik\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch')->where('locale', 'ka|en');
    Route::get('/{locale}', function (string $locale) {
        session(['locale' => $locale]);

        return redirect('/');
    })->where('locale', 'ka|en');
    Route::get('/sitemap.xml', SitemapController::class);
    Route::get('/blog/feed.xml', BlogFeedController::class)->name('blog.feed');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('blog.show');
    Route::get('/{locale}/blog/feed.xml', BlogFeedController::class)->where('locale', 'ka|en');
    Route::get('/{locale}/blog', [BlogController::class, 'index'])->where('locale', 'ka|en');
    Route::get('/{locale}/blog/{slug}', [BlogController::class, 'showLocalized'])->where(['locale' => 'ka|en', 'slug' => '[a-z0-9]+(?:-[a-z0-9]+)*']);
    Route::middleware(['auth', 'admin-panel'])->group(function (): void {
        Route::get('/admin/blog', [BlogPanelController::class, 'index']);
        Route::get('/admin/blog/create', [BlogPanelController::class, 'create']);
        Route::post('/admin/blog', [BlogPanelController::class, 'store']);
        Route::get('/admin/blog/{id}/edit', [BlogPanelController::class, 'edit'])->whereNumber('id');
        Route::match(['put', 'patch'], '/admin/blog/{id}', [BlogPanelController::class, 'update'])->whereNumber('id');
        Route::delete('/admin/blog/{id}', [BlogPanelController::class, 'destroy'])->whereNumber('id');
        Route::post('/admin/blog/categories', [BlogPanelController::class, 'storeCategory']);
        Route::delete('/admin/blog/categories/{id}', [BlogPanelController::class, 'destroyCategory'])->whereNumber('id');
        Route::post('/admin/blog/tags', [BlogPanelController::class, 'storeTag']);
        Route::delete('/admin/blog/tags/{id}', [BlogPanelController::class, 'destroyTag'])->whereNumber('id');
    });
    Route::get('/api/v1/openapi.json', PublicOpenApiController::class);
    Route::get('/api/v1/postman.json', PublicPostmanController::class);
    Route::get('/api/v1/docs', [PublicPageController::class, 'reference']);
    Route::any('/api/v1/{path?}', CatalogController::class)->where('path', '.*');
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

Route::get('/account/billing', Billing::class)
    ->middleware(['web', 'auth'])
    ->name('account.billing');

// Electrik's Stripe billing screens are not GeoAPI's billing (QA GEO-QA-010).
foreach (['', '/plans', '/subscription', '/payment-methods', '/address', '/invoices', '/usage'] as $path) {
    Route::redirect('/billing'.$path, '/account/billing');
}

Route::get('/billing/test/{order}', [MockPaymentController::class, 'show'])
    ->middleware(['web', 'auth', 'signed'])
    ->name('billing.mock.show');

Route::post('/billing/test/{order}', [MockPaymentController::class, 'complete'])
    ->middleware(['web', 'auth', 'throttle:20,1'])
    ->name('billing.mock.complete');

Route::get('/account/billing/invoices/{id}', InvoiceController::class)
    ->middleware(['web', 'auth'])
    ->whereNumber('id')
    ->name('account.invoice');

Route::post('/billing/provider-webhook', PaymentWebhookController::class)
    ->name('billing.provider-webhook');

Route::post('/billing/bog/callback', BogCallbackController::class)
    ->name('billing.bog.callback');

Route::get('/ready', ReadyController::class)->name('ready');

// ELECTRIK_EMAIL_VERIFICATION=false (prod) skips Electrik's verification routes, but
// registration still mails VerifyEmail, which needs verification.verify. Keep the link working.
if (! config('electrik.auth.email_verification', true)) {
    Route::get('/verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['web', 'auth', 'signed', 'throttle:6,1'])
        ->name('verification.verify');
}
