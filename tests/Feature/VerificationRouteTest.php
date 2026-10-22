<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class VerificationRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_link_exists_and_works_when_electrik_verification_is_disabled(): void
    {
        // Boot the app the way prod is configured, so Electrik skips its own verification routes.
        putenv('ELECTRIK_EMAIL_VERIFICATION=false');
        $_ENV['ELECTRIK_EMAIL_VERIFICATION'] = $_SERVER['ELECTRIK_EMAIL_VERIFICATION'] = 'false';
        try {
            $this->refreshApplication();
            $this->artisan('migrate:fresh');
        } finally {
            putenv('ELECTRIK_EMAIL_VERIFICATION');
            unset($_ENV['ELECTRIK_EMAIL_VERIFICATION'], $_SERVER['ELECTRIK_EMAIL_VERIFICATION']);
        }
        $this->assertFalse(config('electrik.auth.email_verification'));
        $this->assertTrue(Route::has('verification.verify'));
        $this->assertFalse(Route::has('verification.notice'));

        $user = User::factory()->unverified()->create();
        Notification::fake();
        $user->sendEmailVerificationNotification();
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user): bool {
            return str_contains($n->toMail($user)->actionUrl, '/verify-email/'.$user->getKey().'/');
        });

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]);
        $this->actingAs($user)->get($url)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
    }
}
