<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Access\LegacyGate;
use App\Livewire\Account\Products;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** QA retest M3: a new account must learn where to turn the Cars API on, and not see another plan's quota. */
final class ProductsGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_key_refusal_says_where_to_turn_the_cars_api_on(): void
    {
        $denial = LegacyGate::issueKey(404);

        $this->assertNotNull($denial);
        $this->assertStringContainsString('/account/products', $denial['body']['error']['message']);
    }

    public function test_the_products_page_does_not_promise_the_included_plan_quota(): void
    {
        $user = User::factory()->create(['legacy_platform_user_id' => 404]);

        Livewire::actingAs($user)->test(Products::class)
            ->assertDontSee('10 000')
            ->assertSee(route('account.billing'), false);
    }
}
