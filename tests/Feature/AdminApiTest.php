<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Electrik\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalogSchema();
    }

    public function test_docs_are_hidden_without_admin_access(): void
    {
        $this->get('/api/docs')->assertNotFound();
        $this->get('/api/docs/openapi.json')->assertNotFound();

        $user = User::factory()->create();
        $this->actingAs($user)->get('/api/docs')->assertNotFound();

        $token = $user->createToken('limited', ['cars:read'])->plainTextToken;
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->get('/api/docs/openapi.json')
            ->assertNotFound();
    }

    public function test_operator_session_can_read_docs(): void
    {
        $user = User::factory()->create(['email' => 'ops@example.com']);
        config(['electrik.operators.emails' => ['ops@example.com']]);

        $this->actingAs($user)->get('/api/docs')->assertOk()->assertSee('GET /api/admin/pages', false);
    }

    public function test_docs_list_admin_routes(): void
    {
        $token = $this->adminToken();
        $response = $this->withHeader('Authorization', 'Bearer '.$token)->get('/api/docs');

        $response->assertOk();
        $response->assertSee('GET /api/admin/pages', false);
        $response->assertSee('DELETE /api/admin/cars/{id}', false);
        $response->assertSee('/api/docs/openapi.json', false);

        $spec = $this->withHeader('Authorization', 'Bearer '.$token)->get('/api/docs/openapi.json');
        $spec->assertOk();
        $spec->assertJsonPath('openapi', '3.0.3');
        $spec->assertJsonStructure(['paths' => ['/api/admin/pages' => ['get', 'post']]]);
    }

    public function test_token_without_admin_role_is_forbidden(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('limited', ['*'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/me')
            ->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_a_page(): void
    {
        $token = $this->adminToken();

        $created = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/pages', [
                'slug' => 'privacy',
                'title' => 'კონფიდენციალურობა',
                'body' => '<p>ახალი ტექსტი</p>',
                'status' => 'published',
            ]);
        $created->assertCreated();
        $id = (int) $created->json('data.id');

        $this->get('/privacy')->assertOk()->assertSee('ახალი ტექსტი', false);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/admin/pages/'.$id, ['title' => 'განახლებული'])
            ->assertOk()
            ->assertJsonPath('data.title', 'განახლებული');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/pages/'.$id)
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/pages/'.$id)
            ->assertNotFound();
    }

    public function test_admin_can_write_a_catalog_brand_and_a_setting(): void
    {
        $token = $this->adminToken();

        $brand = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/brands', [
                'slug' => 'probe',
                'name' => 'Probe',
                'source_url' => 'https://example.test/probe',
                'synonyms' => ['p'],
            ]);
        $brand->assertCreated()->assertJsonPath('data.slug', 'probe');
        $id = (int) $brand->json('data.id');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/admin/brands/'.$id)
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/settings', [
                'key' => 'site.contact_email',
                'value' => ['email' => 'hello@geoapi.ge'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.value.email', 'hello@geoapi.ge');
    }

    public function test_duplicate_page_slug_is_a_conflict(): void
    {
        $token = $this->adminToken();
        $payload = [
            'slug' => 'privacy',
            'title' => 'კონფიდენციალურობა',
            'body' => '<p>ტექსტი</p>',
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/pages', $payload)
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/pages', $payload)
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'conflict')
            ->assertDontSee('SQLSTATE', false);
    }

    public function test_brand_can_be_created_without_source_url(): void
    {
        $token = $this->adminToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/brands', [
                'slug' => 'nosource',
                'name' => 'No Source',
            ])
            ->assertCreated()
            ->assertJsonPath('data.source_url', '')
            ->assertJsonPath('data.id', 1);
    }

    public function test_equipment_list_requires_a_modification(): void
    {
        $token = $this->adminToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/equipment')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['modification_id']);
    }

    public function test_generation_list_filters_by_model_id(): void
    {
        Schema::connection('catalog')->create('generations', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->unsignedBigInteger('drom_generation_id')->nullable();
            $table->string('slug')->nullable();
            $table->string('name')->nullable();
            $table->string('chassis_code')->nullable();
            $table->string('body_type')->nullable();
            $table->integer('year_start')->nullable();
            $table->integer('year_end')->nullable();
            $table->string('period_text')->nullable();
            $table->string('market')->nullable();
            $table->string('steering_wheel')->nullable();
            $table->text('photo_urls')->nullable();
            $table->string('source_url')->nullable();
        });
        DB::connection('catalog')->table('generations')->insert([
            ['model_id' => 7, 'slug' => 'camry-a', 'name' => 'A', 'source_url' => ''],
            ['model_id' => 8, 'slug' => 'camry-b', 'name' => 'B', 'source_url' => ''],
        ]);
        $token = $this->adminToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/admin/generations?model_id=7')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.model_id', 7)
            ->assertJsonPath('data.0.id', 1);
    }

    public function test_secret_settings_are_rejected(): void
    {
        $token = $this->adminToken();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/admin/settings', [
                'key' => 'mail.password',
                'value' => ['password' => 'nope'],
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'forbidden');
    }

    private function adminToken(): string
    {
        $user = User::factory()->create([
            'name' => 'drom.amin',
            'email' => 'drom.amin@example.com',
        ]);
        $team = $user->createOwnedTeam(['name' => 'drom.amin'], true);
        app(PermissionRegistrar::class)->setPermissionsTeamId($team->getKey());
        $role = Role::findOrCreateForTeam('admin', 'web', (int) $team->getKey());
        $user->assignRole($role);

        $this->assertTrue(DB::table('roles')->where('name', 'admin')->exists());

        return $user->createToken('drom.amin', ['*'])->plainTextToken;
    }

    private function catalogSchema(): void
    {
        Schema::connection('catalog')->create('brands', function ($table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('synonyms')->nullable();
            $table->boolean('is_lcv_or_commercial')->nullable();
            $table->string('logo_url_light')->nullable();
            $table->string('logo_url_dark')->nullable();
            $table->string('source_url');
            $table->integer('drom_firm_id')->nullable();
        });
        Schema::connection('catalog')->create('modifications', function ($table): void {
            $table->id();
            $table->unsignedBigInteger('model_id');
            $table->unsignedBigInteger('drom_complectation_id');
            $table->string('name');
            $table->string('source_url');
        });
        Schema::connection('catalog')->create('modification_specs', function ($table): void {
            $table->unsignedBigInteger('modification_id')->primary();
            $table->text('specs')->nullable();
        });
    }
}
