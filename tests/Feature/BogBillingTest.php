<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Billing\BillingNotConfigured;
use App\Billing\BogClient;
use App\Billing\BogSignature;
use App\Billing\Entitlements;
use App\Billing\InvoicePdf;
use App\Billing\NbgRate;
use App\Livewire\Account\Billing;
use App\Livewire\Auth\Register;
use App\Models\User;
use Carapi\Platform\ApiKeys;
use Carapi\Platform\Connection as PlatformConnection;
use Carapi\Platform\ProjectRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PDO;
use Tests\TestCase;

final class BogBillingTest extends TestCase
{
    use SeedsPublicCatalog {
        setUp as seedCatalog;
        tearDown as forgetCatalog;
    }

    private PDO $platform;

    private string $privateKey;

    protected function setUp(): void
    {
        $this->seedCatalog();
        $this->platform = DB::connection()->getPdo();
        $this->platform->sqliteCreateFunction('now', static fn (): string => gmdate('Y-m-d H:i:s'));
        $this->platformSchema(true);
        PlatformConnection::use($this->platform);
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $private);
        $this->privateKey = $private;
        $details = openssl_pkey_get_details($key);
        config(['services.bog.public_key' => $details['key']]);
    }

    protected function tearDown(): void
    {
        PlatformConnection::reset();
        foreach (['CARAPI_USAGE_NOW', 'CARAPI_USAGE_SQLITE', 'CARAPI_USAGE_MEMORY', 'CARAPI_CATALOG_TEST_PROJECT'] as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
        $this->forgetCatalog();
    }

    public function test_migration_does_not_rewrite_catalog_tables_or_existing_quotas(): void
    {
        $sql = (string) file_get_contents(base_path('database/migrations/2026_12_31_910000_billing_bog.php'));

        $this->assertStringContainsString('ADD COLUMN IF NOT EXISTS', $sql);
        $this->assertStringContainsString("environment('testing')", $sql);
        $this->assertStringNotContainsString('modification_equipment', $sql);
        $this->assertStringNotContainsString('public.modifications', $sql);
        $this->assertStringNotContainsString('UPDATE platform.project_limits', $sql);
        $this->assertStringNotContainsString('UPDATE platform.users', $sql);
        $this->assertNotNull(DB::table('api_plans')->where('code', 'basic')->value('id'));
        $this->assertNotNull(DB::table('api_plans')->where('code', 'cars-included')->value('id'));
    }

    public function test_checkout_without_credentials_does_not_call_the_bank_or_change_quota(): void
    {
        Http::fake();
        $user = $this->owner();
        $this->limits(7, 10000, null, false, null);

        Livewire::actingAs($user)->test(Billing::class)
            ->assertSee('payments not configured')
            ->set('projectId', 7)
            ->call('checkout', 'basic', 'month')
            ->assertHasErrors('checkout');

        Http::assertNothingSent();
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertSame(0, DB::table('billing_orders')->count());
    }

    public function test_a_paid_checkout_waits_for_the_signed_callback(): void
    {
        $this->rate();
        config(['services.bog.client_id' => 'sandbox-client', 'services.bog.client_secret' => 'sandbox-secret-value']);
        Http::fake([
            'https://oauth2-sandbox.bog.ge/*' => Http::response(['access_token' => 'sandbox-access-token'], 200),
            'https://api-sandbox.bog.ge/*' => Http::response([
                'id' => 'bog-order-1',
                '_links' => ['redirect' => ['href' => 'https://payment-sandbox.bog.ge/pay/bog-1']],
            ], 200),
        ]);
        $user = $this->owner();
        $this->limits(7, 10000, null, false, null);

        Livewire::actingAs($user)->test(Billing::class)
            ->set('projectId', 7)
            ->call('checkout', 'basic', 'month')
            ->assertRedirect('https://payment-sandbox.bog.ge/pay/bog-1');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api-sandbox.bog.ge/payments/v1/ecommerce/orders');
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '://api.bog.ge') || str_contains($request->url(), '://oauth2.bog.ge'));
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $order = DB::table('billing_orders')->first();
        $this->assertNotNull($order);
        $this->assertSame('pending', $order->status);
        $this->assertSame('159.30', number_format((float) $order->gel_amount, 2, '.', ''));
        $this->assertStringNotContainsString('sandbox-secret-value', json_encode(DB::table('billing_audit')->get()));

        $this->actingAs($user)->get('/account/billing?paid=return')->assertOk()->assertSee('ბანკის დადასტურება');
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
    }

    public function test_callback_grants_once_and_a_refund_restores_the_previous_quota(): void
    {
        $user = $this->owner();
        $this->limits(7, 10000, null, false, null);
        $publicId = $this->pendingOrder($user, 'basic');
        $body = $this->callbackBody($publicId, 'bog-1', 'completed');

        $this->postRaw($body, $this->sign($body))->assertOk()->assertJsonPath('duplicate', false);
        $this->assertSame(2000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertSame('basic', DB::table('platform.project_limits')->where('project_id', 7)->value('plan_code'));
        $this->assertSame('paid', DB::table('billing_orders')->where('public_id', $publicId)->value('status'));
        $basic = (int) DB::table('api_plans')->where('code', 'basic')->value('id');
        $this->assertSame($basic, (int) DB::table('api_entitlements')->where('legacy_user_id', 3)->value('api_plan_id'));
        $this->assertSame('active', DB::table('api_entitlements')->where('legacy_user_id', 3)->value('status'));

        $this->postRaw($body, $this->sign($body))->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame(1, DB::table('payment_events')->where('provider', 'bog')->count());
        $this->assertSame(2000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));

        $refund = $this->callbackBody($publicId, 'bog-1', 'refunded');
        $this->postRaw($refund, $this->sign($refund))->assertOk();
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertNull(DB::table('platform.project_limits')->where('project_id', 7)->value('plan_code'));
        $included = (int) DB::table('api_plans')->where('code', 'cars-included')->value('id');
        $this->assertSame($included, (int) DB::table('api_entitlements')->where('legacy_user_id', 3)->value('api_plan_id'));
        $pdf = (string) DB::table('billing_invoices')->value('pdf');
        $text = InvoicePdf::plainText($pdf);
        $this->assertStringContainsString('ნინო ბერიძე', $text);
        $this->assertStringContainsString('Test LLC', $text);
        $this->assertStringContainsString('405123456', $text);
        $this->assertStringContainsString('Tbilisi Rustaveli 1', $text);
        $this->assertStringContainsString('VAT payer: yes', $text);
        $this->assertStringContainsString('Prices include VAT 18%: VAT GEL', $text);
        $this->assertStringContainsString('/FontFile2 9 0 R', $pdf);
        $this->assertStringNotContainsString('sandbox-secret-value', $pdf);
        $this->assertStringNotContainsString('sandbox-secret-value', json_encode(DB::table('billing_audit')->get()));
    }

    public function test_a_signed_callback_with_a_different_amount_does_not_grant(): void
    {
        $user = $this->owner();
        $this->limits(7, 10000, null, false, null);
        $publicId = $this->pendingOrder($user, 'basic');
        $body = $this->callbackBody($publicId, 'bog-9', 'completed', '1.00');

        $this->postRaw($body, $this->sign($body))->assertOk();
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertSame('pending', DB::table('billing_orders')->where('public_id', $publicId)->value('status'));
    }

    public function test_a_partial_refund_keeps_the_paid_plan_and_records_the_amount(): void
    {
        config(['services.bog.client_secret' => 'sandbox-secret-value']);
        $user = $this->owner();
        $this->limits(7, 10000, null, false, null);
        $publicId = $this->pendingOrder($user, 'basic');
        $completed = $this->callbackBody($publicId, 'bog-partial', 'completed');
        $this->postRaw($completed, $this->sign($completed))->assertOk();

        $first = $this->callbackBody($publicId, 'bog-partial', 'refunded_partially', null, '10.00');
        $this->postRaw($first, $this->sign($first))->assertOk();
        $this->assertSame(2000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertSame('basic', DB::table('platform.project_limits')->where('project_id', 7)->value('plan_code'));
        $basic = (int) DB::table('api_plans')->where('code', 'basic')->value('id');
        $this->assertSame($basic, (int) DB::table('api_entitlements')->where('legacy_user_id', 3)->value('api_plan_id'));
        $order = DB::table('billing_orders')->where('public_id', $publicId)->first();
        $this->assertSame('paid', $order->status);
        $this->assertSame('10.00', number_format((float) $order->refunded_gel, 2, '.', ''));

        $repeat = $this->callbackBody($publicId, 'bog-partial', 'refunded_partially', null, '10.00');
        $this->postRaw($repeat, $this->sign($repeat))->assertOk()->assertJsonPath('duplicate', true);
        $this->assertSame('10.00', number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('refunded_gel'), 2, '.', ''));

        // refund_amount is cumulative: a second 5.50 refund arrives as 15.50.
        $second = $this->callbackBody($publicId, 'bog-partial', 'refunded_partially', null, '15.50');
        $this->postRaw($second, $this->sign($second))->assertOk()->assertJsonPath('duplicate', false);
        $this->assertSame('15.50', number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('refunded_gel'), 2, '.', ''));
        $this->assertSame(2000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertSame('paid', DB::table('billing_orders')->where('public_id', $publicId)->value('status'));

        $over = $this->callbackBody($publicId, 'bog-partial', 'refunded_partially', null, '159.31');
        $this->postRaw($over, $this->sign($over))->assertOk();
        $this->assertSame('15.50', number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('refunded_gel'), 2, '.', ''));
        $this->assertSame(2000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));

        $audit = json_encode(DB::table('billing_audit')->where('action', 'partial_refund')->get());
        $this->assertIsString($audit);
        $this->assertStringContainsString('10.00', $audit);
        $this->assertStringContainsString('15.50', $audit);
        $stale = $this->callbackBody($publicId, 'bog-partial', 'refunded_partially', null, '12.00');
        $this->postRaw($stale, $this->sign($stale))->assertOk();
        $this->assertSame('15.50', number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('refunded_gel'), 2, '.', ''));
        $this->assertStringNotContainsString('sandbox-secret-value', $audit);

        $full = $this->callbackBody($publicId, 'bog-partial', 'refunded');
        $this->postRaw($full, $this->sign($full))->assertOk();
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertSame('refunded', DB::table('billing_orders')->where('public_id', $publicId)->value('status'));
        $this->assertSame('159.30', number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('refunded_gel'), 2, '.', ''));
    }

    public function test_a_partial_refund_for_the_full_amount_does_not_revoke_the_plan(): void
    {
        $user = $this->owner();
        $this->limits(7, 10000, null, false, null);
        $publicId = $this->pendingOrder($user, 'basic');
        $completed = $this->callbackBody($publicId, 'bog-full-partial', 'completed');
        $this->postRaw($completed, $this->sign($completed))->assertOk();
        $gel = number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('gel_amount'), 2, '.', '');

        $partial = $this->callbackBody($publicId, 'bog-full-partial', 'refunded_partially', null, $gel);
        $this->postRaw($partial, $this->sign($partial))->assertOk();
        $this->assertSame('paid', DB::table('billing_orders')->where('public_id', $publicId)->value('status'));
        $this->assertSame(2000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertSame('basic', DB::table('platform.project_limits')->where('project_id', 7)->value('plan_code'));
        $this->assertSame($gel, number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('refunded_gel'), 2, '.', ''));
    }

    public function test_a_partial_refund_before_payment_does_not_block_the_grant(): void
    {
        $user = $this->owner();
        $this->limits(7, 10000, null, false, null);
        $publicId = $this->pendingOrder($user, 'basic');
        $partial = $this->callbackBody($publicId, 'bog-early', 'refunded_partially', null, '10.00');
        $this->postRaw($partial, $this->sign($partial))->assertOk();
        $this->assertSame('pending', DB::table('billing_orders')->where('public_id', $publicId)->value('status'));
        $this->assertSame('10.00', number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('refunded_gel'), 2, '.', ''));
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));

        $completed = $this->callbackBody($publicId, 'bog-early', 'completed');
        $this->postRaw($completed, $this->sign($completed))->assertOk();
        $this->assertSame('paid', DB::table('billing_orders')->where('public_id', $publicId)->value('status'));
        $this->assertSame(2000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertSame('10.00', number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('refunded_gel'), 2, '.', ''));
    }

    public function test_a_full_refund_with_a_different_amount_does_not_revoke(): void
    {
        $user = $this->owner();
        $this->limits(7, 10000, null, false, null);
        $publicId = $this->pendingOrder($user, 'basic');
        $completed = $this->callbackBody($publicId, 'bog-short', 'completed');
        $this->postRaw($completed, $this->sign($completed))->assertOk();

        $refund = $this->callbackBody($publicId, 'bog-short', 'refunded', null, '1.00');
        $this->postRaw($refund, $this->sign($refund))->assertOk();
        $this->assertSame('paid', DB::table('billing_orders')->where('public_id', $publicId)->value('status'));
        $this->assertSame(2000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertNull(DB::table('billing_orders')->where('public_id', $publicId)->value('refunded_gel'));
    }

    public function test_bog_env_selects_the_host_pair_and_the_matching_public_key(): void
    {
        $override = config('services.bog.public_key');
        config(['services.bog.public_key' => null, 'services.bog.env' => 'sandbox']);
        $this->assertSame('sandbox', BogClient::environment());
        $this->assertStringContainsString('qczfAuhtxw2iF68kS0Hy', BogSignature::publicKey());

        config(['services.bog.env' => 'staging']);
        $this->assertSame('sandbox', BogClient::environment());
        $this->assertStringContainsString('qczfAuhtxw2iF68kS0Hy', BogSignature::publicKey());

        config(['services.bog.env' => 'production']);
        $this->assertSame('production', BogClient::environment());
        $production = BogSignature::publicKey();
        $this->assertStringContainsString('Au4RUyAw3+CdkS3ZNILQh', $production);
        $this->assertStringNotContainsString('qczfAuhtxw2iF68kS0Hy', $production);

        config(['services.bog.public_key' => $override, 'services.bog.env' => 'production']);
        $this->assertSame(trim((string) $override), BogSignature::publicKey());
    }

    public function test_production_env_posts_only_to_production_hosts(): void
    {
        $this->rate();
        config([
            'services.bog.env' => 'production',
            'services.bog.client_id' => 'prod-client',
            'services.bog.client_secret' => 'prod-secret-value',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://oauth2.bog.ge/*' => Http::response(['access_token' => 'prod-access-token'], 200),
            'https://api.bog.ge/*' => Http::response([
                'id' => 'bog-prod-1',
                '_links' => ['redirect' => ['href' => 'https://payment.bog.ge/pay/bog-1']],
            ], 200),
        ]);
        $user = $this->owner();
        $this->limits(7, 10000, null, false, null);

        Livewire::actingAs($user)->test(Billing::class)
            ->set('projectId', 7)
            ->call('checkout', 'basic', 'month')
            ->assertRedirect('https://payment.bog.ge/pay/bog-1');

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.bog.ge/payments/v1/ecommerce/orders');
        Http::assertSent(fn ($request): bool => $request->url() === 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token');
        Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'sandbox.bog.ge'));
        $this->assertStringNotContainsString('prod-secret-value', json_encode(DB::table('billing_audit')->get()));
    }

    public function test_sandbox_refuses_a_production_host(): void
    {
        config([
            'services.bog.env' => 'sandbox',
            'services.bog.client_id' => 'sandbox-client',
            'services.bog.client_secret' => 'sandbox-secret-value',
            'services.bog.sandbox.token_url' => 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token',
        ]);
        Http::preventStrayRequests();
        Http::fake();

        $this->expectException(BillingNotConfigured::class);
        BogClient::createOrder('ext-1', '159.30', 'basic', 'https://geoapi.ge/billing/bog/callback', 'https://geoapi.ge/ok', 'https://geoapi.ge/fail');
    }

    public function test_a_bad_signature_stores_nothing_and_a_refund_before_payment_does_not_grant(): void
    {
        $user = $this->owner();
        $this->limits(7, 10000, null, false, null);
        $publicId = $this->pendingOrder($user, 'premium');
        $body = $this->callbackBody($publicId, 'bog-2', 'completed');

        $this->postRaw($body, base64_encode('nope'))->assertStatus(400);
        $this->assertSame(0, DB::table('payment_events')->count());
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));

        $refund = $this->callbackBody($publicId, 'bog-2', 'refunded');
        $this->postRaw($refund, $this->sign($refund))->assertOk();
        $completed = $this->callbackBody($publicId, 'bog-2', 'completed');
        $this->postRaw($completed, $this->sign($completed))->assertOk();
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertSame('refunded', DB::table('billing_orders')->where('public_id', $publicId)->value('status'));
    }

    public function test_free_choice_sets_100_and_expiry_ends_the_paid_cap(): void
    {
        $user = $this->owner();
        $this->limits(7, 10000, null, false, null);
        Livewire::actingAs($user)->test(Billing::class)->set('projectId', 7)->call('chooseFree');
        $this->assertSame(100, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertSame('free', DB::table('platform.project_limits')->where('project_id', 7)->value('plan_code'));
        $this->assertSame(1, DB::table('billing_audit')->where('action', 'choose_free')->count());

        $ends = now()->addMonth()->utc()->format('Y-m-d H:i:s');
        $this->limits(7, 0, 'premium', true, $ends);
        DB::table('platform.monthly_usage')->insert([
            'project_id' => 7,
            'period' => gmdate('Y-m-01'),
            'used_count' => 50,
        ]);
        $this->useStore();
        $issued = $this->issueKey();
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands?limit=1')
            ->assertOk()
            ->assertHeader('X-Quota-Limit', 'unlimited');

        $this->limits(7, 0, 'premium', true, '2020-01-01 00:00:00');
        DB::table('platform.monthly_usage')->where('project_id', 7)->update(['used_count' => 0]);
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/brands?limit=1')
            ->assertOk()
            ->assertHeader('X-Quota-Limit', '100');

        $this->limits(7, 0, 'premium', true, '2020-01-01 00:00:00');
        $this->assertSame(1, Entitlements::expireDue());
        $this->assertSame(100, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('monthly_quota'));
        $this->assertSame('free', DB::table('platform.project_limits')->where('project_id', 7)->value('plan_code'));
        $this->assertSame(0, (int) DB::table('platform.project_limits')->where('project_id', 7)->value('unlimited_month'));
        $this->assertSame(1, DB::table('billing_audit')->where('action', 'expire')->count());
        $this->assertSame(0, Entitlements::expireDue());
    }

    public function test_new_projects_keep_10000_until_grandfathered_is_false(): void
    {
        DB::statement('DROP TABLE platform.users');
        DB::statement('CREATE TABLE platform.users (id integer primary key, first_name text, last_name text, email text, password_hash text, status text not null default \'active\')');
        DB::table('platform.users')->insert(['id' => 9, 'first_name' => 'Old', 'last_name' => 'User', 'email' => 'old@example.com', 'password_hash' => 'x']);
        $repo = new ProjectRepository($this->platform);
        $id = $repo->insert(9, 'Kept', null);
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', $id)->value('monthly_quota'));

        DB::statement('ALTER TABLE platform.users ADD COLUMN grandfathered integer NOT NULL DEFAULT 0');
        DB::table('platform.users')->where('id', 9)->update(['grandfathered' => 1]);
        DB::table('platform.users')->insert(['id' => 10, 'first_name' => 'New', 'last_name' => 'User', 'email' => 'new@example.com', 'password_hash' => 'x', 'grandfathered' => 0]);
        $kept = $repo->insert(9, 'Still kept', null);
        $fresh = $repo->insert(10, 'Free default', null);
        $this->assertSame(10000, (int) DB::table('platform.project_limits')->where('project_id', $kept)->value('monthly_quota'));
        $this->assertSame(100, (int) DB::table('platform.project_limits')->where('project_id', $fresh)->value('monthly_quota'));
        $this->assertSame('free', DB::table('platform.project_limits')->where('project_id', $fresh)->value('plan_code'));
    }

    public function test_nbg_command_stores_a_fixture_and_falls_back_when_the_fetch_fails(): void
    {
        $fixture = (string) file_get_contents(base_path('tests/fixtures/nbg-usd.json'));
        Http::preventStrayRequests();
        Http::fake([
            'https://nbg.gov.ge/*' => Http::sequence()
                ->push($fixture, 200)
                ->push('nope', 500)
                ->push('nope', 500),
        ]);
        $this->artisan('billing:nbg-rate')->assertOk();
        $this->assertSame('2026-10-21', substr((string) DB::table('billing_rates')->value('rated_on'), 0, 10));

        $this->artisan('billing:nbg-rate')->assertOk();
        $this->assertSame(1, DB::table('billing_rates')->count());

        DB::table('billing_rates')->delete();
        $this->artisan('billing:nbg-rate')->assertFailed();
    }

    public function test_registration_rejects_an_identification_code_that_is_not_9_or_11_digits(): void
    {
        foreach (['12345', '4051234567', '40512345a', '405123456789'] as $code) {
            Livewire::test(Register::class)
                ->set('buyer_kind', 'legal')
                ->set('company_code', $code)
                ->call('register')
                ->assertHasErrors(['company_code' => 'regex']);
        }
    }

    public function test_registration_stores_legal_entity_fields(): void
    {
        DB::statement('DROP TABLE IF EXISTS platform.email_tokens');
        DB::statement('CREATE TABLE platform.email_tokens (id integer primary key, user_id integer, purpose text, token_hash text, email_snapshot text, expires_at text, revoked_at text, used_at text)');
        DB::statement('DROP TABLE platform.users');
        DB::statement("CREATE TABLE platform.users (id integer primary key, first_name text, last_name text, email text, password_hash text, terms_version text, terms_accepted_at text, status text not null default 'active', email_verified_at text, auth_version integer not null default 1)");

        Livewire::test(Register::class)
            ->set('name', 'Nino Beridze')
            ->set('email', 'legal@example.com')
            ->set('password', 'correct-horse-battery')
            ->set('password_confirmation', 'correct-horse-battery')
            ->set('accept_terms', true)
            ->set('buyer_kind', 'legal')
            ->set('company_name', 'Test LLC')
            ->set('company_code', '405123456')
            ->set('company_address', 'Tbilisi Rustaveli 1')
            ->set('vat_payer', true)
            ->call('register')
            ->assertHasNoErrors();

        $user = User::query()->where('email', 'legal@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('legal', $user->buyer_kind);
        $this->assertSame('Test LLC', $user->company_name);
        $this->assertSame('405123456', $user->company_code);
        $this->assertSame('Tbilisi Rustaveli 1', $user->company_address);
        $this->assertTrue((bool) $user->vat_payer);
    }

    private function platformSchema(bool $planColumns): void
    {
        foreach ([
            'platform.email_tokens',
            'platform.rate_windows',
            'platform.daily_usage',
            'platform.monthly_usage',
            'platform.api_keys',
            'platform.project_limits',
            'platform.projects',
            'platform.users',
        ] as $table) {
            DB::statement('DROP TABLE IF EXISTS '.$table);
        }
        $limits = $planColumns
            ? 'CREATE TABLE platform.project_limits (project_id integer primary key, per_minute integer not null, monthly_quota integer not null, per_day integer not null, plan_code text, plan_ends_at text, unlimited_month integer not null default 0)'
            : 'CREATE TABLE platform.project_limits (project_id integer primary key, per_minute integer not null, monthly_quota integer not null, per_day integer not null)';
        foreach ([
            "CREATE TABLE platform.users (id integer primary key, first_name text, last_name text, email text, password_hash text, terms_version text, terms_accepted_at text, status text not null default 'active', email_verified_at text)",
            "CREATE TABLE platform.projects (id integer primary key, user_id integer not null, name text, website_url text, status text not null default 'active', created_at text, updated_at text)",
            'CREATE TABLE platform.api_keys (id integer primary key, project_id integer not null, name text, key_selector text not null, secret_hash text not null, scope text not null default \'catalog:read\', created_at text, expires_at text, revoked_at text, last_used_at text)',
            $limits,
            'CREATE TABLE platform.monthly_usage (project_id integer not null, period text not null, used_count integer not null, primary key (project_id, period))',
            'CREATE TABLE platform.daily_usage (project_id integer not null, day text not null, route text not null, used_count integer not null, primary key (project_id, day, route))',
            'CREATE TABLE platform.rate_windows (scope text not null, subject text not null, window_start text not null, hit_count integer not null, primary key (scope, subject, window_start))',
        ] as $sql) {
            DB::statement($sql);
        }
        DB::table('platform.users')->insert([
            'id' => 3,
            'first_name' => 'Nino',
            'last_name' => 'Beridze',
            'email' => 'owner@example.com',
            'password_hash' => 'x',
            'status' => 'active',
            'email_verified_at' => '2026-01-01 00:00:00',
        ]);
        DB::table('platform.projects')->insert([
            'id' => 7,
            'user_id' => 3,
            'name' => 'Catalog',
            'website_url' => 'https://owner.example',
            'status' => 'active',
        ]);
    }

    private function owner(): User
    {
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'email_verified_at' => now(),
        ]);
        $user->forceFill([
            'legacy_platform_user_id' => 3,
            'buyer_kind' => 'legal',
            'company_name' => 'Test LLC',
            'company_code' => '405123456',
            'company_address' => 'Tbilisi Rustaveli 1',
            'vat_payer' => true,
        ])->save();

        return $user;
    }

    private function limits(int $projectId, int $quota, ?string $plan, bool $unlimited, ?string $ends): void
    {
        DB::table('platform.project_limits')->updateOrInsert(['project_id' => $projectId], [
            'per_minute' => 60,
            'monthly_quota' => $quota,
            'per_day' => 2000,
            'plan_code' => $plan,
            'plan_ends_at' => $ends,
            'unlimited_month' => $unlimited ? 1 : 0,
        ]);
    }

    private function rate(): void
    {
        NbgRate::remember(NbgRate::parse((string) file_get_contents(base_path('tests/fixtures/nbg-usd.json'))));
    }

    private function pendingOrder(User $user, string $plan): string
    {
        $this->rate();

        return Entitlements::openOrder((int) $user->id, 3, 7, $plan, 'month', NbgRate::latest(), [
            'kind' => 'legal',
            'name' => 'ნინო ბერიძე',
            'company_name' => 'Test LLC',
            'company_code' => '405123456',
            'company_address' => 'Tbilisi Rustaveli 1',
            'vat_payer' => true,
        ]);
    }

    private function callbackBody(string $publicId, string $bogId, string $status, ?string $amount = null, ?string $refundAmount = null): string
    {
        $amount ??= number_format((float) DB::table('billing_orders')->where('public_id', $publicId)->value('gel_amount'), 2, '.', '');
        $units = ['request_amount' => $amount, 'transfer_amount' => $amount, 'currency_code' => 'GEL'];
        if ($refundAmount !== null) {
            $units['refund_amount'] = $refundAmount;
        }

        return json_encode([
            'event' => 'order_payment',
            'zoned_request_time' => '2026-10-21T12:00:00.000000Z',
            'body' => [
                'order_id' => $bogId,
                'external_order_id' => $publicId,
                'order_status' => ['key' => $status, 'value' => $status],
                'purchase_units' => $units,
            ],
        ], JSON_UNESCAPED_SLASHES);
    }

    private function sign(string $body): string
    {
        openssl_sign($body, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function postRaw(string $body, string $signature): TestResponse
    {
        return $this->call('POST', '/billing/bog/callback', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_CALLBACK_SIGNATURE' => $signature,
        ], $body);
    }

    /** @return array{token:string} */
    private function issueKey(): array
    {
        $issued = ApiKeys::issue();
        $this->assertTrue((new ProjectRepository($this->platform))->insertKey(3, 7, 'server', $issued['selector'], $issued['hash'], null));

        return $issued;
    }

    private function useStore(): void
    {
        $_ENV['CARAPI_USAGE_MEMORY'] = '0';
        $_SERVER['CARAPI_USAGE_MEMORY'] = '0';
        putenv('CARAPI_USAGE_MEMORY=0');
        unset($_ENV['CARAPI_CATALOG_TEST_PROJECT'], $_SERVER['CARAPI_CATALOG_TEST_PROJECT']);
        putenv('CARAPI_CATALOG_TEST_PROJECT');
    }
}
