<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Billing\InvoicePdf;
use App\Billing\Plans;
use App\Billing\TrueTypeFont;
use PHPUnit\Framework\TestCase;

final class BillingMathTest extends TestCase
{
    public function test_yearly_price_is_twelve_months_minus_twelve_percent(): void
    {
        $this->assertSame(15900, Plans::tetri('basic', 'month'));
        $this->assertSame(26700, Plans::tetri('premium', 'month'));
        $this->assertSame(intdiv(15900 * 12 * 88, 100), Plans::tetri('basic', 'year'));
        $this->assertSame(intdiv(26700 * 12 * 88, 100), Plans::tetri('premium', 'year'));
        $this->assertSame(167904, Plans::tetri('basic', 'year'));
        $this->assertSame(281952, Plans::tetri('premium', 'year'));
    }

    public function test_prices_are_lari_without_a_currency_rate(): void
    {
        $this->assertSame('159 ₾', Plans::lari(15900));
        $this->assertSame('1679.04 ₾', Plans::lari(Plans::tetri('basic', 'year')));
        $this->assertSame('0 ₾', Plans::lari(0));
        $this->assertSame('159.00', Plans::formatTetri(15900));
        $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/app/Billing/NbgRate.php');
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
            'gel' => '159.30',
        ]);

        $text = InvoicePdf::plainText($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('Test LLC', $text);
        $this->assertStringContainsString('405123456', $text);
        $this->assertStringContainsString('Tbilisi Rustaveli 1', $text);
        $this->assertStringContainsString('VAT payer: yes', $text);
        $this->assertStringContainsString('Prices include VAT 18%: VAT GEL 24.30 of GEL 159.30', $text);
        $this->assertStringContainsString('/BaseFont /FiraGO', $pdf);
        $this->assertStringContainsString('/FontFile2 9 0 R', $pdf);
    }

    public function test_invoice_pdf_embeds_firago_and_round_trips_a_georgian_name(): void
    {
        $name = 'ნინო ბერიძე';
        $font = TrueTypeFont::load();
        foreach (mb_str_split($name) as $char) {
            $codepoint = mb_ord($char);
            $this->assertIsInt($codepoint);
            $this->assertGreaterThan(0, $font->glyph($codepoint));
        }

        $pdf = InvoicePdf::render([
            'kind' => 'legal',
            'name' => $name,
            'company_name' => 'შპს ტესტი',
            'company_code' => '405123456',
            'company_address' => 'თბილისი',
            'vat_payer' => true,
        ], [
            'number' => 'GEO-20261021-1',
            'plan' => 'basic',
            'gel' => '159.30',
        ]);

        $text = InvoicePdf::plainText($pdf);
        $this->assertStringContainsString($name, $text);
        $this->assertStringContainsString('შპს ტესტი', $text);
        $this->assertStringContainsString('თბილისი', $text);
        $this->assertStringContainsString('/Length1 '.strlen($font->bytes()), $pdf);
        $this->assertStringNotContainsString('endstream', $font->bytes());
    }

    public function test_vat_contained_in_an_inclusive_amount(): void
    {
        $this->assertSame('18.00', InvoicePdf::vatIncluded('118.00'));
        $this->assertSame('24.30', InvoicePdf::vatIncluded('159.30'));
        $this->assertSame('0.00', InvoicePdf::vatIncluded('0.00'));
    }
}
