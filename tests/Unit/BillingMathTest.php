<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Billing\InvoicePdf;
use App\Billing\NbgRate;
use App\Billing\Plans;
use PHPUnit\Framework\TestCase;

final class BillingMathTest extends TestCase
{
    public function test_yearly_price_is_twelve_months_minus_twelve_percent(): void
    {
        $this->assertSame(5900, Plans::usdCents('basic', 'month'));
        $this->assertSame(9900, Plans::usdCents('premium', 'month'));
        $this->assertSame(intdiv(5900 * 12 * 88, 100), Plans::usdCents('basic', 'year'));
        $this->assertSame(intdiv(9900 * 12 * 88, 100), Plans::usdCents('premium', 'year'));
        $this->assertSame(62304, Plans::usdCents('basic', 'year'));
        $this->assertSame(104544, Plans::usdCents('premium', 'year'));
    }

    public function test_gel_uses_the_rate_and_rounds_half_up_to_tetri(): void
    {
        $this->assertSame(15930, Plans::gelTetri(5900, '2.7000', 1));
        $this->assertSame('159.30', Plans::formatTetri(15930));
        $this->assertSame(1, Plans::gelTetri(1, '0.5000', 1));
    }

    public function test_nbg_fixture_uses_the_outer_date_and_the_formatted_rate(): void
    {
        $parsed = NbgRate::parse((string) file_get_contents(dirname(__DIR__).'/fixtures/nbg-usd.json'));

        $this->assertSame('USD', $parsed['code']);
        $this->assertSame('2.7000', $parsed['rate']);
        $this->assertSame(1, $parsed['quantity']);
        $this->assertSame('2026-10-21', $parsed['rated_on']);
        $this->assertSame('https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies/en/json/?currencies=USD', NbgRate::URL);
    }

    public function test_invoice_pdf_contains_the_legal_entity_fields(): void
    {
        $pdf = InvoicePdf::render([
            'kind' => 'legal',
            'name' => 'Nino Beridze',
            'company_name' => 'Test LLC',
            'company_code' => '405123456',
            'company_address' => 'Tbilisi Rustaveli 1',
            'vat_payer' => true,
        ], [
            'number' => 'GEO-20261021-1',
            'plan' => 'basic',
            'usd' => '59.00',
            'gel' => '159.30',
            'rate' => '2.7000',
            'rate_date' => '2026-10-21',
        ]);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('Test LLC', $pdf);
        $this->assertStringContainsString('405123456', $pdf);
        $this->assertStringContainsString('Tbilisi Rustaveli 1', $pdf);
        $this->assertStringContainsString('VAT payer: yes', $pdf);
        $this->assertStringContainsString('Prices include VAT', $pdf);
    }

    public function test_vat_contained_in_an_inclusive_amount(): void
    {
        $this->assertSame('18.00', \App\Billing\InvoicePdf::vatIncluded('118.00'));
        $this->assertSame('24.30', \App\Billing\InvoicePdf::vatIncluded('159.30'));
        $this->assertSame('0.00', \App\Billing\InvoicePdf::vatIncluded('0.00'));
    }
}
