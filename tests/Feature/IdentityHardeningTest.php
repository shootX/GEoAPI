<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Access\AdminAccess;
use App\Livewire\Auth\Register;
use App\Models\User;
use Geoapi\Platform\Connection as PlatformConnection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/** QA GEO-QA-001 and GEO-QA-002: registration must not claim another account or operator rights. */
final class IdentityHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::statement("ATTACH DATABASE ':memory:' AS platform");
        } catch (\Throwable) {
        }
        $pdo = DB::connection()->getPdo();
        $pdo->sqliteCreateFunction('now', static fn (): string => gmdate('Y-m-d H:i:s'));
        DB::statement('DROP TABLE IF EXISTS platform.email_tokens');
        DB::statement('CREATE TABLE platform.email_tokens (id integer primary key, user_id integer, purpose text, token_hash text, email_snapshot text, expires_at text, revoked_at text, used_at text)');
        DB::statement('DROP TABLE IF EXISTS platform.users');
        DB::statement("CREATE TABLE platform.users (id integer primary key, first_name text, last_name text, email text, password_hash text, terms_version text, terms_accepted_at text, status text not null default 'active', email_verified_at text, auth_version integer not null default 1, grandfathered integer not null default 1)");
        PlatformConnection::use($pdo);
        config(['electrik.operators.emails' => ['ops@example.com'], 'electrik.auth.email_verification' => false]);
    }

    protected function tearDown(): void
    {
        PlatformConnection::reset();
        parent::tearDown();
    }

    private function register(string $email): Testable
    {
        return Livewire::test(Register::class)
            ->set('name', 'Mallory Tester')
            ->set('email', $email)
            ->set('password', 'Correct-horse-battery-7')
            ->set('password_confirmation', 'Correct-horse-battery-7')
            ->set('accept_terms', true)
            ->call('register');
    }

    public function test_a_letter_case_variant_of_an_existing_email_cannot_register(): void
    {
        User::factory()->create(['email' => 'ops@example.com']);

        $this->register('OPS@Example.com')->assertHasErrors('email');

        $this->assertSame(1, User::query()->whereRaw('lower(email) = ?', ['ops@example.com'])->count());
    }

    public function test_the_email_of_a_platform_only_account_cannot_register(): void
    {
        DB::table('platform.users')->insert(['id' => 50, 'first_name' => 'Victim', 'last_name' => 'User', 'email' => 'victim@example.com', 'password_hash' => 'x', 'status' => 'active', 'email_verified_at' => '2026-01-01 00:00:00']);

        $this->register('Victim@example.com')->assertHasErrors('email');

        $this->assertNull(User::query()->where('legacy_platform_user_id', 50)->first());
        $this->assertSame(0, User::query()->whereRaw('lower(email) = ?', ['victim@example.com'])->count());
    }

    public function test_a_new_email_still_registers_and_gets_its_own_platform_account(): void
    {
        $this->register('new@example.com')->assertHasNoErrors();

        $user = User::query()->where('email', 'new@example.com')->firstOrFail();
        $this->assertNotNull($user->legacy_platform_user_id);
        $this->assertSame('new@example.com', DB::table('platform.users')->where('id', $user->legacy_platform_user_id)->value('email'));
        $this->assertNull($user->email_verified_at);
    }

    public function test_changing_an_email_to_a_case_variant_of_another_account_is_refused(): void
    {
        User::factory()->create(['email' => 'ops@example.com']);
        $other = User::factory()->create(['email' => 'someone@example.com']);

        $this->expectException(ValidationException::class);
        $other->forceFill(['email' => 'OPS@example.com'])->save();
    }

    public function test_a_later_unverified_case_variant_does_not_get_operator_rights(): void
    {
        $real = User::factory()->create(['email' => 'ops@example.com', 'email_verified_at' => null]);
        // A duplicate that predates the guards (inserted directly, as it may exist in production).
        $id = DB::table('users')->insertGetId([
            'name' => 'Mallory', 'email' => 'OPS@example.com', 'password' => bcrypt('x'),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $dupe = User::query()->findOrFail($id);

        $this->assertTrue(AdminAccess::allows($real));
        $this->assertFalse(AdminAccess::allows($dupe));
        $this->actingAs($dupe)->get('/admin/blog')->assertNotFound();
        $this->actingAs($dupe)->get('/api/docs')->assertNotFound();
        $this->actingAs($real)->get('/admin/blog')->assertOk();

        $dupe->forceFill(['email_verified_at' => now()])->saveQuietly();
        $this->assertTrue(AdminAccess::allows($dupe->fresh()), 'a verified mailbox owner is an operator');
    }
}
