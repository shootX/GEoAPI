<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** A new password must sign out every other device, including "remember me" cookies. */
final class PasswordChangeSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_password_change_ends_the_accounts_other_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['remember_token' => 'old-remember-token']);
        $other = User::factory()->create();
        foreach (['laptop' => $user->id, 'phone' => $user->id, 'someone-else' => $other->id] as $id => $owner) {
            DB::table('sessions')->insert(['id' => $id, 'user_id' => $owner, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()]);
        }

        $user->forceFill(['password' => Hash::make('A-new-Passw0rd!')])->save();

        $this->assertSame(['someone-else'], DB::table('sessions')->pluck('id')->all());
        $this->assertNotSame('old-remember-token', $user->fresh()->remember_token);
    }

    public function test_other_profile_changes_keep_sessions(): void
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['remember_token' => 'kept']);
        DB::table('sessions')->insert(['id' => 'laptop', 'user_id' => $user->id, 'ip_address' => null, 'user_agent' => null, 'payload' => '', 'last_activity' => time()]);

        $user->forceFill(['name' => 'Renamed'])->save();

        $this->assertSame(1, DB::table('sessions')->count());
        $this->assertSame('kept', $user->fresh()->remember_token);
    }
}
