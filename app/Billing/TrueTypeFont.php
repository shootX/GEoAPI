<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * BMP cmap format 4 reader for the embedded FiraGO file.
 * The file is SIL OFL. See resources/fonts/FiraGO-OFL.txt.
 */
final class TrueTypeFont
{
    private static ?self $loaded = null;

    /** @var array<int, int> */
    private array $glyphs = [];

    private function __construct(private string $bytes)
    {
        $this->glyphs = self::cmap($bytes);
    }

    public static function load(): self
    {
        if (self::$loaded instanceof self) {
            return self::$loaded;
        }
        $path = dirname(__DIR__, 2).'/resources/fonts/FiraGO-Regular.ttf';
        $bytes = is_file($path) ? file_get_contents($path) : false;
        if (! is_string($bytes) || strlen($bytes) < 12 || substr($bytes, 0, 4) !== "\x00\x01\x00\x00") {
            throw new \RuntimeException('FiraGO font is missing');
        }
        if (str_contains($bytes, 'endstream') || str_contains($bytes, 'endobj')) {
            throw new \RuntimeException('FiraGO font cannot be embedded');
        }

        return self::$loaded = new self($bytes);
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function glyph(int $codepoint): int
    {
        return $this->glyphs[$codepoint] ?? 0;
    }

    /** @return array<int, int> */
    private static function cmap(string $bytes): array
    {
        $tables = self::u16($bytes, 4);
        $cmap = null;
        for ($i = 0; $i < $tables; $i++) {
            $record = 12 + ($i * 16);
            if (substr($bytes, $record, 4) === 'cmap') {
                $cmap = self::u32($bytes, $record + 8);
                break;
            }
        }
        if ($cmap === null) {
            throw new \RuntimeException('cmap');
        }
        $encodings = self::u16($bytes, $cmap + 2);
        $subtable = null;
        for ($i = 0; $i < $encodings; $i++) {
            $record = $cmap + 4 + ($i * 8);
            $platform = self::u16($bytes, $record);
            $encoding = self::u16($bytes, $record + 2);
            $offset = $cmap + self::u32($bytes, $record + 4);
            if ($platform === 3 && $encoding === 1) {
                $subtable = $offset;
                break;
            }
            if ($platform === 0 && $encoding === 3) {
                $subtable = $offset;
            }
        }
        if ($subtable === null || self::u16($bytes, $subtable) !== 4) {
            throw new \RuntimeException('cmap format');
        }

        return self::format4($bytes, $subtable);
    }

    /** @return array<int, int> */
    private static function format4(string $bytes, int $start): array
    {
        $segments = intdiv(self::u16($bytes, $start + 6), 2);
        $endAt = $start + 14;
        $startAt = $endAt + (2 * $segments) + 2;
        $deltaAt = $startAt + (2 * $segments);
        $offsetAt = $deltaAt + (2 * $segments);
        $map = [];
        for ($i = 0; $i < $segments; $i++) {
            $end = self::u16($bytes, $endAt + (2 * $i));
            $from = self::u16($bytes, $startAt + (2 * $i));
            $delta = self::s16($bytes, $deltaAt + (2 * $i));
            $range = self::u16($bytes, $offsetAt + (2 * $i));
            if ($from === 0xFFFF) {
                continue;
            }
            for ($code = $from; $code <= $end; $code++) {
                if ($range === 0) {
                    $glyph = ($code + $delta) & 0xFFFF;
                } else {
                    $pos = $offsetAt + (2 * $i) + $range + (2 * ($code - $from));
                    $glyph = self::u16($bytes, $pos);
                    if ($glyph !== 0) {
                        $glyph = ($glyph + $delta) & 0xFFFF;
                    }
                }
                if ($glyph !== 0) {
                    $map[$code] = $glyph;
                }
            }
        }

        return $map;
    }

    private static function u16(string $bytes, int $offset): int
    {
        $unpacked = unpack('n', substr($bytes, $offset, 2));

        return is_array($unpacked) ? (int) $unpacked[1] : 0;
    }

    private static function s16(string $bytes, int $offset): int
    {
        $value = self::u16($bytes, $offset);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private static function u32(string $bytes, int $offset): int
    {
        $unpacked = unpack('N', substr($bytes, $offset, 4));

        return is_array($unpacked) ? (int) $unpacked[1] : 0;
    }
}
