<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Electrik\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class CsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Laravel skips the CSRF check while unit tests run; turn it back on.
        $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    public function test_admin_blog_post_without_token_gets_419(): void
    {
        $this->actingAs($this->adminUser())
            ->post('/admin/blog/categories', ['name' => ['ka' => 'ა']])
            ->assertStatus(419);
    }

    public function test_admin_blog_post_with_token_passes_csrf(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->withSession(['_token' => 'csrf-test-token'])
            ->post('/admin/blog/categories', ['_token' => 'csrf-test-token', 'name' => ['ka' => 'ა']]);
        $this->assertNotSame(419, $response->getStatusCode());
    }

    public function test_coming_soon_disable_without_token_gets_419(): void
    {
        $this->actingAs($this->adminUser())->post('/admin/coming-soon')->assertStatus(419);
    }

    public function test_only_legacy_paths_are_exempt(): void
    {
        $except = (new \ReflectionClass(ValidateCsrfToken::class))->getStaticPropertyValue('neverVerify');
        $this->assertNotContains('admin/*', $except);
        $this->assertNotContains('account/*', $except);
        $this->assertContains('billing/bog/callback', $except);
    }

    private function adminUser(): User
    {
        $user = User::factory()->create(['email' => 'csrf-'.uniqid().'@example.com']);
        $team = $user->createOwnedTeam(['name' => 'csrf'], true);
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
        $user->assignRole(Role::findOrCreateForTeam('admin', 'web', (int) $team->getKey()));
        $this->assertTrue(DB::table('roles')->where('name', 'admin')->exists());

        return $user;
    }
}
