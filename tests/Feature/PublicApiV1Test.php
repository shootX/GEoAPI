<?php

declare(strict_types=1);

namespace Tests\Feature;

use Geoapi\Platform\MemoryUsageStore;
use Tests\TestCase;

final class PublicApiV1Test extends TestCase
{
    use SeedsPublicCatalog;

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
            ->assertJsonPath('data.0.count', 5);

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
            ->assertJsonPath('modifications_count', 4)
            ->assertJsonPath('generations_count', 3);
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
        $_ENV['GEOAPI_USAGE_MEMORY'] = '1';
        $_SERVER['GEOAPI_USAGE_MEMORY'] = '1';
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
            ->assertJsonPath('paths./api/v1/engines.get.summary', 'Engine volume, fuel, and power of each modification')
            ->assertJsonPath('paths./api/v1/bodies.get.summary', 'Distinct body types used by models, generations, and modifications')
            ->assertJsonPath('paths./api/v1/fuel.get.summary', 'Fuel consumption of each modification')
            ->assertJsonPath('paths./api/v1/colors.get.summary', 'Colors of model photos')
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
            ->assertSee('cURL', false)
            ->assertSee('Node', false)
            ->assertSee('Python', false)
            ->assertSee('PHP', false)
            ->assertSee('ბრენდები', false)
            ->assertSee('X-RateLimit-Limit', false);

        $this->withSession(['locale' => 'ka'])->get('/pricing')
            ->assertOk()
            ->assertSee('Free')
            ->assertSee('Basic')
            ->assertSee('Premium')
            ->assertSee('2 000')
            ->assertSee('10 000')
            ->assertSee('₾')
            ->assertDontSee('$59')
            ->assertDontSee('$99')
            ->assertDontSee('USD')
            ->assertSee('Bank of Georgia')
            ->assertSee('გადახდა')
            ->assertDontSee('Stripe');
    }
}
