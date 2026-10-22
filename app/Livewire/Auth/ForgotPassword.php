<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;

/** Electrik reports "no such user" and "throttled"; one answer for every email keeps accounts from being looked up. */
final class ForgotPassword extends \Electrik\Livewire\Auth\ForgotPassword
{
    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        Password::sendResetLink(['email' => $this->email]);

        $this->status = __(Password::RESET_LINK_SENT);
    }
}
