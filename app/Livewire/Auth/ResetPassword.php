<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Electrik\Actions\Auth\ResetUserPassword;
use Illuminate\Validation\Rules\Password;

/** Electrik checks min:8; GeoAPI accounts need the same rule as registration (Password::defaults()). */
final class ResetPassword extends \Electrik\Livewire\Auth\ResetPassword
{
    public function resetPassword(ResetUserPassword $resetUserPassword): void
    {
        $this->validate(['password' => ['required', 'string', Password::defaults(), 'confirmed']]);

        parent::resetPassword($resetUserPassword);
    }
}
