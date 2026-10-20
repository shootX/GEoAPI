<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'legacy_platform_user_id')) {
                $table->unsignedBigInteger('legacy_platform_user_id')->nullable()->unique()->after('id');
            }
        });

        Schema::create('legacy_id_maps', function (Blueprint $table): void {
            $table->id();
            $table->string('entity');
            $table->unsignedBigInteger('old_id');
            $table->unsignedBigInteger('new_id');
            $table->timestamps();
            $table->unique(['entity', 'old_id']);
        });

        Schema::create('api_products', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('summary');
            $table->string('documentation_path')->nullable();
            $table->string('status');
            $table->timestamps();
        });

        Schema::create('api_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_product_id')->constrained('api_products');
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_addon')->default(false);
            $table->unsignedInteger('per_minute');
            $table->unsignedInteger('monthly_quota');
            $table->unsignedInteger('price_minor')->nullable();
            $table->string('currency', 3)->default('GEL');
            $table->string('interval')->default('month');
            $table->string('status');
            $table->json('features');
            $table->timestamps();
        });

        Schema::create('api_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->unsignedBigInteger('legacy_user_id');
            $table->foreignId('api_product_id')->constrained('api_products');
            $table->foreignId('api_plan_id')->constrained('api_plans');
            $table->string('status');
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->string('source');
            $table->timestamps();
            $table->unique(['legacy_user_id', 'api_product_id']);
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

        $now = now();
        $products = [
            ['slug' => 'cars', 'name' => 'მანქანების API', 'summary' => 'მარკები, მოდელები, თაობები და მოდიფიკაციები.', 'documentation_path' => '/guide', 'status' => 'published'],
            ['slug' => 'cities', 'name' => 'ქალაქების API', 'summary' => 'მონახაზი. მონაცემები ჯერ არ არის და სერვისი არ მოქმედებს.', 'documentation_path' => null, 'status' => 'draft'],
            ['slug' => 'parking', 'name' => 'პარკინგების API', 'summary' => 'მონახაზი. მონაცემები ჯერ არ არის და სერვისი არ მოქმედებს.', 'documentation_path' => null, 'status' => 'draft'],
        ];
        foreach ($products as $product) {
            DB::table('api_products')->insert($product + ['created_at' => $now, 'updated_at' => $now]);
        }

        $carsId = (int) DB::table('api_products')->where('slug', 'cars')->value('id');
        DB::table('api_plans')->insert([
            'api_product_id' => $carsId,
            'code' => 'cars-included',
            'name' => 'მანქანების კატალოგი',
            'is_addon' => false,
            'per_minute' => 60,
            'monthly_quota' => 10000,
            'price_minor' => null,
            'currency' => 'GEL',
            'interval' => 'month',
            'status' => 'published',
            'features' => json_encode(['cars:read' => true, 'per_minute' => 60, 'monthly_quota' => 10000], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('api_entitlements');
        Schema::dropIfExists('api_plans');
        Schema::dropIfExists('api_products');
        Schema::dropIfExists('legacy_id_maps');
        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'legacy_platform_user_id')) {
                $table->dropColumn('legacy_platform_user_id');
            }
        });
    }
};
