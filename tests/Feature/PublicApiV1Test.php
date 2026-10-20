<?php

declare(strict_types=1);

namespace Tests\Feature;

use Carapi\Catalog\Connection;
use Carapi\Platform\MemoryUsageStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PDO;
use Tests\TestCase;

final class PublicApiV1Test extends TestCase
{
    use RefreshDatabase;

    private PDO $pdo;

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
            'CARAPI_CATALOG_ENABLED' => '1',
            'CARAPI_MODIFICATIONS_ENABLED' => '1',
            'CARAPI_MODIFICATION_DETAILS_ENABLED' => '1',
            'CARAPI_CATALOG_TEST_PROJECT' => '7',
            'CARAPI_USAGE_MEMORY' => '1',
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
            'CARAPI_CATALOG_ENABLED',
            'CARAPI_MODIFICATIONS_ENABLED',
            'CARAPI_MODIFICATION_DETAILS_ENABLED',
            'CARAPI_CATALOG_TEST_PROJECT',
            'CARAPI_USAGE_MEMORY',
        ] as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
        }
        parent::tearDown();
    }

    public function test_lists_include_collection_metadata_and_accept_limit_or_per_page(): void
    {
        $response = $this->getJson('/api/v1/brands?limit=2');

        $response->assertOk()
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('collection.count', 2)
            ->assertJsonPath('collection.total', 3)
            ->assertJsonPath('collection.pages', 2)
            ->assertJsonPath('collection.prev', null)
            ->assertJsonPath('data.0.slug', 'audi')
            ->assertJsonPath('data.0.modified', '2026-01-01T00:00:00Z');
        $this->assertStringContainsString('page=2', (string) $response->json('collection.next'));
        $this->assertStringContainsString('limit=2', (string) $response->json('collection.first'));
        $this->assertNotNull($response->json('collection.last'));
        $response->assertHeader('X-RateLimit-Limit', '60');
        $response->assertHeader('X-Quota-Limit', '10000');

        $this->getJson('/api/v1/brands?per_page=1&sort=name&direction=desc')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.slug', 'toyota');

        $this->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 100)
            ->assertJsonCount(3, 'data');
    }

    public function test_invalid_list_parameters_are_rejected(): void
    {
        $this->getJson('/api/v1/brands?limit=1001')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_parameter');

        $this->getJson('/api/v1/brands?sort=source_url')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_parameter');

        $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/brands?direction=sideways')
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'direction must be asc or desc.');

        $this->getJson('/api/v1/brands?year_from=2020')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_parameter');
    }

    public function test_filters_search_and_modified_after_follow_the_stored_columns(): void
    {
        $this->getJson('/api/v1/brands?q=toy')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Toyota');

        $this->getJson('/api/v1/brands?modified_after=2026-03-01T00:00:00Z')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', 'bmw');

        $this->getJson('/api/v1/brands/toyota/models?body=sedan')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'camry')
            ->assertJsonPath('data.0.generations_count', 1)
            ->assertJsonPath('data.0.markets.0', 'jp');

        $this->getJson('/api/v1/models/10/generations?year_from=2020&body=sedan')
            ->assertOk()
            ->assertJsonPath('data.0.id', '100')
            ->assertJsonPath('data.0.chassis_code', 'XV70');

        $this->getJson('/api/v1/generations/101/modifications')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        $this->getJson('/api/v1/generations/100/modifications?transmission=at&drive=fwd')
            ->assertOk()
            ->assertJsonPath('data.0.id', '1000')
            ->assertJsonPath('data.0.generation_id', '100')
            ->assertJsonPath('data.0.modified', '2026-02-01T00:00:00Z');

        $flat = $this->getJson('/api/v1/modifications?q=2.5&fuel=petrol&year_from=2020&brand_id=3&model_id=10');
        $flat->assertOk()
            ->assertJsonPath('data.0.id', '1000')
            ->assertJsonPath('data.0.fuel', 'petrol')
            ->assertJsonPath('data.0.generation_id', '100')
            ->assertJsonPath('data.0.body_type', 'sedan');

        $this->getJson('/api/v1/modifications/1000')
            ->assertOk()
            ->assertJsonPath('data.brand.slug', 'toyota')
            ->assertJsonPath('data.generation.id', '100')
            ->assertJsonPath('data.modified', '2026-02-01T00:00:00Z');
    }

    public function test_flat_spec_resources_only_return_stored_fields(): void
    {
        $this->getJson('/api/v1/engines?fuel=petrol&volume_from=2000&volume_to=3000')
            ->assertOk()
            ->assertJsonPath('data.0.id', '1000')
            ->assertJsonPath('data.0.volume_cc', 2494)
            ->assertJsonPath('data.0.power_hp', 200)
            ->assertJsonMissingPath('data.0.image');

        $this->getJson('/api/v1/bodies?q=sed')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'sedan')
            ->assertJsonPath('data.0.count', 4);

        $this->getJson('/api/v1/fuel?fuel=petrol')
            ->assertOk()
            ->assertJsonPath('data.0.city_l', 9.1)
            ->assertJsonPath('data.0.highway_l', 6.2)
            ->assertJsonPath('data.0.combined_l', 7.4);

        $colors = $this->getJson('/api/v1/colors?q=white&brand_id=3')->assertOk();
        $colors->assertJsonPath('data.0.name', 'white')
            ->assertJsonPath('data.0.verified', true)
            ->assertJsonMissingPath('data.0.image')
            ->assertJsonMissingPath('data.0.prompt');

        $this->getJson('/api/v1/coverage')
            ->assertOk()
            ->assertJsonPath('brands_count', 3)
            ->assertJsonPath('modifications_count', 1)
            ->assertJsonPath('generations_count', 1);
    }

    public function test_daily_and_minute_quotas_return_json_429_with_rate_headers(): void
    {
        MemoryUsageStore::shared()->perMinute = 1;
        $this->getJson('/api/v1/brands?limit=1')->assertOk()->assertHeader('X-RateLimit-Remaining', '0');
        $this->getJson('/api/v1/brands?limit=1')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED')
            ->assertHeader('Retry-After')
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeader('X-RateLimit-Reset');

        MemoryUsageStore::reset();
        MemoryUsageStore::shared()->perMinute = 100;
        MemoryUsageStore::shared()->perDay = 1;
        $_ENV['CARAPI_USAGE_MEMORY'] = '1';
        $_SERVER['CARAPI_USAGE_MEMORY'] = '1';
        $this->getJson('/api/v1/brands?limit=1')->assertOk();
        $this->getJson('/api/v1/brands?limit=1')
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'DAILY_QUOTA_EXCEEDED')
            ->assertHeader('X-RateLimit-Limit', '1')
            ->assertHeader('X-RateLimit-Remaining', '0');
    }

    public function test_public_docs_expose_the_spec_postman_collection_and_code_samples(): void
    {
        $this->getJson('/api/v1/openapi.json')
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.title', 'GeoAPI')
            ->assertJsonPath('paths./api/v1/engines.get.summary', 'Engine volume, fuel, and power stored on modifications')
            ->assertJsonPath('paths./api/v1/bodies.get.summary', 'Distinct body types stored on models, generations, and modifications')
            ->assertJsonPath('paths./api/v1/fuel.get.summary', 'Fuel consumption stored on modification specs')
            ->assertJsonPath('paths./api/v1/colors.get.summary', 'Colors stored on model photos')
            ->assertJsonPath('paths./api/v1/modifications.get.summary', 'Modifications across brands, models, and generations')
            ->assertJsonPath('components.schemas.List.properties.collection.type', 'object')
            ->assertJsonPath('components.parameters.limit.schema.maximum', 1000);

        $this->getJson('/api/v1/postman.json')
            ->assertOk()
            ->assertJsonPath('info.schema', 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json')
            ->assertJsonPath('info.name', 'GeoAPI');
        $this->assertNotEmpty($this->getJson('/api/v1/postman.json')->json('item'));

        $this->get('/api/v1/docs')
            ->assertOk()
            ->assertSee('redoc', false)
            ->assertSee('/api/v1/openapi.json', false)
            ->assertSee('Postman', false);

        $this->withSession(['locale' => 'ka'])->get('/guide')
            ->assertOk()
            ->assertSee('curl', false)
            ->assertSee('JavaScript', false)
            ->assertSee('Node', false)
            ->assertSee('Python', false)
            ->assertSee('PHP', false)
            ->assertSee('ბრენდები', false)
            ->assertSee('X-RateLimit-Limit', false);

        $this->withSession(['locale' => 'ka'])->get('/pricing')
            ->assertOk()
            ->assertSee('Free')
            ->assertSee('Pro — მალე')
            ->assertSee('2 000')
            ->assertSee('10 000')
            ->assertDontSee('Stripe');
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
        SQL);
        $this->pdo->exec(<<<'SQL'
            INSERT INTO brands (id, slug, name, synonyms, is_lcv_or_commercial, updated_at) VALUES
                (1, 'audi', 'Audi', '[]', 0, '2026-01-01T00:00:00Z'),
                (2, 'bmw', 'BMW', '[]', 0, '2026-06-01T00:00:00Z'),
                (3, 'toyota', 'Toyota', '["тойота"]', 0, '2026-01-15T00:00:00Z');
            INSERT INTO models (id, brand_id, slug, name, body_type, updated_at) VALUES
                (10, 3, 'camry', 'Camry', 'sedan', '2026-02-01T00:00:00Z');
            INSERT INTO model_markets (model_id, market_slug) VALUES (10, 'jp');
            INSERT INTO generations (id, model_id, slug, name, chassis_code, body_type, year_start, year_end, updated_at) VALUES
                (100, 10, 'xv70', 'XV70', 'XV70', 'sedan', 2017, 2024, '2026-02-01T00:00:00Z'),
                (101, 10, 'xv70-b', 'other', 'XV70', 'sedan', 2018, 2024, '2026-02-01T00:00:00Z');
            INSERT INTO modifications (id, generation_id, model_id, name, year_start, year_end, body_type, drive_type, transmission, engine_volume_cc, updated_at) VALUES
                (1000, 100, 10, '2.5', 2018, 2023, 'sedan', 'FWD', 'AT', 2494, '2026-02-01T00:00:00Z');
            INSERT INTO modification_specs (modification_id, specs) VALUES
                (1000, '{"fuel_type":"petrol","fuel_city_l":9.1,"fuel_highway_l":6.2,"fuel_combined_l":7.4,"power_hp":200}');
            INSERT INTO model_photos (model_id, color, verified, prompt, updated_at) VALUES
                (10, 'white', 1, 'secret prompt', '2026-02-01T00:00:00Z');
        SQL);
    }
}
