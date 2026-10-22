<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Http\Middleware\RequireLongPassword;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('components.layouts.auth')]
#[Title('შესვლა')]
final class Login extends \Electrik\Livewire\Auth\Login
{
    /** Accounts made before the 15-character rule still sign in, but only to change the password. */
    public function login(): void
    {
        if ($this->requiresTwoFactor) {
            // The password was checked in the first step and the field is empty now.
            parent::login();

            return;
        }
        $short = RequireLongPassword::tooShort($this->password);

        parent::login();

        if ($this->requiresTwoFactor) {
            session(['login.password_too_short' => $short]);
        }
        $this->restrictShortPassword($short);
    }

    public function verifyTwoFactor(): void
    {
        parent::verifyTwoFactor();

        $this->restrictShortPassword((bool) session()->pull('login.password_too_short', false));
    }

    private function restrictShortPassword(bool $short): void
    {
        if ($short && Auth::check()) {
            RequireLongPassword::flag(Auth::user());
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
