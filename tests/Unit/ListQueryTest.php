<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Catalog\ListQuery;
use PHPUnit\Framework\TestCase;

final class ListQueryTest extends TestCase
{
    public function test_page_size_defaults_to_100_and_stops_at_1000(): void
    {
        $this->assertSame(100, ListQuery::parse([], ['name'], [])->limit);
        $this->assertSame(1000, ListQuery::parse(['limit' => '1000'], ['name'], [])->limit);
        $this->assertSame(2, ListQuery::parse(['per_page' => '2'], ['name'], [])->limit);
        $this->assertSame(5, ListQuery::parse(['limit' => '5', 'per_page' => '2'], ['name'], [])->limit);

        $tooBig = ListQuery::parse(['limit' => '1001'], ['name'], []);
        $this->assertTrue($tooBig->fails());
        $this->assertTrue(ListQuery::parse(['limit' => '0'], ['name'], [])->fails());
    }

    public function test_sort_is_a_whitelist_and_filters_are_endpoint_specific(): void
    {
        $injected = ListQuery::parse(['sort' => 'name; drop table brands'], ['name', 'id'], []);
        $this->assertTrue($injected->fails());
        $this->assertNull($injected->sort);

        $allowed = ListQuery::parse(['sort' => 'name', 'direction' => 'DESC'], ['name'], []);
        $this->assertSame('name', $allowed->sort);
        $this->assertSame('desc', $allowed->direction);

        $foreign = ListQuery::parse(['year_from' => '2020'], ['name'], []);
        $this->assertTrue($foreign->fails());

        $control = ListQuery::parse(['q' => "toyota\n"], ['name'], []);
        $this->assertTrue($control->fails());
    }
}
