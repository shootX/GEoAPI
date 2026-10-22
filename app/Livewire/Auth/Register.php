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

#[Layout('components.layouts.auth')]
#[Title('ანგარიშის შექმნა')]
final class Register extends \Electrik\Livewire\Auth\Register
{
    public bool $accept_terms = false;

    public string $buyer_kind = 'individual';

    public string $company_name = '';

    public string $company_code = '';

    public string $company_address = '';

    public bool $vat_payer = false;

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
            'buyer_kind' => ['required', 'in:individual,legal'],
            'company_name' => ['required_if:buyer_kind,legal', 'nullable', 'string', 'max:255'],
            // Georgian identification code: 9 digits for a legal entity, 11 for an individual entrepreneur.
            'company_code' => ['required_if:buyer_kind,legal', 'nullable', 'string', 'regex:/^(\d{9}|\d{11})$/'],
            'company_address' => ['required_if:buyer_kind,legal', 'nullable', 'string', 'max:500'],
            'vat_payer' => ['boolean'],
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
        $legal = $validated['buyer_kind'] === 'legal';
        $user->forceFill([
            'buyer_kind' => $validated['buyer_kind'],
            'company_name' => $legal ? $validated['company_name'] : null,
            'company_code' => $legal ? $validated['company_code'] : null,
            'company_address' => $legal ? $validated['company_address'] : null,
            'vat_payer' => $legal && $this->vat_payer,
        ])->save();

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
        return view('livewire.auth.register')->layoutData([
            'title' => __('Create account').' — GeoAPI',
            'description' => __('Password must be at least 15 characters.'),
        ]);
    }
}
