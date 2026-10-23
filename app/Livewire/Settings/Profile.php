<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;

/**
 * Electrik's profile page in the cabinet design. Language is the cabinet's KA/EN switch,
 * so this form keeps only name and email (Electrik's language list has no Georgian).
 */
#[Layout('components.layouts.cabinet')]
final class Profile extends \Electrik\Livewire\Settings\Profile
{
    public function save(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getAuthIdentifier())],
        ]);

        $emailChanged = strcasecmp((string) $user->email, $validated['email']) !== 0;
        $user->forceFill(['name' => $validated['name'], 'email' => $validated['email']]);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        if ($emailChanged && method_exists($user, 'sendEmailVerificationNotification')) {
            $user->sendEmailVerificationNotification();
        }

        session()->flash('status', __('Profile updated.'));
        $this->redirect(route('settings.profile'));
    }

    public function render()
    {
        $en = app()->getLocale() === 'en';

        return view('livewire.settings.profile', parent::render()->getData())
            ->layoutData(['title' => ($en ? 'Profile' : 'პროფილი').' — GeoAPI']);
    }
}
