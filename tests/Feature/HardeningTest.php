<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

final class HardeningTest extends TestCase
{
    public function test_ready_answers_select_one_and_echoes_a_request_id(): void
    {
        $this->assertFalse((bool) config('session.secure'));

        $response = $this->withHeader('X-Request-Id', 'req_42.ok')->get('/ready');

        $response->assertOk();
        $response->assertExactJson(['ok' => true]);
        $response->assertHeader('X-Request-Id', 'req_42.ok');
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('SELECT', $body);
        $this->assertStringNotContainsString('password', $body);
    }

    public function test_ready_hides_a_database_failure(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new RuntimeException('password=supersecret SQLSTATE'));

        $response = $this->withHeader('X-Request-Id', 'not a valid id')->get('/ready');

        $response->assertStatus(503);
        $response->assertExactJson(['ok' => false]);
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('password', $body);
        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('not a valid id', (string) $response->headers->get('X-Request-Id'));
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $response->headers->get('X-Request-Id'));
    }

    public function test_template_stripe_routes_are_gone_and_billing_screens_redirect(): void
    {
        $webhook = $this->postJson('/stripe/webhook', ['type' => 'customer.subscription.deleted']);
        $this->assertContains($webhook->getStatusCode(), [404, 405]);
        $webhook->assertDontSee('Webhook Handled');
        foreach (['/billing', '/billing/plans', '/billing/subscription', '/billing/invoices'] as $path) {
            $this->get($path)->assertRedirect('/account/billing');
        }
    }

    public function test_up_carries_the_request_id(): void
    {
        $this->withHeader('X-Request-Id', 'health-1')->get('/up')->assertOk()->assertHeader('X-Request-Id', 'health-1');
    }

    public function test_engineering_notes_under_docs_are_not_served(): void
    {
        // QA retest L5: they named env vars, the database role and files that do not exist.
        foreach (['/docs/CATALOG_API.md', '/docs/usage_and_limits.md', '/docs/PROJECTS_API_KEYS.md', '/docs/WIDGET_INTEGRATION.md'] as $path) {
            $this->get($path)->assertNotFound();
        }
        $this->get('/docs')->assertRedirect('/guide');
    }
}
