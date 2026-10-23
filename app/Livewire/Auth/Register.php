<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Models\User;
use Closure;
use Electrik\Actions\Auth\CreateNewUser;
use Electrik\Support\Onboarding;
use Electrik\Support\TeamInviteContext;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
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

        $this->email = trim($this->email);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', $this->emailIsFree(...)],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
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

    /**
     * Emails are compared without letter case, against both Laravel users and legacy platform users.
     * A platform-only account belongs to whoever owns that mailbox: it is claimed by signing in or
     * resetting the password, never by registering again (that would hand over its projects and keys).
     */
    private function emailIsFree(string $attribute, mixed $value, Closure $fail): void
    {
        $email = mb_strtolower(trim((string) $value));
        $taken = User::query()->whereRaw('lower(email) = ?', [$email])->exists();
        if (! $taken) {
            try {
                $taken = DB::table('platform.users')->whereRaw('lower(email) = ?', [$email])->exists();
            } catch (QueryException) {
                // No platform schema in this environment.
            }
        }
        if ($taken) {
            $fail(__('This email is already registered. Sign in or reset your password.'));
        }
    }

    public function render()
    {
        return view('livewire.auth.register')->layoutData([
            'title' => __('Create account').' — GeoAPI',
            'description' => \Carapi\Platform\Passwords::rule(app()->getLocale()),
        ]);
    }
}
