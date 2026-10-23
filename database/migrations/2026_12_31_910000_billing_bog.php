<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Buyer fields, billing tables, and plan rows. Platform columns are additive.
 * Tests skip the platform statements. Catalog tables are not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->buyers();
        $this->billing();
        $this->plans();
        if (app()->environment('testing')) {
            return;
        }
        $this->platform();
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_invoices');
        Schema::dropIfExists('billing_audit');
        Schema::dropIfExists('billing_orders');
        Schema::dropIfExists('billing_rates');
        Schema::table('users', function (Blueprint $table): void {
            foreach (['buyer_kind', 'company_name', 'company_code', 'company_address', 'vat_payer'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function buyers(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'buyer_kind')) {
                $table->string('buyer_kind', 16)->default('individual');
            }
            if (! Schema::hasColumn('users', 'company_name')) {
                $table->string('company_name')->nullable();
            }
            if (! Schema::hasColumn('users', 'company_code')) {
                $table->string('company_code', 32)->nullable();
            }
            if (! Schema::hasColumn('users', 'company_address')) {
                $table->string('company_address', 500)->nullable();
            }
            if (! Schema::hasColumn('users', 'vat_payer')) {
                $table->boolean('vat_payer')->default(false);
            }
        });
    }

    private function billing(): void
    {
        if (! Schema::hasTable('billing_rates')) {
            Schema::create('billing_rates', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 3);
                $table->decimal('rate', 16, 8);
                $table->unsignedInteger('quantity');
                $table->date('rated_on');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('billing_orders')) {
            Schema::create('billing_orders', function (Blueprint $table): void {
                $table->id();
                $table->uuid('public_id')->unique();
                $table->foreignId('user_id')->constrained('users');
                $table->unsignedBigInteger('legacy_user_id');
                $table->unsignedBigInteger('project_id');
                $table->string('plan_code');
                $table->string('interval', 8);
                $table->unsignedInteger('usd_cents');
                $table->decimal('gel_amount', 12, 2);
                $table->decimal('rate', 16, 8);
                $table->unsignedInteger('rate_quantity');
                $table->date('rate_date');
                $table->json('buyer');
                $table->json('previous');
                $table->string('status', 16);
                $table->string('bog_order_id')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('billing_invoices')) {
            Schema::create('billing_invoices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('billing_order_id')->constrained('billing_orders');
                $table->string('number')->unique();
                $table->binary('pdf');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('billing_audit')) {
            Schema::create('billing_audit', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('action');
                $table->string('subject_type');
                $table->string('subject_id');
                $table->json('detail');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    private function plans(): void
    {
        if (! Schema::hasTable('api_products') || ! Schema::hasTable('api_plans')) {
            return;
        }
        $carsId = DB::table('api_products')->where('slug', 'cars')->value('id');
        if (! is_numeric($carsId)) {
            return;
        }
        $now = now();
        $rows = [
            ['code' => 'free', 'name' => 'Free', 'monthly_quota' => 100, 'price_minor' => null, 'interval' => 'month'],
            ['code' => 'basic', 'name' => 'Basic', 'monthly_quota' => 2000, 'price_minor' => 5900, 'interval' => 'month'],
            ['code' => 'basic-year', 'name' => 'Basic year', 'monthly_quota' => 2000, 'price_minor' => 62304, 'interval' => 'year'],
            ['code' => 'premium', 'name' => 'Premium', 'monthly_quota' => 0, 'price_minor' => 9900, 'interval' => 'month'],
            ['code' => 'premium-year', 'name' => 'Premium year', 'monthly_quota' => 0, 'price_minor' => 104544, 'interval' => 'year'],
        ];
        foreach ($rows as $row) {
            $exists = DB::table('api_plans')->where('code', $row['code'])->exists();
            if ($exists) {
                continue;
            }
            DB::table('api_plans')->insert([
                'api_product_id' => (int) $carsId,
                'code' => $row['code'],
                'name' => $row['name'],
                'is_addon' => false,
                'per_minute' => 60,
                'monthly_quota' => $row['monthly_quota'],
                'price_minor' => $row['price_minor'],
                'currency' => 'USD',
                'interval' => $row['interval'],
                'status' => 'published',
                'features' => json_encode([
                    'cars:read' => true,
                    'unlimited_month' => str_starts_with($row['code'], 'premium'),
                ], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function platform(): void
    {
        $statements = [
            'ALTER TABLE platform.users ADD COLUMN IF NOT EXISTS grandfathered boolean NOT NULL DEFAULT true',
            'ALTER TABLE platform.users ALTER COLUMN grandfathered SET DEFAULT false',
            'ALTER TABLE platform.project_limits ADD COLUMN IF NOT EXISTS plan_code text',
            'ALTER TABLE platform.project_limits ADD COLUMN IF NOT EXISTS plan_ends_at timestamptz',
            'ALTER TABLE platform.project_limits ADD COLUMN IF NOT EXISTS unlimited_month boolean NOT NULL DEFAULT false',
        ];
        // Production keeps the platform schema in the same database: use that connection, no extra credentials.
        if (DB::connection()->getDriverName() === 'pgsql'
            && DB::selectOne("SELECT 1 AS ok FROM information_schema.schemata WHERE schema_name = 'platform'") !== null) {
            // Skip what already exists: ALTER needs table ownership even when it would be a no-op,
            // and production applies these columns ahead of the deploy as the table owner.
            $have = static fn (string $table, string $column): ?object => DB::selectOne(
                "SELECT column_default FROM information_schema.columns WHERE table_schema = 'platform' AND table_name = ? AND column_name = ?",
                [$table, $column],
            );
            $grandfathered = $have('users', 'grandfathered');
            if ($grandfathered === null) {
                DB::statement($statements[0]);
                DB::statement($statements[1]);
            } elseif (! str_starts_with((string) $grandfathered->column_default, 'false')) {
                DB::statement($statements[1]);
            }
            foreach (['plan_code' => 2, 'plan_ends_at' => 3, 'unlimited_month' => 4] as $column => $index) {
                if ($have('project_limits', $column) === null) {
                    DB::statement($statements[$index]);
                }
            }

            return;
        }
        $pdo = $this->platformPdo();
        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
    }

    private function platformPdo(): PDO
    {
        $host = $this->env('GEOAPI_PLATFORM_HOST');
        $database = $this->env('GEOAPI_PLATFORM_DATABASE');
        $user = $this->env('GEOAPI_PLATFORM_USER');
        $password = $this->env('GEOAPI_PLATFORM_PASSWORD');
        $port = $this->env('GEOAPI_PLATFORM_PORT');
        if ($port === '') {
            $port = '5432';
        }
        if ($host === '' || $database === '' || $user === '') {
            throw new RuntimeException('Platform migration needs GEOAPI_PLATFORM_HOST, GEOAPI_PLATFORM_DATABASE, and GEOAPI_PLATFORM_USER.');
        }

        return new PDO(
            sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, (int) $port, $database),
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }

    private function env(string $name): string
    {
        $value = env($name);

        return is_string($value) ? trim($value) : '';
    }
};
