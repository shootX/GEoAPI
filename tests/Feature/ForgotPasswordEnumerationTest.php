<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\ForgotPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/** QA retest M1: the reset form must not tell whether an email has an account. */
final class ForgotPasswordEnumerationTest extends TestCase
{
    use RefreshDatabase;

    private function ask(string $email): ?string
    {
        return Livewire::test(ForgotPassword::class)
            ->set('email', $email)
            ->call('sendResetLink')
            ->assertHasNoErrors()
            ->get('status');
    }

    public function test_known_unknown_and_repeated_emails_get_the_same_answer(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);

        $known = $this->ask('known@example.com');
        $repeated = $this->ask('known@example.com');
        $unknown = $this->ask('nobody@example.com');

        $this->assertSame(__('passwords.sent'), $known);
        $this->assertSame($known, $repeated);
        $this->assertSame($known, $unknown);
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }
}
