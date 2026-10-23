<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Electrik\Livewire\Settings\Security;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use Tests\TestCase;

/** Cursor finding: registration demands 15 characters, but a shorter password still signed in and could be set again. */
final class PasswordLengthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_short_password_signs_in_only_to_the_password_change_page(): void
    {
        User::factory()->create(['email' => 'short@example.com', 'password' => 'nine-char']);

        Livewire::test(Login::class)
            ->set('email', 'short@example.com')
            ->set('password', 'nine-char')
            ->call('login')
            ->assertRedirect(route('settings.security'));

        $this->assertAuthenticated();
        $this->get('/account')->assertRedirect(route('settings.security'));
        // A user without a team is sent on to create one, and that page must not bounce back.
        $this->get(route('settings.security'))->assertRedirect(route('teams.create'));
        $this->get(route('teams.create'))->assertOk();
    }

    public function test_setting_a_long_password_lifts_the_restriction(): void
    {
        $user = User::factory()->create(['email' => 'short@example.com', 'password' => 'nine-char']);
        Livewire::test(Login::class)->set('email', 'short@example.com')->set('password', 'nine-char')->call('login');

        Livewire::actingAs($user)->test(Security::class)
            ->set('current_password', 'nine-char')
            ->set('password', 'a-much-longer-passphrase')
            ->set('password_confirmation', 'a-much-longer-passphrase')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->get('/account')->assertOk();
    }

    public function test_a_long_password_signs_in_normally(): void
    {
        User::factory()->create(['email' => 'long@example.com', 'password' => 'correct-horse-battery-x']);

        $login = Livewire::test(Login::class)
            ->set('email', 'long@example.com')
            ->set('password', 'correct-horse-battery-x')
            ->call('login');

        $this->assertNotSame(route('settings.security'), $login->effects['redirect'] ?? null);

        $this->get('/account')->assertOk();
    }

    public function test_a_new_password_shorter_than_15_characters_is_refused(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-battery-x']);

        Livewire::actingAs($user)->test(Security::class)
            ->set('current_password', 'correct-horse-battery-x')
            ->set('password', 'nine-char')
            ->set('password_confirmation', 'nine-char')
            ->call('updatePassword')
            ->assertHasErrors('password');

        $token = Password::createToken($user);
        Livewire::test(ResetPassword::class, ['token' => $token])
            ->set('email', $user->email)
            ->set('password', 'nine-char')
            ->set('password_confirmation', 'nine-char')
            ->call('resetPassword')
            ->assertHasErrors('password');
    }

    public function test_a_short_password_with_two_factor_is_also_sent_to_the_password_page(): void
    {
        $secret = (new \PragmaRX\Google2FA\Google2FA)->generateSecretKey();
        User::factory()->create([
            'email' => 'twofa@example.com',
            'password' => 'nine-char',
            'two_factor_secret' => \Illuminate\Support\Facades\Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $login = Livewire::test(Login::class)
            ->set('email', 'twofa@example.com')
            ->set('password', 'nine-char')
            ->call('login')
            ->assertSet('requiresTwoFactor', true);
        $this->assertGuest();

        $login->set('twoFactorCode', (new \PragmaRX\Google2FA\Google2FA)->getCurrentOtp($secret))
            ->call('login')
            ->assertRedirect(route('settings.security'));
        $this->assertAuthenticated();
        $this->get('/account')->assertRedirect(route('settings.security'));
    }

    public function test_a_long_password_with_two_factor_signs_in_normally(): void
    {
        $secret = (new \PragmaRX\Google2FA\Google2FA)->generateSecretKey();
        User::factory()->create([
            'email' => 'twofa-long@example.com',
            'password' => 'correct-horse-battery-x',
            'two_factor_secret' => \Illuminate\Support\Facades\Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        Livewire::test(Login::class)
            ->set('email', 'twofa-long@example.com')
            ->set('password', 'correct-horse-battery-x')
            ->call('login')
            ->set('twoFactorCode', (new \PragmaRX\Google2FA\Google2FA)->getCurrentOtp($secret))
            ->call('login');

        $this->assertAuthenticated();
        $this->get('/account')->assertOk();
    }
}
