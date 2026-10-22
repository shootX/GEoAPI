<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\RequireLongPassword;
use App\Http\Middleware\SecurityHeaders;
use App\Listeners\ProvisionPlatformUser;
use App\Livewire\Account\Errors;
use App\Livewire\Account\Products;
use App\Livewire\Account\Projects;
use App\Livewire\Auth\Register;
use App\Livewire\Dashboard;
use App\Livewire\Pricing;
use App\Livewire\Settings\ApiTokens;
use App\Models\User;
use App\Observers\UserObserver;
use Carapi\Platform\Passwords as PlatformPasswords;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // GeoAPI bills through BOG. Cashier's Stripe webhook accepted unsigned events (QA GEO-QA-006).
        Cashier::ignoreRoutes();
    }

    public function boot(): void
    {
        // <script @nonce> / <style @nonce>: the per-request CSP nonce (SecurityHeaders, QA GEO-QA-022).
        // Electrik/Slate views whose inline Alpine code Alpine's CSP build cannot parse
        // (methods, arrow functions, template literals, optional chaining).
        View::composer([
            'electrik::livewire.auth.login',
            'electrik::livewire.settings.security',
            'electrik::livewire.clients.index',
            'electrik::livewire.projects.index',
            'slate::components.toaster',
            'slate::components.toast-close',
            'slate::components.partials.overlay-root-attrs',
            'slate::components.partials.focus-trap-attrs',
            'slate::components.partials.file-input-control',
            'slate::components.accordion*',
            'slate::components.calendar',
            'slate::components.carousel*',
            'slate::components.combobox*',
            'slate::components.command-empty',
            'slate::components.context-menu*',
            'slate::components.dark-mode-toggle',
            'slate::components.dropdown-menu',
            'slate::components.hover-card',
            'slate::components.menubar*',
            'slate::components.navigation-menu*',
            'slate::components.popover',
            'slate::components.rating',
            'slate::components.resizable*',
            'slate::components.slider',
            'slate::components.spotlight',
            'slate::components.toggle-group',
        ], static fn () => SecurityHeaders::needsEval());
        Blade::directive('nonce', static fn (): string => '<?php echo \'nonce="\'.e((string) \Illuminate\Support\Facades\Vite::cspNonce()).\'"\'; ?>');
        // Electrik's SetLocale replaces unsupported codes, including ka, and
        // app()->setLocale() also overwrites config('app.locale').
        config(['carapi.locale' => (string) config('app.locale')]);

        // Registered before Electrik's /dashboard so the cabinet wins the match.
        // It must keep the name: both routes share GET /dashboard, so the
        // route collection (and route:cache) keeps only one of them, and
        // Electrik views call route('dashboard') (403 page, app layout).
        Route::middleware(['web', 'auth'])->get('/dashboard', Projects::class)->name('dashboard');

        $this->app->booted(function (): void {
            require base_path('routes/admin-api.php');
            require base_path('routes/product.php');
            Route::middleware('web')->group(base_path('routes/legacy.php'));
        });

        // Same 15-128 rule as registration and Carapi\Platform\Passwords, for password change and reset.
        Password::defaults(static fn (): Password => Password::min(PlatformPasswords::MIN)->max(PlatformPasswords::MAX));
        Livewire::addPersistentMiddleware(RequireLongPassword::class);
        User::observe(UserObserver::class);
        $platform = ProvisionPlatformUser::class;
        Event::listen(Registered::class, [$platform, 'registered']);
        Event::listen(Login::class, [$platform, 'login']);
        Event::listen(Logout::class, [$platform, 'logout']);
        Event::listen(Verified::class, [$platform, 'verified']);
        Livewire::component('electrik.dashboard', Dashboard::class);
        Livewire::component('electrik.settings.api-tokens', ApiTokens::class);
        Livewire::component('electrik.auth.login', \App\Livewire\Auth\Login::class);
        Livewire::component('electrik.auth.register', Register::class);
        Livewire::component('electrik.auth.reset-password', \App\Livewire\Auth\ResetPassword::class);
        Livewire::component('account.products', Products::class);
        Livewire::component('account.projects', Projects::class);
        Livewire::component('account.errors', Errors::class);
        Livewire::component('electrik.pricing', Pricing::class);
    }
}
