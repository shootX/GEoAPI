<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Billing\StripeSignature;
use App\Billing\StripeSignatureException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test';

    private const NOW = 1_700_000_000;

    /** @var list<MessageLogged> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));
        config(['services.stripe.webhook_secret' => self::SECRET]);
        Log::listen(function (MessageLogged $event): void {
            $this->logged[] = $event;
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_unconfigured_secret_returns_503_and_writes_nothing(): void
    {
        $before = $this->snapshotGrants();
        $payload = $this->payload();
        $header = $this->sign($payload, self::NOW);

        foreach ([null, '', '   '] as $secret) {
            config(['services.stripe.webhook_secret' => $secret]);
            $this->postRaw($header, $payload)
                ->assertStatus(503)
                ->assertJsonPath('error.code', 'billing_not_configured');
        }

        $this->assertSame(0, DB::table('payment_events')->count());
        $this->assertSafeLogs($payload, $header);
        $this->assertNoGrantChanges($before);
    }

    public function test_missing_signature_returns_400(): void
    {
        $before = $this->snapshotGrants();
        $payload = $this->payload();

        $this->postRaw(null, $payload)
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_signature');

        $this->assertSame(0, DB::table('payment_events')->count());
        $this->assertRejection(StripeSignatureException::MISSING, $payload, '');
        $this->assertNoGrantChanges($before);
    }

    public function test_forged_signature_returns_400(): void
    {
        $before = $this->snapshotGrants();
        $payload = $this->payload('evt_forged', 'invoice.paid', 'cs_test_secret');
        $header = 't='.self::NOW.',v1=abcdef';

        $this->postRaw($header, $payload)
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_signature');

        $stale = $this->sign($payload, self::NOW - 10_000);
        $flipped = substr($stale, 0, -1).($stale[-1] === '0' ? '1' : '0');
        $this->postRaw($flipped, $payload)->assertStatus(400);

        $this->postRaw('t='.self::NOW.',v1='.str_repeat('ab', 40), $payload)->assertStatus(400);
        $this->assertSame(0, DB::table('payment_events')->count());
        $this->assertRejection(StripeSignatureException::FORGED, $payload, $header);
        $this->assertNoGrantChanges($before);
    }

    public function test_expired_signature_returns_400(): void
    {
        $before = $this->snapshotGrants();
        $payload = $this->payload();

        foreach ([self::NOW - 301, self::NOW + 301] as $timestamp) {
            $header = $this->sign($payload, $timestamp);
            $this->postRaw($header, $payload)
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'invalid_signature');
            $this->assertRejection(StripeSignatureException::EXPIRED, $payload, $header);
            $this->logged = [];
        }

        $this->assertSame(0, DB::table('payment_events')->count());
        $this->assertNoGrantChanges($before);
    }

    public function test_signature_at_tolerance_boundary_is_accepted(): void
    {
        $before = $this->snapshotGrants();
        $payload = $this->payload('evt_boundary');
        $header = $this->sign($payload, self::NOW - StripeSignature::TOLERANCE_SECONDS);

        $this->postRaw($header, $payload)
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('duplicate', false);

        $this->assertSame(1, DB::table('payment_events')->count());
        $this->assertNoGrantChanges($before);
        $this->assertSafeLogs($payload, $header);
    }

    public function test_second_v1_signature_is_accepted_and_v0_only_is_rejected(): void
    {
        $before = $this->snapshotGrants();
        $payload = $this->payload('evt_multi');
        $good = hash_hmac('sha256', self::NOW.'.'.$payload, self::SECRET);

        $this->postRaw('t='.self::NOW.',v0='.$good, $payload)
            ->assertStatus(400)
            ->assertJsonPath('error.code', 'invalid_signature');
        $this->assertRejection(StripeSignatureException::MISSING, $payload, 't='.self::NOW.',v0='.$good);
        $this->logged = [];

        $this->postRaw('t='.self::NOW.',v1=deadbeef,v1='.$good, $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', false);

        $this->assertSame(1, DB::table('payment_events')->where('event_id', 'evt_multi')->count());
        $this->assertNoGrantChanges($before);
    }

    public function test_valid_event_is_recorded_once_and_does_not_change_grants(): void
    {
        $productId = (int) DB::table('api_products')->where('slug', 'cars')->value('id');
        $planId = (int) DB::table('api_plans')->where('code', 'cars-included')->value('id');
        DB::table('api_entitlements')->insert([
            'legacy_user_id' => 3,
            'api_product_id' => $productId,
            'api_plan_id' => $planId,
            'status' => 'active',
            'starts_at' => now(),
            'source' => 'test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $before = $this->snapshotGrants();
        $payload = $this->payload('evt_paid', 'invoice.paid', 'cs_test_secret');
        $header = $this->sign($payload, self::NOW);

        $this->postRaw($header, $payload)
            ->assertOk()
            ->assertExactJson(['ok' => true, 'duplicate' => false]);

        $this->assertSame(1, DB::table('payment_events')->count());
        $row = DB::table('payment_events')->first();
        $this->assertSame('stripe', $row->provider);
        $this->assertSame('evt_paid', $row->event_id);
        $this->assertSame('invoice.paid', $row->type);
        $this->assertSame(['type' => 'invoice.paid'], json_decode((string) $row->summary, true));
        $this->assertStringNotContainsString('cs_test_secret', (string) $row->summary);
        $this->assertNoGrantChanges($before);
        $this->assertSafeLogs($payload, $header);
        $this->assertSame([], array_map(fn (MessageLogged $event) => $event->message, $this->logged));
    }

    public function test_duplicate_event_returns_200_without_mutation(): void
    {
        $before = $this->snapshotGrants();
        $first = $this->payload('evt_same', 'invoice.paid');
        $second = $this->payload('evt_same', 'customer.subscription.updated', 'sk_test_should_not_stick');

        $this->postRaw($this->sign($first, self::NOW), $first)->assertOk()->assertJsonPath('duplicate', false);
        $stored = DB::table('payment_events')->first();

        $this->postRaw($this->sign($second, self::NOW), $second)
            ->assertOk()
            ->assertExactJson(['ok' => true, 'duplicate' => true]);

        $this->assertSame(1, DB::table('payment_events')->count());
        $again = DB::table('payment_events')->first();
        $this->assertEquals($stored, $again);
        $this->assertSame('invoice.paid', $again->type);
        $this->assertStringNotContainsString('sk_test_should_not_stick', (string) $again->summary);
        $this->assertNoGrantChanges($before);
    }

    public function test_signed_malformed_body_returns_400_and_writes_nothing(): void
    {
        $before = $this->snapshotGrants();
        foreach ([
            'not-json',
            '{}',
            '{"id":"evt_1"}',
            '{"type":"invoice.paid"}',
            '{"id":"","type":"invoice.paid"}',
            '{"id":1,"type":"invoice.paid"}',
        ] as $payload) {
            $header = $this->sign($payload, self::NOW);
            $this->postRaw($header, $payload)
                ->assertStatus(400)
                ->assertJsonPath('error.code', 'invalid_parameter');
            $this->assertRejection(StripeSignatureException::MALFORMED, $payload, $header);
            $this->logged = [];
        }

        $this->assertSame(0, DB::table('payment_events')->count());
        $this->assertNoGrantChanges($before);
    }

    private function payload(string $id = 'evt_1', string $type = 'invoice.paid', ?string $secret = null): string
    {
        $body = ['id' => $id, 'type' => $type];
        if ($secret !== null) {
            $body['client_secret'] = $secret;
        }

        return json_encode($body, JSON_UNESCAPED_SLASHES);
    }

    private function sign(string $payload, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, self::SECRET);
    }

    private function postRaw(?string $header, string $payload): TestResponse
    {
        $server = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];
        if ($header !== null) {
            $server['HTTP_STRIPE_SIGNATURE'] = $header;
        }

        return $this->call('POST', '/billing/provider-webhook', [], [], [], $server, $payload);
    }

    /**
     * @return array{plans: string, entitlements: string}
     */
    private function snapshotGrants(): array
    {
        return [
            'plans' => $this->tableJson('api_plans'),
            'entitlements' => $this->tableJson('api_entitlements'),
        ];
    }

    private function tableJson(string $table): string
    {
        $rows = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

        return json_encode($rows, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array{plans: string, entitlements: string}  $before
     */
    private function assertNoGrantChanges(array $before): void
    {
        $this->assertSame($before, $this->snapshotGrants());
    }

    private function assertRejection(string $reason, string $payload, string $header): void
    {
        $matched = false;
        foreach ($this->logged as $event) {
            if ($event->message !== 'stripe.webhook.rejected') {
                continue;
            }
            $this->assertSame(['reason' => $reason], $event->context);
            $matched = true;
        }
        $this->assertTrue($matched);
        $this->assertSafeLogs($payload, $header);
    }

    private function assertSafeLogs(string $payload, string $header): void
    {
        $blob = '';
        foreach ($this->logged as $event) {
            $blob .= $event->level.' '.$event->message.' '.json_encode($event->context, JSON_UNESCAPED_UNICODE);
        }
        $this->assertStringNotContainsString(self::SECRET, $blob);
        if ($payload !== '') {
            $this->assertStringNotContainsString($payload, $blob);
        }
        if ($header !== '') {
            $this->assertStringNotContainsString($header, $blob);
        }
    }
}
