<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Auth\Register;
use Carapi\Platform\Connection as PlatformConnection;
use Carapi\Platform\FakeMailer;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * QA retest M2: the first verification email was a bare link in Georgian only.
 * No RefreshDatabase: Tokens::issue opens its own transaction on the shared PDO. The in-memory
 * database is migrated here and disappears with the application.
 */
final class PlatformLettersTest extends TestCase
{
    private const ENV = ['CARAPI_MAIL_TRANSPORT' => 'fake', 'CARAPI_APP_URL' => 'http://localhost'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        DB::statement("ATTACH DATABASE ':memory:' AS platform");
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('now', static fn (): string => gmdate('Y-m-d H:i:s'));
        DB::statement('CREATE TABLE platform.email_tokens (id integer primary key, user_id integer, purpose text, token_hash text, email_snapshot text, expires_at text, revoked_at text, used_at text)');
        DB::statement("CREATE TABLE platform.users (id integer primary key, first_name text, last_name text, email text, password_hash text, terms_version text, terms_accepted_at text, status text not null default 'active', email_verified_at text, auth_version integer not null default 1, grandfathered integer not null default 1)");
        PlatformConnection::use($pdo);
        config(['electrik.auth.email_verification' => false]);
        FakeMailer::reset();
        $_ENV = self::ENV + $_ENV;
    }

    protected function tearDown(): void
    {
        foreach (array_keys(self::ENV) as $name) {
            unset($_ENV[$name]);
        }
        PlatformConnection::reset();
        parent::tearDown();
    }

    public function test_the_first_verification_email_explains_the_link_in_both_languages(): void
    {
        Livewire::test(Register::class)
            ->set('name', 'Letter Tester')
            ->set('email', 'letter@example.com')
            ->set('password', 'correct-horse-battery-x')
            ->set('password_confirmation', 'correct-horse-battery-x')
            ->set('accept_terms', true)
            ->call('register')
            ->assertHasNoErrors();

        $this->assertCount(1, FakeMailer::$messages);
        $letter = FakeMailer::$messages[0];
        $this->assertStringContainsString('Confirm your email', $letter['subject']);
        $this->assertStringContainsString('დაადასტურეთ', $letter['text']);
        $this->assertStringContainsString('Confirm the email', $letter['text']);
        $this->assertMatchesRegularExpression('#http://localhost/email/verify\?token=\S+#', $letter['text']);
    }
}
