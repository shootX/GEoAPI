<?php

declare(strict_types=1);

namespace Tests\Feature;

use Geoapi\Catalog\Connection;
use Geoapi\Platform\MemoryUsageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;

trait SeedsPublicCatalog
{
    use RefreshDatabase;

    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            DB::statement("ATTACH DATABASE ':memory:' AS platform");
        } catch (\Throwable) {
        }
        DB::statement('CREATE TABLE IF NOT EXISTS platform.projects (id integer primary key, user_id integer not null)');
        DB::table('platform.projects')->where('id', 7)->delete();
        DB::table('platform.projects')->insert(['id' => 7, 'user_id' => 3]);
        $productId = (int) DB::table('api_products')->where('slug', 'cars')->value('id');
        $planId = (int) DB::table('api_plans')->where('code', 'cars-included')->value('id');
        DB::table('api_entitlements')->insert([
            'legacy_user_id' => 3,
            'api_product_id' => $productId,
            'api_plan_id' => $planId,
            'status' => 'active',
            'starts_at' => now(),
            'source' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        MemoryUsageStore::reset();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->schema();
        Connection::use($this->pdo);
        foreach ([
            'GEOAPI_CATALOG_ENABLED' => '1',
            'GEOAPI_MODIFICATIONS_ENABLED' => '1',
            'GEOAPI_MODIFICATION_DETAILS_ENABLED' => '1',
            'GEOAPI_CATALOG_TEST_PROJECT' => '7',
            'GEOAPI_USAGE_MEMORY' => '1',
        ] as $name => $value) {
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    protected function tearDown(): void
    {
        Connection::reset();
        MemoryUsageStore::reset();
        foreach ([
            'GEOAPI_CATALOG_ENABLED',
            'GEOAPI_MODIFICATIONS_ENABLED',
            'GEOAPI_MODIFICATION_DETAILS_ENABLED',
            'GEOAPI_CATALOG_TEST_PROJECT',
            'GEOAPI_USAGE_MEMORY',
        ] as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
        }
        parent::tearDown();
    }

    private function schema(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE brands (
                id integer primary key,
                slug text not null,
                name text not null,
                synonyms text,
                is_lcv_or_commercial integer,
                logo_url_light text,
                logo_url_dark text,
                updated_at text
            );
            CREATE TABLE models (
                id integer primary key,
                brand_id integer not null,
                slug text not null,
                name text not null,
                body_type text,
                updated_at text
            );
            CREATE TABLE model_markets (
                model_id integer not null,
                market_slug text not null
            );
            CREATE TABLE generations (
                id integer primary key,
                model_id integer not null,
                slug text,
                name text,
                chassis_code text,
                body_type text,
                year_start integer,
                year_end integer,
                steering_wheel text,
                updated_at text
            );
            CREATE TABLE modifications (
                id integer primary key,
                generation_id integer,
                model_id integer not null,
                name text not null,
                year_start integer,
                year_end integer,
                body_type text,
                drive_type text,
                transmission text,
                engine_volume_cc integer,
                updated_at text
            );
            CREATE TABLE modification_specs (
                modification_id integer primary key,
                specs text
            );
            CREATE TABLE model_photos (
                model_id integer primary key,
                color text,
                verified integer,
                prompt text,
                updated_at text
            );
            CREATE TABLE photos (id integer primary key);
            CREATE TABLE modification_equipment (
                id integer primary key,
                modification_id integer not null,
                category text,
                name text,
                status text,
                value text
            );
        SQL);
        $this->pdo->exec(<<<'SQL'
            INSERT INTO brands (id, slug, name, synonyms, is_lcv_or_commercial, updated_at) VALUES
                (1, 'audi', 'Audi', '[]', 0, '2026-01-01T00:00:00Z'),
                (2, 'bmw', 'BMW', '[]', 0, '2026-06-01T00:00:00Z'),
                (3, 'toyota', 'Toyota', '["тойота"]', 0, '2026-01-15T00:00:00Z');
            INSERT INTO models (id, brand_id, slug, name, body_type, updated_at) VALUES
                (10, 3, 'camry', 'Camry', 'sedan', '2026-02-01T00:00:00Z'),
                (11, 3, 'prius', 'Prius', 'hatchback', '2026-02-01T00:00:00Z'),
                (13, 3, 'corolla', 'Corolla', NULL, '2026-02-01T00:00:00Z');
            INSERT INTO model_markets (model_id, market_slug) VALUES (10, 'jp');
            INSERT INTO generations (id, model_id, slug, name, chassis_code, body_type, year_start, year_end, updated_at) VALUES
                (100, 10, 'xv70', 'XV70', 'XV70', 'sedan', 2017, 2024, '2026-02-01T00:00:00Z'),
                (101, 10, 'xv70-b', 'other', 'XV70', 'sedan', 2018, 2024, '2026-02-01T00:00:00Z'),
                (300, 13, 'e210', 'E210', 'E210', NULL, 2025, NULL, '2026-02-01T00:00:00Z'),
                (301, 13, 'future', 'Future', 'FUTURE', NULL, 2099, 2100, '2026-02-01T00:00:00Z');
            INSERT INTO modifications (id, generation_id, model_id, name, year_start, year_end, body_type, drive_type, transmission, engine_volume_cc, updated_at) VALUES
                (1000, 100, 10, '2.5', 2018, 2023, 'sedan', 'FWD', 'AT', 2494, '2026-02-01T00:00:00Z'),
                (1001, 100, 10, 'bare', 2019, 2020, 'sedan', 'RWD', 'MT', 1800, '2026-02-01T00:00:00Z'),
                (1002, NULL, 11, '1.8', 2015, 2016, 'hatchback', 'FWD', 'AT', 1798, '2026-02-01T00:00:00Z'),
                (1003, 300, 13, '1.6', 2020, 2021, NULL, 'FWD', 'AT', 1598, '2026-02-01T00:00:00Z');
            INSERT INTO modification_specs (modification_id, specs) VALUES
                (1000, '{"fuel_type":"petrol","fuel_city_l":9.1,"fuel_highway_l":6.2,"fuel_combined_l":7.4,"power_hp":200,"source_url":"https://secret.example/internal","length_mm":4630}');
            INSERT INTO modification_equipment (id, modification_id, category, name, status, value) VALUES
                (1, 1000, 'Safety', 'ABS', 'included', NULL),
                (2, 1000, 'Safety', 'ABS', 'included', 'duplicate'),
                (3, 1000, 'Comfort', 'Sunroof', 'option', 'glass'),
                (4, 1000, 'Audio', 'Navigation', 'not_available', NULL),
                (5, 1000, 'Misc', 'Mystery', 'weird', 'x');
            INSERT INTO model_photos (model_id, color, verified, prompt, updated_at) VALUES
                (10, 'white', 1, 'secret prompt', '2026-02-01T00:00:00Z'),
                (11, 'red', 0, 'hidden prompt', '2026-02-01T00:00:00Z');
        SQL);
    }
}
