<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RequireStrongPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Settings\Security;
use App\Models\User;
use Geoapi\Platform\Passwords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/** Password rule: at least 8 characters with one uppercase letter, one digit and one symbol, everywhere a password is set. */
final class PasswordRuleTest extends TestCase
{
    use RefreshDatabase;

    private const STRONG = 'Correct-horse-7';

    public function test_the_rule_names_the_first_missing_part(): void
    {
        $this->assertNull(Passwords::error('Abcdef1!'));
        $this->assertNull(Passwords::error(self::STRONG));
        $this->assertStringContainsString('8', (string) Passwords::error('Ab1!'));
        $this->assertStringContainsString('დიდ ასოს', (string) Passwords::error('abcdef1!'));
        $this->assertStringContainsString('ციფრს', (string) Passwords::error('Abcdefg!'));
        $this->assertStringContainsString('სიმბოლოს', (string) Passwords::error('Abcdefg1'));
        $this->assertStringContainsString('uppercase', (string) Passwords::error('abcdef1!', 'en'));
        $this->assertNotNull(Passwords::error('A1!'.str_repeat('a', 126)));
    }

    public function test_a_weak_password_signs_in_only_to_the_password_change_page(): void
    {
        User::factory()->create(['email' => 'weak@example.com', 'password' => 'a-much-longer-passphrase']);

        Livewire::test(Login::class)
            ->set('email', 'weak@example.com')
            ->set('password', 'a-much-longer-passphrase')
            ->call('login')
            ->assertRedirect(route('settings.security'));

        $this->assertAuthenticated();
        $this->get('/account')->assertRedirect(route('settings.security'));
        // A user without a team is sent on to create one, and that page must not bounce back.
        $this->get(route('settings.security'))->assertRedirect(route('teams.create'));
        $this->get(route('teams.create'))->assertOk();
    }

    public function test_setting_a_strong_password_lifts_the_restriction(): void
    {
        $user = User::factory()->create(['email' => 'weak@example.com', 'password' => 'nine-char']);
        Livewire::test(Login::class)->set('email', 'weak@example.com')->set('password', 'nine-char')->call('login');

        Livewire::actingAs($user)->test(Security::class)
            ->set('current_password', 'nine-char')
            ->set('password', self::STRONG)
            ->set('password_confirmation', self::STRONG)
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check(self::STRONG, $user->fresh()->password));
        $this->get('/account')->assertOk();
    }

    /** The password form's own Livewire call was redirected away, so the password never changed. */
    public function test_a_livewire_call_is_not_redirected_while_the_password_is_weak(): void
    {
        $user = User::factory()->create(['password' => 'nine-char']);
        $this->actingAs($user);
        RequireStrongPassword::flag($user);

        $request = Request::create('/livewire-abc/update', 'POST');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);
        $route = (new Route('POST', 'livewire-abc/update', []))->name('livewire.update');
        $request->setRouteResolver(fn () => $route);

        $response = (new RequireStrongPassword)->handle($request, fn () => new Response('ok'));
        $this->assertSame('ok', $response->getContent());

        $page = Request::create('/account', 'GET');
        $page->setLaravelSession(app('session.store'));
        $page->setUserResolver(fn () => $user);
        $page->setRouteResolver(fn () => (new Route('GET', 'account', []))->name('account.home'));
        $this->assertTrue((new RequireStrongPassword)->handle($page, fn () => new Response('ok'))->isRedirect(route('settings.security')));
    }

    public function test_a_strong_password_signs_in_normally(): void
    {
        User::factory()->create(['email' => 'strong@example.com', 'password' => self::STRONG]);

        $login = Livewire::test(Login::class)
            ->set('email', 'strong@example.com')
            ->set('password', self::STRONG)
            ->call('login');

        $this->assertNotSame(route('settings.security'), $login->effects['redirect'] ?? null);

        $this->get('/account')->assertOk();
    }

    public function test_a_new_password_that_breaks_the_rule_is_refused_everywhere(): void
    {
        $user = User::factory()->create(['password' => self::STRONG]);

        foreach (['Ab1!', 'abcdefg1!', 'Abcdefgh!', 'Abcdefgh1'] as $weak) {
            Livewire::actingAs($user)->test(Security::class)
                ->set('current_password', self::STRONG)
                ->set('password', $weak)
                ->set('password_confirmation', $weak)
                ->call('updatePassword')
                ->assertHasErrors('password');

            $token = Password::createToken($user);
            Livewire::test(ResetPassword::class, ['token' => $token])
                ->set('email', $user->email)
                ->set('password', $weak)
                ->set('password_confirmation', $weak)
                ->call('resetPassword')
                ->assertHasErrors('password');

            Livewire::test(Register::class)
                ->set('name', 'New User')
                ->set('email', 'new@example.com')
                ->set('password', $weak)
                ->set('password_confirmation', $weak)
                ->set('accept_terms', true)
                ->call('register')
                ->assertHasErrors('password');
        }

        $this->assertTrue(Hash::check(self::STRONG, $user->fresh()->password));
    }

    public function test_the_error_is_in_georgian_on_a_georgian_page(): void
    {
        $user = User::factory()->create(['password' => self::STRONG]);
        app()->setLocale('ka');

        $component = Livewire::actingAs($user)->test(Security::class)
            ->set('current_password', self::STRONG)
            ->set('password', 'abcdefg1!')
            ->set('password_confirmation', 'abcdefg1!')
            ->call('updatePassword');

        $this->assertSame([Passwords::error('abcdefg1!')], $component->errors()->get('password'));
    }

    public function test_a_weak_password_with_two_factor_is_also_sent_to_the_password_page(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        User::factory()->create([
            'email' => 'twofa@example.com',
            'password' => 'nine-char',
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        $login = Livewire::test(Login::class)
            ->set('email', 'twofa@example.com')
            ->set('password', 'nine-char')
            ->call('login')
            ->assertSet('requiresTwoFactor', true);
        $this->assertGuest();

        $login->set('twoFactorCode', (new Google2FA)->getCurrentOtp($secret))
            ->call('login')
            ->assertRedirect(route('settings.security'));
        $this->assertAuthenticated();
        $this->get('/account')->assertRedirect(route('settings.security'));
    }

    public function test_a_strong_password_with_two_factor_signs_in_normally(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        User::factory()->create([
            'email' => 'twofa-strong@example.com',
            'password' => self::STRONG,
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_confirmed_at' => now(),
        ]);

        Livewire::test(Login::class)
            ->set('email', 'twofa-strong@example.com')
            ->set('password', self::STRONG)
            ->call('login')
            ->set('twoFactorCode', (new Google2FA)->getCurrentOtp($secret))
            ->call('login');

        $this->assertAuthenticated();
        $this->get('/account')->assertOk();
    }

    public function test_settings_pages_use_the_cabinet_design(): void
    {
        $user = User::factory()->create(['password' => self::STRONG, 'onboarding_completed_at' => now()]);
        $user->createOwnedTeam(['name' => 'Team'], true);

        foreach (['settings.profile', 'settings.security'] as $page) {
            $this->actingAs($user)->get(route($page))
                ->assertOk()
                ->assertSee('class="cab ga"', false)
                ->assertSee('class="tabs-set"', false)
                ->assertDontSee('Electrik 5.5');
        }
        $this->actingAs($user)->get(route('settings.security'))->assertSee(Passwords::rule('ka'));
    }
}
