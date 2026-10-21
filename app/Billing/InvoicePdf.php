<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * One-page invoice. Buyer fields are literal text in the content stream.
 */
final class InvoicePdf
{
    /**
     * @param  array{kind:string, name:string, company_name:string, company_code:string, company_address:string, vat_payer:bool}  $buyer
     * @param  array{number:string, plan:string, usd:string, gel:string, rate:string, rate_date:string}  $invoice
     */
    public static function render(array $buyer, array $invoice): string
    {
        $lines = [
            'Invoice '.$invoice['number'],
            'Buyer '.$buyer['name'],
            'Kind '.$buyer['kind'],
            'Company name '.$buyer['company_name'],
            'Identification code '.$buyer['company_code'],
            'Legal address '.$buyer['company_address'],
            'VAT payer: '.($buyer['vat_payer'] ? 'yes' : 'no'),
            'Plan '.$invoice['plan'],
            'USD '.$invoice['usd'],
            'GEL '.$invoice['gel'],
            'NBG rate '.$invoice['rate'].' on '.$invoice['rate_date'],
            'Prices include VAT 18%: VAT GEL '.self::vatIncluded($invoice['gel']).' of GEL '.$invoice['gel'],
        ];
        $commands = ['BT', '/F1 11 Tf', '50 760 Td'];
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $commands[] = '0 -16 Td';
            }
            $commands[] = self::literal($line).' Tj';
        }
        $commands[] = 'ET';
        $stream = implode("\n", $commands);
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj',
            '4 0 obj << /Length '.strlen($stream).' >> stream'."\n".$stream."\n".'endstream endobj',
            '5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($offsets))."\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i < count($offsets); $i++) {
            $pdf .= str_pad((string) $offsets[$i], 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }
        $pdf .= 'trailer << /Size '.(count($offsets))." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xref."\n%%EOF";

        return $pdf;
    }

    /** VAT contained in a VAT-inclusive GEL amount: total * 18 / 118, rounded half up to the tetri. */
    public static function vatIncluded(string $gel): string
    {
        if (! preg_match('/^(\d+)\.(\d{2})$/', $gel, $m)) {
            return '0.00';
        }
        $tetri = ((int) $m[1]) * 100 + (int) $m[2];
        $vat = intdiv($tetri * 18 * 2 + 118, 118 * 2);

        return Plans::formatTetri($vat);
    }

    private static function literal(string $value): string
    {
        return '('.str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value).')';
    }
}
