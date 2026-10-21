<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Catalog\ListQuery;
use App\Catalog\PublicCatalog;
use PDO;
use PHPUnit\Framework\TestCase;

final class PublicCatalogSafetyTest extends TestCase
{
    public function test_missing_spec_and_equipment_tables_return_nulls(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(<<<'SQL'
            CREATE TABLE models (id integer primary key, brand_id integer, slug text, name text, body_type text, updated_at text);
            CREATE TABLE brands (id integer primary key, slug text, name text);
            CREATE TABLE generations (id integer primary key, model_id integer, slug text, name text, chassis_code text, body_type text, year_start integer, year_end integer, steering_wheel text, updated_at text);
            CREATE TABLE modifications (
                id integer primary key, generation_id integer, model_id integer, name text,
                year_start integer, year_end integer, body_type text, drive_type text,
                transmission text, engine_volume_cc integer, updated_at text
            );
            INSERT INTO brands (id, slug, name) VALUES (1, 'audi', 'Audi');
            INSERT INTO models (id, brand_id, slug, name) VALUES (1, 1, 'a4', 'A4');
            INSERT INTO generations (id, model_id, name, chassis_code, year_start, year_end) VALUES
                (1, 1, 'B9', 'B9', 2010, NULL),
                (2, 1, 'Future', 'FUTURE', 2099, 2100);
            INSERT INTO modifications (id, model_id, name, year_start, year_end, engine_volume_cc, transmission, drive_type) VALUES
                (5, 1, '2.0', 2011, 2011, 1984, 'AT', 'FWD'),
                (6, 1, 'bad', 2009, 2008, NULL, NULL, NULL);
        SQL);
        $catalog = new PublicCatalog($pdo);
        $query = ListQuery::parse([], ['year', 'id', 'name', 'volume_cc', 'power_hp', 'modified', 'combined_l'], ['fuel']);

        $years = $catalog->years('1', $query, 2012);
        $this->assertNotNull($years);
        $this->assertSame([2010, 2011, 2012], array_column($years['rows'], 'year'));

        $engines = $catalog->engines($query);
        $this->assertSame('5', $engines['rows'][0]['id']);
        $this->assertNull($engines['rows'][0]['power_hp']);
        $this->assertNull($engines['rows'][0]['fuel']);

        $this->assertSame(0, $catalog->fuel($query)['total']);

        $specs = $catalog->modificationSpecs('5');
        $this->assertNotNull($specs);
        $this->assertNull($specs['engine']['power_hp']);
        $this->assertNull($specs['engine']['fuel']);
        $this->assertSame(1984, $specs['engine']['volume_cc']);
        $this->assertNull($specs['dimensions']);
        $this->assertNull($specs['performance']);
        $this->assertNull($specs['consumption']['city_l']);

        $equipment = $catalog->modificationEquipment('5');
        $this->assertSame([], $equipment['standard']);
        $this->assertSame([], $equipment['unknown']);
        $this->assertNull($catalog->modificationSpecs('999'));
    }
}
