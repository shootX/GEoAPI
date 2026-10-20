<?php

declare(strict_types=1);

namespace App\Providers;

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
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Electrik's SetLocale replaces unsupported codes, including ka, and
        // app()->setLocale() also overwrites config('app.locale').
        config(['carapi.locale' => (string) config('app.locale')]);

        $this->app->booted(function (): void {
            require base_path('routes/admin-api.php');
            require base_path('routes/product.php');
            Route::middleware('web')->group(base_path('routes/legacy.php'));
        });

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
        Livewire::component('account.products', Products::class);
        Livewire::component('account.projects', Projects::class);
        Livewire::component('account.errors', Errors::class);
        Livewire::component('electrik.pricing', Pricing::class);
    }
}
