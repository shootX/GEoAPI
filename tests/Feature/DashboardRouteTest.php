<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class DashboardRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_route_is_named(): void
    {
        $this->assertTrue(Route::has('dashboard'));
        $this->assertSame(url('/dashboard'), route('dashboard'));
    }

    public function test_dashboard_name_survives_route_cache_serialization(): void
    {
        $routes = app('router')->getRoutes();
        $routes->refreshNameLookups();
        $compiled = $routes->compile();
        $names = [];
        foreach ($compiled['attributes'] as $attribute) {
            $name = $attribute['action']['as'] ?? null;
            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }
        $this->assertContains('dashboard', $names);
    }

    public function test_forbidden_page_renders_without_server_error(): void
    {
        $html = view('electrik::errors.403')->render();
        $this->assertStringContainsString(url('/dashboard'), $html);
    }

    public function test_admin_panel_does_not_500_for_a_regular_user(): void
    {
        $user = User::factory()->create();
        $status = $this->actingAs($user)->get('/admin')->getStatusCode();
        $this->assertLessThan(500, $status);
    }
}
