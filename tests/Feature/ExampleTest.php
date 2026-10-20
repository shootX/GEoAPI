<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_unpublished_products_are_not_enabled_by_default(): void
    {
        $this->assertFalse((bool) config('electrik.billing.plan_features.default.cities:read'));
        $this->assertFalse((bool) config('electrik.billing.plan_features.default.parking:read'));
        $this->assertFalse((bool) config('electrik.billing.plan_features.default.cars:read'));
    }
}
