<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Access\ProductAccess;
use App\Billing\PaymentEvents;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ProductAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();
        Schema::create('platform_users_stub', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->unsignedBigInteger('legacy_platform_user_id')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
        Schema::create('platform_projects', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
        });
        Schema::create('api_products', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->string('status');
        });
        Schema::create('api_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_user_id');
            $table->unsignedBigInteger('api_product_id');
            $table->string('status');
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });
        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->string('type');
            $table->json('summary')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        DB::table('api_products')->insert([
            ['id' => 1, 'slug' => 'cars', 'name' => 'მანქანების API', 'status' => 'published'],
            ['id' => 2, 'slug' => 'cities', 'name' => 'ქალაქების API', 'status' => 'draft'],
        ]);
    }

    public function test_cars_access_does_not_grant_a_draft_product(): void
    {
        DB::table('api_entitlements')->insert([
            'legacy_user_id' => 4,
            'api_product_id' => 1,
            'status' => 'active',
            'ends_at' => null,
            'cancelled_at' => null,
        ]);

        $this->assertFalse(ProductAccess::allowsUser(4, 'cities'));
        $this->assertTrue(ProductAccess::allowsUser(4, 'cars'));
    }

    public function test_cancelled_and_expired_grants_block_access(): void
    {
        DB::table('api_entitlements')->insert([
            ['legacy_user_id' => 1, 'api_product_id' => 1, 'status' => 'cancelled', 'cancelled_at' => now(), 'ends_at' => now()],
            ['legacy_user_id' => 2, 'api_product_id' => 1, 'status' => 'active', 'cancelled_at' => null, 'ends_at' => now()->subMinute()],
        ]);

        $this->assertFalse(ProductAccess::allowsUser(1, 'cars'));
        $this->assertFalse(ProductAccess::allowsUser(2, 'cars'));
    }

    public function test_token_abilities_follow_purchased_products_only(): void
    {
        DB::table('api_entitlements')->insert([
            'legacy_user_id' => 6,
            'api_product_id' => 1,
            'status' => 'active',
            'ends_at' => null,
            'cancelled_at' => null,
        ]);
        $user = new User();
        $user->forceFill([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => Hash::make('correct-horse-battery'),
            'legacy_platform_user_id' => 6,
        ]);
        $user->save();

        $abilities = ProductAccess::tokenAbilitiesFor($user);

        $this->assertSame(['cars:read' => 'მანქანების API'], $abilities);
        $this->assertArrayNotHasKey('cities:read', $abilities);
        $this->assertArrayNotHasKey('*', $abilities);
    }

    public function test_payment_event_is_recorded_once(): void
    {
        $this->assertTrue(PaymentEvents::record('stripe', 'evt_1', 'invoice.paid'));
        $this->assertFalse(PaymentEvents::record('stripe', 'evt_1', 'invoice.paid'));
        $this->assertSame(1, DB::table('payment_events')->count());
    }

    public function test_existing_argon2id_hash_verifies_without_rehash(): void
    {
        $hash = password_hash('სწორი-პაროლი-12345', PASSWORD_ARGON2ID);
        $user = new User();
        $user->forceFill([
            'name' => 'User',
            'email' => 'user@example.test',
            'password' => $hash,
        ]);
        $user->save();

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertTrue(Hash::check('სწორი-პაროლი-12345', (string) $user->fresh()->password));
        $this->assertFalse(Hash::needsRehash((string) $user->fresh()->password));
    }
}
