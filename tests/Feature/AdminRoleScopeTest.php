<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Access\AdminAccess;
use App\Models\User;
use Electrik\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Electrik lets a team owner make any member a team "admin". That team role shares its name with the
 * platform admin role, so only an admin role held in a team the user owns may count as platform admin.
 */
final class AdminRoleScopeTest extends TestCase
{
    use RefreshDatabase;

    private function adminIn(User $user, int $teamId): void
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($teamId);
        $user->assignRole(Role::findOrCreateForTeam('admin', 'web', $teamId));
    }

    public function test_a_team_admin_in_someone_elses_team_is_not_a_platform_admin(): void
    {
        $owner = User::factory()->create();
        $team = $owner->createOwnedTeam(['name' => 'Owner team'], true);
        $member = User::factory()->create();
        $this->adminIn($member, (int) $team->getKey());

        $this->assertFalse(AdminAccess::allows($member->fresh()));
        $this->actingAs($member)->get('/admin/blog')->assertNotFound();
    }

    public function test_an_admin_role_in_the_users_own_team_still_counts(): void
    {
        $admin = User::factory()->create();
        $team = $admin->createOwnedTeam(['name' => 'Admin team'], true);
        $this->adminIn($admin, (int) $team->getKey());

        $this->assertTrue(AdminAccess::allows($admin->fresh()));
    }
}
