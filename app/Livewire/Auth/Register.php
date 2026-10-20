<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Electrik\Actions\Auth\CreateNewUser;
use Electrik\Support\Onboarding;
use Electrik\Support\TeamInviteContext;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

#[Layout('electrik::components.layouts.guest')]
#[Title('ანგარიშის შექმნა')]
final class Register extends \Electrik\Livewire\Auth\Register
{
    public bool $accept_terms = false;

    public function register(CreateNewUser $createNewUser): void
    {
        abort_unless(config('electrik.auth.registration', true), 404);

        $invite = TeamInviteContext::findValidAcceptInvite(TeamInviteContext::peekToken());
        if ($invite) {
            $this->email = $invite->email;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:15', 'confirmed'],
            'accept_terms' => ['accepted'],
        ]);

        if ($invite && strcasecmp($validated['email'], $invite->email) !== 0) {
            throw ValidationException::withMessages([
                'email' => 'მოწვევა ამ ელფოსტაზეა გაგზავნილი.',
            ]);
        }

        $user = $createNewUser->execute([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);
        session()->regenerate();

        if ($user instanceof MustVerifyEmail && config('electrik.auth.email_verification', true)) {
            $this->redirect(route('verification.notice'), navigate: true);

            return;
        }

        if ($token = TeamInviteContext::peekToken()) {
            $this->redirect(route('teams.invitations.accept', $token), navigate: true);

            return;
        }

        $this->redirect(Onboarding::homePath(), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.register');
    }
}
