<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Http\Middleware\RequireStrongPassword;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.auth')]
#[Title('შესვლა')]
final class Login extends \Electrik\Livewire\Auth\Login
{
    /** Accounts whose password breaks the current rule still sign in, but only to change the password. */
    public function login(): void
    {
        if ($this->requiresTwoFactor) {
            // The password was checked in the first step and the field is empty now.
            parent::login();

            return;
        }
        $weak = RequireStrongPassword::weak($this->password);

        parent::login();

        if ($this->requiresTwoFactor) {
            session(['login.password_weak' => $weak]);
        }
        $this->restrictWeakPassword($weak);
    }

    public function verifyTwoFactor(): void
    {
        parent::verifyTwoFactor();

        $this->restrictWeakPassword((bool) session()->pull('login.password_weak', false));
    }

    private function restrictWeakPassword(bool $weak): void
    {
        if ($weak && Auth::check()) {
            RequireStrongPassword::flag(Auth::user());
            $this->redirectRoute('settings.security', navigate: true);
        }
    }

    public function render()
    {
        return view('livewire.auth.login')->layoutData([
            'title' => __('Sign in').' — GeoAPI',
            'description' => __('Welcome back. Enter your credentials to continue.'),
        ]);
    }
}
