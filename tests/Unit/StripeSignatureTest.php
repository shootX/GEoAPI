<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Billing\StripeSignature;
use App\Billing\StripeSignatureException;
use PHPUnit\Framework\TestCase;

final class StripeSignatureTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private const NOW = 1_700_000_000;

    public function test_missing_header_or_v1_is_rejected(): void
    {
        $payload = $this->payload();

        $this->assertSame('missing', $this->reason($payload, null));
        $this->assertSame('missing', $this->reason($payload, ''));
        $this->assertSame('missing', $this->reason($payload, '   '));
        $this->assertSame('missing', $this->reason($payload, 't='.self::NOW));
        $this->assertSame('missing', $this->reason($payload, 'v1=abc'));
        $this->assertSame('missing', $this->reason($payload, 't=nope,v1=abc'));
        $this->assertSame('missing', $this->reason($payload, 't='.self::NOW.',v0=abc'));
        $this->assertSame('missing', $this->reason($payload, 't='.self::NOW.',v1='));
    }

    public function test_forged_signature_is_rejected_before_tolerance(): void
    {
        $payload = $this->payload();
        $stale = $this->sign($payload, self::NOW - 10_000);
        $flipped = substr($stale, 0, -1).($stale[-1] === '0' ? '1' : '0');

        $this->assertSame('forged', $this->reason($payload, 't='.self::NOW.',v1=abcdef'));
        $this->assertSame('forged', $this->reason($payload, 't='.self::NOW.',v1='.str_repeat('ab', 40)));
        $this->assertSame('forged', $this->reason($payload, $flipped));
    }

    public function test_expired_signature_is_rejected_outside_tolerance(): void
    {
        $payload = $this->payload();

        $this->assertSame('expired', $this->reason($payload, $this->sign($payload, self::NOW - 301)));
        $this->assertSame('expired', $this->reason($payload, $this->sign($payload, self::NOW + 301)));
    }

    public function test_signature_inside_tolerance_is_accepted_including_the_boundary(): void
    {
        $payload = $this->payload('evt_1', 'invoice.paid');

        foreach ([0, -300, 300] as $offset) {
            $event = StripeSignature::verify($payload, $this->sign($payload, self::NOW + $offset), self::SECRET, self::NOW);
            $this->assertSame(['id' => 'evt_1', 'type' => 'invoice.paid'], $event);
        }
    }

    public function test_any_matching_v1_is_accepted_and_v0_is_ignored(): void
    {
        $payload = $this->payload('evt.1', 'invoice.paid');
        $good = hash_hmac('sha256', self::NOW.'.'.$payload, self::SECRET);
        $header = 't='.self::NOW.', v0=ignored, v1=deadbeef, v1='.$good;

        $event = StripeSignature::verify($payload, $header, self::SECRET, self::NOW);

        $this->assertSame(['id' => 'evt.1', 'type' => 'invoice.paid'], $event);
    }

    public function test_empty_secret_is_unconfigured_even_when_the_mac_would_match(): void
    {
        $payload = $this->payload();
        $header = 't='.self::NOW.',v1='.hash_hmac('sha256', self::NOW.'.'.$payload, '');

        $this->assertSame('unconfigured', $this->reason($payload, $header, ''));
        $this->assertSame('unconfigured', $this->reason($payload, $this->sign($payload, self::NOW), '   '));
    }

    public function test_signed_malformed_json_is_rejected_without_returning_extra_fields(): void
    {
        foreach ([
            'not-json',
            '{}',
            '{"id":"evt_1"}',
            '{"type":"invoice.paid"}',
            '{"id":"","type":"invoice.paid"}',
            '{"id":1,"type":"invoice.paid"}',
            '{"id":"evt_1","type":""}',
        ] as $payload) {
            $this->assertSame('malformed', $this->reason($payload, $this->sign($payload, self::NOW)), $payload);
        }

        $payload = '{"id":"evt_1","type":"invoice.paid","client_secret":"cs_test_secret"}';
        $event = StripeSignature::verify($payload, $this->sign($payload, self::NOW), self::SECRET, self::NOW);

        $this->assertSame(['id' => 'evt_1', 'type' => 'invoice.paid'], $event);
        $this->assertArrayNotHasKey('client_secret', $event);
    }

    private function payload(string $id = 'evt_1', string $type = 'invoice.paid'): string
    {
        return '{"id":"'.$id.'","type":"'.$type.'"}';
    }

    private function sign(string $payload, int $timestamp, string $secret = self::SECRET): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    private function reason(string $payload, ?string $header, string $secret = self::SECRET): string
    {
        try {
            StripeSignature::verify($payload, $header, $secret, self::NOW);
        } catch (StripeSignatureException $exception) {
            return $exception->reason;
        }

        $this->fail('Expected StripeSignatureException.');
    }
}
