<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Catalog\ListQuery;
use App\Catalog\PublicCatalog;
use Geoapi\Catalog\Http as CatalogHttp;
use Geoapi\Catalog\PickerSelection;
use Geoapi\Widget\Demo;
use PDO;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class PickerSelectionTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE brands (id integer primary key, slug text, name text);
            CREATE TABLE models (id integer primary key, brand_id integer, slug text, name text, body_type text, updated_at text);
            CREATE TABLE generations (
                id integer primary key, model_id integer, slug text, name text, chassis_code text,
                body_type text, year_start integer, year_end integer, steering_wheel text, market text, updated_at text
            );
            CREATE TABLE modifications (
                id integer primary key, generation_id integer, model_id integer, name text,
                year_start integer, year_end integer, body_type text, drive_type text,
                transmission text, engine_volume_cc integer, updated_at text
            );
            CREATE TABLE modification_specs (modification_id integer primary key, specs text);
            CREATE TABLE modification_equipment (
                id integer primary key, modification_id integer not null,
                category text, name text, status text, value text
            );
            INSERT INTO brands (id, slug, name) VALUES (3, 'toyota', 'Toyota');
            INSERT INTO models (id, brand_id, slug, name, body_type) VALUES
                (10, 3, 'camry', 'Camry', 'sedan'),
                (11, 3, 'prius', 'Prius', 'hatchback'),
                (13, 3, 'corolla', 'Corolla', NULL);
            INSERT INTO generations (id, model_id, slug, name, chassis_code, body_type, year_start, year_end) VALUES
                (100, 10, 'xv70', 'XV70', 'XV70', 'sedan', 2017, 2024),
                (101, 10, 'xv70-b', 'other', 'XV70', 'sedan', 2018, 2024),
                (300, 13, 'e210', 'E210', 'E210', NULL, 2025, NULL),
                (301, 13, 'future', 'Future', 'FUTURE', NULL, 2099, 2100);
            INSERT INTO modifications (id, generation_id, model_id, name, year_start, year_end, body_type, drive_type, transmission, engine_volume_cc) VALUES
                (1000, 100, 10, '2.5', 2018, 2023, 'sedan', 'FWD', 'AT', 2494),
                (1001, 100, 10, 'bare', 2019, 2020, 'sedan', 'RWD', 'MT', 1800),
                (1002, NULL, 11, '1.8', 2015, 2016, 'hatchback', 'FWD', 'AT', 1798),
                (1003, 300, 13, '1.6', 2020, 2021, NULL, 'FWD', 'AT', 1598);
            INSERT INTO modification_specs (modification_id, specs) VALUES
                (1000, '{"fuel_type":"petrol","power_hp":200,"fuel_city_l":9.1,"source_url":"https://secret.example/internal","length_mm":4630}');
            INSERT INTO modification_equipment (id, modification_id, category, name, status, value) VALUES
                (1, 1000, 'Safety', 'ABS', 'included', NULL),
                (2, 1000, 'Safety', 'ABS', 'included', 'duplicate'),
                (3, 1000, 'Comfort', 'Sunroof', 'option', 'glass'),
                (4, 1000, 'Audio', 'Navigation', 'not_available', NULL),
                (5, 1000, 'Misc', 'Mystery', 'weird', 'x');
        SQL);
    }

    public function test_widget_years_match_the_public_api_and_skip_open_ended_futures(): void
    {
        $query = ListQuery::parse([], ['year', 'id'], []);
        $catalog = new PublicCatalog($this->pdo);

        $api = array_column($catalog->years('13', $query, 2026)['rows'], 'year');
        $widget = array_map('intval', array_column(PickerSelection::years($this->pdo, '13', 2026)['rows'], 'id'));
        $this->assertSame([2026, 2025, 2021, 2020], $widget);
        $sorted = $widget;
        sort($sorted);
        $this->assertSame($sorted, $api);
        $this->assertNotContains(2099, $widget);
        $this->assertNotContains(2100, $widget);

        $camry = array_map('intval', array_column(PickerSelection::years($this->pdo, '10', 2026)['rows'], 'id'));
        $this->assertSame(range(2024, 2017), $camry);
        $this->assertSame(['sedan'], PickerSelection::years($this->pdo, '10', 2026)['rows'][0]['bodies']);
        $prius = PickerSelection::years($this->pdo, '11', 2026);
        $this->assertSame(['2016', '2015'], array_column($prius['rows'], 'id'));
        $this->assertSame(['hatchback'], $prius['rows'][0]['bodies']);
        $this->assertNull(PickerSelection::years($this->pdo, '999', 2026));
    }

    public function test_generation_and_modification_ids_match_across_the_picker(): void
    {
        $generations = PickerSelection::generations($this->pdo, '10', 2019);
        $this->assertNotNull($generations);
        $this->assertSame(['100'], array_column($generations['rows'], 'id'));
        $this->assertStringContainsString('XV70', (string) $generations['rows'][0]['label']);

        $mods = PickerSelection::modifications($this->pdo, '100', 2019);
        $this->assertSame(['1000', '1001'], array_column($mods['rows'], 'id'));
        $this->assertSame(200, $mods['rows'][0]['power_hp']);
        $this->assertSame('automatic', $mods['rows'][0]['transmission']);
        $this->assertNull($mods['rows'][1]['power_hp']);
        $this->assertStringNotContainsString('secret.example', json_encode($mods));
        $this->assertNull(PickerSelection::modifications($this->pdo, '101', 2019));
        $this->assertSame([], PickerSelection::generations($this->pdo, '11', 2015)['rows']);

        $corolla = PickerSelection::generations($this->pdo, '13', 2020);
        $this->assertSame(['300'], array_column($corolla['rows'], 'id'));
        $this->assertSame(['1003'], array_column(PickerSelection::modifications($this->pdo, '300', 2020)['rows'], 'id'));
        $this->assertSame([], PickerSelection::modifications($this->pdo, '300', 2024)['rows']);
        $open = array_column(PickerSelection::generations($this->pdo, '13', 2026)['rows'], 'id');
        $this->assertSame(['300'], $open);
    }

    public function test_equipment_groups_match_the_api_and_missing_tables_stay_empty(): void
    {
        $groups = PickerSelection::equipment($this->pdo, '1000');
        $this->assertSame(['1'], array_column($groups['standard'], 'id'));
        $this->assertSame('ABS', $groups['standard'][0]['name']);
        $this->assertSame('Sunroof', $groups['optional'][0]['name']);
        $this->assertSame('Navigation', $groups['not_available'][0]['name']);
        $this->assertSame('Mystery', $groups['unknown'][0]['name']);

        $stored = PickerSelection::allowSpecs('{"power_hp":200,"length_mm":4630,"source_url":"https://secret.example/internal"}');
        $this->assertSame(['power_hp' => 200], $stored);

        $bare = new PDO('sqlite::memory:');
        $bare->exec('CREATE TABLE models (id integer primary key)');
        $bare->exec('INSERT INTO models (id) VALUES (1)');
        $this->assertSame([], PickerSelection::equipment($bare, '1')['unknown']);
        $this->assertSame([], PickerSelection::years($bare, '1', 2026)['rows']);
    }

    public function test_widget_routes_and_script_keep_the_old_event_fields(): void
    {
        $route = new ReflectionMethod(Demo::class, 'route');
        $this->assertSame('modifications', $route->invoke(null, '/widget/v1/demo/generations/100/modifications')['kind']);
        ob_start();
        $invalid = $route->invoke(null, '/widget/v1/demo/generations/0/modifications');
        ob_end_clean();
        $this->assertSame('invalid', $invalid['kind']);

        ob_start();
        $this->assertFalse(CatalogHttp::yearParam('nope', true));
        $body = ob_get_clean();
        $this->assertStringContainsString('invalid_parameter', $body);
        $this->assertNull(CatalogHttp::yearParam(null, false));

        $script = (string) file_get_contents(dirname(__DIR__, 2).'/public/widget/v1/geoapi-filter.js');
        $this->assertStringContainsString('generation_id: this.state.generation', $script);
        $this->assertStringContainsString('modification_id: this.state.modification', $script);
        $this->assertStringContainsString("credentials: 'omit'", $script);
        $this->assertStringContainsString('retries < 2', $script);
        $this->assertStringContainsString("'/modifications'", $script);
        $this->assertStringContainsString('year: this.state.year', $script);
        $this->assertStringNotContainsString('geoapi_sk_', $script);
        $this->assertStringNotContainsString('while (true)', $script);
    }
}
