<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * One-page invoice. Text is a CID font so Georgian names use the embedded FiraGO file.
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
            'GEL '.$invoice['gel'],
            'Prices include VAT 18%: VAT GEL '.self::vatIncluded($invoice['gel']).' of GEL '.$invoice['gel'],
        ];
        $font = TrueTypeFont::load();
        /** @var array<int, int> $used */
        $used = [];
        $commands = ['BT', '/F1 11 Tf', '50 760 Td'];
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $commands[] = '0 -16 Td';
            }
            $commands[] = self::glyphs($line, $font, $used).' Tj';
        }
        $commands[] = 'ET';
        $stream = implode("\n", $commands);
        $cmap = self::toUnicode($used);
        $ttf = $font->bytes();
        $widths = '';
        ksort($used);
        foreach (array_keys($used) as $glyph) {
            $widths .= $glyph.' ['.$font->width($glyph).'] ';
        }
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj',
            '4 0 obj << /Length '.strlen($stream).' >> stream'."\n".$stream."\n".'endstream endobj',
            '5 0 obj << /Type /Font /Subtype /Type0 /BaseFont /FiraGO /Encoding /Identity-H /DescendantFonts [6 0 R] /ToUnicode 8 0 R >> endobj',
            '6 0 obj << /Type /Font /Subtype /CIDFontType2 /BaseFont /FiraGO /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 7 0 R /CIDToGIDMap /Identity /DW 500 /W ['.trim($widths).'] >> endobj',
            '7 0 obj << /Type /FontDescriptor /FontName /FiraGO /Flags 32 /FontBBox [-200 -300 1400 1100] /ItalicAngle 0 /Ascent 800 /Descent -200 /CapHeight 700 /StemV 80 /FontFile2 9 0 R >> endobj',
            '8 0 obj << /Length '.strlen($cmap).' >> stream'."\n".$cmap."\n".'endstream endobj',
            '9 0 obj << /Length '.strlen($ttf).' /Length1 '.strlen($ttf).' >> stream'."\n".$ttf."\n".'endstream endobj',
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
        for ($i = 1, $count = count($offsets); $i < $count; $i++) {
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

    /**
     * Rebuild the invoice lines from the ToUnicode map and the content stream.
     */
    public static function plainText(string $pdf): string
    {
        $cmap = '';
        $content = '';
        foreach (self::streams($pdf) as $stream) {
            if (str_contains($stream['dict'], '/Length1')) {
                continue;
            }
            if (str_contains($stream['data'], 'beginbfchar')) {
                $cmap .= $stream['data'];
            }
            if (str_contains($stream['data'], 'BT') && str_contains($stream['data'], 'Tj')) {
                $content .= $stream['data'];
            }
        }
        $map = [];
        if (preg_match_all('/beginbfchar\s*(.*?)\s*endbfchar/s', $cmap, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (! preg_match_all('/<([0-9A-Fa-f]{4})>\s*<([0-9A-Fa-f]+)>/', $block, $pairs, PREG_SET_ORDER)) {
                    continue;
                }
                foreach ($pairs as $pair) {
                    $utf = hex2bin($pair[2]);
                    if ($utf === false) {
                        continue;
                    }
                    $map[strtoupper($pair[1])] = mb_convert_encoding($utf, 'UTF-8', 'UTF-16BE');
                }
            }
        }
        $text = '';
        if (preg_match_all('/<([0-9A-Fa-f]+)> Tj/', $content, $runs)) {
            foreach ($runs[1] as $run) {
                $run = strtoupper($run);
                $length = strlen($run);
                for ($i = 0; $i + 4 <= $length; $i += 4) {
                    $text .= $map[substr($run, $i, 4)] ?? '';
                }
                $text .= "\n";
            }
        }

        return $text;
    }

    /**
     * @param  array<int, int>  $used
     */
    private static function glyphs(string $text, TrueTypeFont $font, array &$used): string
    {
        $hex = '';
        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false) {
            return '<>';
        }
        foreach ($chars as $char) {
            $codepoint = mb_ord($char);
            if (! is_int($codepoint)) {
                continue;
            }
            $glyph = $font->glyph($codepoint);
            $used[$glyph] = $codepoint;
            $hex .= sprintf('%04X', $glyph);
        }

        return '<'.$hex.'>';
    }

    /** @param  array<int, int>  $used */
    private static function toUnicode(array $used): string
    {
        $blocks = '';
        $pairs = '';
        $count = 0;
        foreach ($used as $glyph => $codepoint) {
            $pairs .= sprintf("<%04X> <%s>\n", $glyph, self::utf16($codepoint));
            $count++;
            if ($count === 100) {
                $blocks .= $count." beginbfchar\n".$pairs."endbfchar\n";
                $pairs = '';
                $count = 0;
            }
        }
        if ($count > 0) {
            $blocks .= $count." beginbfchar\n".$pairs."endbfchar\n";
        }

        return implode("\n", [
            '/CIDInit /ProcSet findresource begin',
            '12 dict begin',
            'begincmap',
            '/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> def',
            '/CMapName /FiraGO def',
            '/CMapType 2 def',
            '1 begincodespacerange',
            '<0000> <FFFF>',
            'endcodespacerange',
            rtrim($blocks),
            'endcmap',
            'CMapName currentdict /CMap defineresource pop',
            'end',
            'end',
        ]);
    }

    private static function utf16(int $codepoint): string
    {
        if ($codepoint <= 0xFFFF) {
            return sprintf('%04X', $codepoint);
        }
        $codepoint -= 0x10000;
        $high = 0xD800 + (($codepoint >> 10) & 0x3FF);
        $low = 0xDC00 + ($codepoint & 0x3FF);

        return sprintf('%04X%04X', $high, $low);
    }

    /** @return list<array{dict:string, data:string}> */
    private static function streams(string $pdf): array
    {
        $out = [];
        $offset = 0;
        $marker = "stream\n";
        while (($at = strpos($pdf, $marker, $offset)) !== false) {
            $dataAt = $at + strlen($marker);
            $end = strpos($pdf, "\nendstream", $dataAt);
            if ($end === false) {
                break;
            }
            $dictAt = strrpos(substr($pdf, 0, $at), '<<');
            $out[] = [
                'dict' => $dictAt === false ? '' : substr($pdf, $dictAt, $at - $dictAt),
                'data' => substr($pdf, $dataAt, $end - $dataAt),
            ];
            $offset = $end + 10;
        }

        return $out;
    }
}
