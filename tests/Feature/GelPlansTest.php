<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Billing\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** QA retest M4: api_plans still carried USD and the old prices; GeoAPI charges only in GEL. */
final class GelPlansTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_plan_row_is_in_gel_at_the_current_price(): void
    {
        $this->assertSame(0, DB::table('api_plans')->where('currency', '!=', 'GEL')->count());

        foreach (['basic' => ['basic', 'month'], 'basic-year' => ['basic', 'year'], 'premium' => ['premium', 'month'], 'premium-year' => ['premium', 'year']] as $code => [$plan, $interval]) {
            $this->assertSame(Plans::tetri($plan, $interval), (int) DB::table('api_plans')->where('code', $code)->value('price_minor'), $code);
        }
    }
}
