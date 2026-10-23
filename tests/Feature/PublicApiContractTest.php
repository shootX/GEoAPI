<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Catalog\CatalogApi;
use App\Catalog\PublicSpec;
use Geoapi\Platform\MemoryUsageStore;
use Tests\TestCase;

final class PublicApiContractTest extends TestCase
{
    use SeedsPublicCatalog;

    public function test_openapi_routes_match_the_catalog_and_responses(): void
    {
        $document = PublicSpec::document();
        $specPaths = array_keys($document['paths']);
        $routes = CatalogApi::templates();
        sort($specPaths);
        sort($routes);

        $this->assertSame($routes, $specPaths);
        $this->assertStringContainsString('geoapi_sk_', (string) $document['info']['description']);
        $this->assertNotContains('/api/v1/vin', $specPaths);
        $this->getJson('/api/v1/vin')->assertNotFound();

        $this->assertKeys('/api/v1/brands?limit=1', 'Brand');
        $this->assertKeys('/api/v1/brands/toyota/models?limit=1', 'Model');
        $this->assertKeys('/api/v1/models/10/generations?limit=1', 'Generation');
        $this->assertKeys('/api/v1/models/10/years?limit=1', 'Year');
        $this->assertKeys('/api/v1/generations/100/modifications?limit=1', 'GenerationModification');
        $this->assertKeys('/api/v1/modifications?limit=1', 'ModificationSearch');
        $this->assertKeys('/api/v1/modifications/1000', 'Modification', 'data');
        $this->assertKeys('/api/v1/engines?limit=1', 'Engine');
        $this->assertKeys('/api/v1/bodies?limit=1', 'Body');
        $this->assertKeys('/api/v1/fuel?limit=1', 'Fuel');
        $this->assertKeys('/api/v1/colors?limit=1', 'Color');
        $this->assertKeys('/api/v1/modifications/1000/specs', 'ModificationSpecs', 'data');
        $this->assertKeys('/api/v1/modifications/1000/equipment', 'ModificationEquipment', 'data');
        $this->assertKeys('/api/v1/modifications/1000/equipment', 'EquipmentItem', 'data.standard.0');

        $coverage = $this->getJson('/api/v1/coverage')->assertOk();
        $this->assertEqualsCanonicalizing(
            array_keys($document['components']['schemas']['Coverage']['properties']),
            array_keys($coverage->json()),
        );

        $names = array_column($this->getJson('/api/v1/postman.json')->assertOk()->json('item'), 'name');
        sort($names);
        $this->assertSame($specPaths, $names);
    }

    public function test_every_documented_endpoint_accepts_a_valid_request(): void
    {
        $this->getJson('/api/v1/brands?limit=1')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'audi')
            ->assertJsonPath('data.0.logo_url_light', null)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('collection.count', 1);
        $this->assertIsString($this->getJson('/api/v1/brands?limit=1')->json('data.0.id'));

        $this->getJson('/api/v1/brands/toyota/models?body=sedan')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'camry')
            ->assertJsonPath('data.0.has_generations', true);

        $this->getJson('/api/v1/models/11/generations')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);

        $camryYears = array_column($this->getJson('/api/v1/models/10/years')->assertOk()->json('data'), 'year');
        $this->assertSame(range(2017, 2024), $camryYears);
        $priusYears = array_column($this->getJson('/api/v1/models/11/years')->assertOk()->json('data'), 'year');
        $this->assertSame([2015, 2016], $priusYears);
        $current = (int) gmdate('Y');
        $corollaYears = array_column($this->getJson('/api/v1/models/13/years')->assertOk()->json('data'), 'year');
        $this->assertSame(array_merge([2020, 2021], range(2025, $current)), $corollaYears);
        $this->assertNotContains(2099, $corollaYears);
        $this->assertNotContains($current + 1, $corollaYears);

        $this->getJson('/api/v1/generations/100/modifications?transmission=at&drive=fwd')
            ->assertOk()
            ->assertJsonPath('data.0.id', '1000')
            ->assertJsonPath('data.0.generation_id', '100');

        $this->getJson('/api/v1/modifications?q=2.5&brand_id=3&model_id=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', '1000')
            ->assertJsonPath('data.0.fuel', 'petrol');

        $this->getJson('/api/v1/modifications/1000')
            ->assertOk()
            ->assertJsonPath('data.id', '1000')
            ->assertJsonMissingPath('data.source_url');

        $specs = $this->getJson('/api/v1/modifications/1000/specs')->assertOk();
        $specs->assertJsonPath('data.engine.volume_cc', 2494)
            ->assertJsonPath('data.engine.power_hp', 200)
            ->assertJsonPath('data.consumption.unit', 'L/100km')
            ->assertJsonPath('data.dimensions', null)
            ->assertJsonPath('data.performance', null);
        $this->assertStringNotContainsString('secret.example', (string) $specs->getContent());
        $this->assertStringNotContainsString('length_mm', (string) $specs->getContent());
        $this->assertEqualsCanonicalizing(['volume_cc', 'fuel', 'power_hp'], array_keys($specs->json('data.engine')));
        $detail = $this->getJson('/api/v1/modifications/1000')->assertOk();
        $this->assertEqualsCanonicalizing(['id', 'slug', 'name'], array_keys($detail->json('data.brand')));
        $this->assertEqualsCanonicalizing(['id', 'name'], array_keys($detail->json('data.generation')));

        $bare = $this->getJson('/api/v1/modifications/1001/specs')->assertOk();
        $bare->assertJsonPath('data.engine.volume_cc', 1800)
            ->assertJsonPath('data.engine.fuel', null)
            ->assertJsonPath('data.engine.power_hp', null)
            ->assertJsonPath('data.consumption.city_l', null);

        $this->getJson('/api/v1/engines?model_id=10')
            ->assertOk()
            ->assertJsonFragment(['id' => '1001', 'power_hp' => null, 'volume_cc' => 1800]);

        $equipment = $this->getJson('/api/v1/modifications/1000/equipment')->assertOk();
        $equipment->assertJsonCount(1, 'data.standard')
            ->assertJsonPath('data.standard.0.id', '1')
            ->assertJsonPath('data.standard.0.name', 'ABS')
            ->assertJsonPath('data.optional.0.name', 'Sunroof')
            ->assertJsonPath('data.not_available.0.name', 'Navigation')
            ->assertJsonPath('data.unknown.0.name', 'Mystery');

        $this->getJson('/api/v1/colors?q=white')->assertOk()->assertJsonPath('data.0.verified', true);
        // Backward compatible: unverified colors stay listed, flagged verified=false.
        $this->getJson('/api/v1/colors?q=red')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.verified', false);
        $this->assertStringNotContainsString('hidden prompt', (string) $this->getJson('/api/v1/colors')->getContent());
    }

    public function test_every_documented_endpoint_rejects_missing_auth_bad_params_and_quota(): void
    {
        foreach ($this->samples() as $url) {
            $this->withoutTestProject();
            $this->getJson($url)
                ->assertUnauthorized()
                ->assertJsonPath('error.code', 'unauthorized')
                ->assertHeader('WWW-Authenticate', 'Bearer');
            $_ENV['GEOAPI_CATALOG_TEST_PROJECT'] = '7';
            $_SERVER['GEOAPI_CATALOG_TEST_PROJECT'] = '7';
        }

        $this->getJson('/api/v1/brands?limit=1001')->assertStatus(422)->assertJsonPath('error.code', 'invalid_parameter');
        $this->getJson('/api/v1/brands?sort='.urlencode('name;drop table brands'))->assertStatus(422);
        $this->getJson('/api/v1/brands/toyota/models?year_from=2020')->assertStatus(422);
        $this->getJson('/api/v1/models/10/generations?year_from=1800')->assertStatus(422);
        $this->getJson('/api/v1/models/10/years?q=camry')->assertStatus(422);
        $this->getJson('/api/v1/generations/abc/modifications')->assertStatus(422);
        $this->getJson('/api/v1/modifications?brand_id=0')->assertStatus(422);
        $this->getJson('/api/v1/modifications/0')->assertStatus(422);
        $this->getJson('/api/v1/modifications/0/specs')->assertStatus(422);
        $this->getJson('/api/v1/modifications/0/equipment')->assertStatus(422);
        $this->getJson('/api/v1/engines?volume_from=0')->assertStatus(422);
        $this->getJson('/api/v1/bodies?modified_after=2026-01-01T00:00:00Z')->assertStatus(422);
        $this->getJson('/api/v1/fuel?year_from=nope')->assertStatus(422);
        $this->getJson('/api/v1/colors?brand_id=0')->assertStatus(422);
        $this->getJson('/api/v1/coverage?year_from=2020')->assertStatus(422);

        foreach ($this->samples() as $url) {
            MemoryUsageStore::reset();
            MemoryUsageStore::shared()->perMinute = 1;
            $_ENV['GEOAPI_USAGE_MEMORY'] = '1';
            $_SERVER['GEOAPI_USAGE_MEMORY'] = '1';
            $this->getJson($url)->assertOk();
            $this->getJson($url)->assertStatus(429)->assertJsonPath('error.code', 'RATE_LIMIT_EXCEEDED');
        }
    }

    public function test_missing_records_and_closed_catalog_return_404(): void
    {
        $this->getJson('/api/v1/brands/missing/models')->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->getJson('/api/v1/models/999/generations')->assertNotFound();
        $this->getJson('/api/v1/models/999/years')->assertNotFound();
        $this->getJson('/api/v1/models/0/years')->assertStatus(422);
        $this->getJson('/api/v1/generations/101/modifications')->assertNotFound();
        $this->getJson('/api/v1/generations/999/modifications')->assertNotFound();
        $this->getJson('/api/v1/modifications/999')->assertNotFound();
        $this->getJson('/api/v1/modifications/999/specs')->assertNotFound();
        $this->getJson('/api/v1/modifications/999/equipment')->assertNotFound();

        $_ENV['GEOAPI_CATALOG_ENABLED'] = '0';
        $_SERVER['GEOAPI_CATALOG_ENABLED'] = '0';
        foreach (['/api/v1/brands', '/api/v1/bodies', '/api/v1/colors', '/api/v1/coverage'] as $url) {
            $this->getJson($url)->assertNotFound()->assertJsonPath('error.code', 'not_found');
        }

        $_ENV['GEOAPI_CATALOG_ENABLED'] = '1';
        $_SERVER['GEOAPI_CATALOG_ENABLED'] = '1';
        $_ENV['GEOAPI_MODIFICATIONS_ENABLED'] = '0';
        $_SERVER['GEOAPI_MODIFICATIONS_ENABLED'] = '0';
        foreach (['/api/v1/modifications', '/api/v1/engines', '/api/v1/fuel', '/api/v1/generations/100/modifications'] as $url) {
            $this->getJson($url)->assertNotFound();
        }
    }

    public function test_query_text_cannot_change_the_catalog(): void
    {
        $this->getJson('/api/v1/brands?q='.urlencode("'; DROP TABLE brands;--"))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/v1/brands')
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('data.2.synonyms.0', 'тойота');

        $this->getJson('/api/v1/brands?q='.urlencode('тойота'))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    /** @return list<string> */
    private function samples(): array
    {
        return [
            '/api/v1/brands?limit=1',
            '/api/v1/brands/toyota/models?limit=1',
            '/api/v1/models/10/generations?limit=1',
            '/api/v1/models/10/years?limit=1',
            '/api/v1/generations/100/modifications?limit=1',
            '/api/v1/modifications?limit=1',
            '/api/v1/modifications/1000',
            '/api/v1/modifications/1000/specs',
            '/api/v1/modifications/1000/equipment',
            '/api/v1/engines?limit=1',
            '/api/v1/bodies?limit=1',
            '/api/v1/fuel?limit=1',
            '/api/v1/colors?limit=1',
            '/api/v1/coverage',
        ];
    }

    private function withoutTestProject(): void
    {
        unset($_ENV['GEOAPI_CATALOG_TEST_PROJECT'], $_SERVER['GEOAPI_CATALOG_TEST_PROJECT']);
    }

    private function assertKeys(string $url, string $schema, string $pointer = 'data.0'): void
    {
        $response = $this->getJson($url)->assertOk();
        $properties = PublicSpec::document()['components']['schemas'][$schema]['properties'];
        $payload = $response->json($pointer);
        $this->assertIsArray($payload, $url.' '.$pointer);
        $this->assertEqualsCanonicalizing(array_keys($properties), array_keys($payload), $url.' '.$pointer);
    }
}
