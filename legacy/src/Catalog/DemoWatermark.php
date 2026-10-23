<?php

declare(strict_types=1);

namespace Geoapi\Catalog;

final class DemoWatermark
{
    private const FONT = __DIR__.'/../../fonts/DejaVuSans-Bold.ttf';

    private const TEXT = 'GEOAPI.GE';

    /** @return array{mime:string, bytes:string}|null */
    public static function apply(string $bytes): ?array
    {
        if (! function_exists('imagecreatefromstring') || ! is_file(self::FONT)) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }
        imagealphablending($image, true);
        $width = imagesx($image);
        $height = imagesy($image);
        $size = max(22, (int) round($width * 0.042));
        $shadow = imagecolorallocatealpha($image, 20, 16, 28, 78);
        $ink = imagecolorallocatealpha($image, 255, 255, 255, 62);
        if ($shadow === false || $ink === false) {
            return null;
        }
        $box = imagettfbbox($size, -28, self::FONT, self::TEXT);
        $stepX = (int) round(abs($box[2] - $box[0]) + $size * 1.6);
        $stepY = (int) round(abs($box[1] - $box[5]) + $size * 2.2);
        $row = 0;
        for ($y = (int) round(-$height * 0.2); $y < $height + $stepY; $y += $stepY) {
            $shift = ($row % 2) * (int) round($stepX / 2);
            for ($x = (int) round(-$width * 0.35) + $shift; $x < $width + $stepX; $x += $stepX) {
                imagettftext($image, $size, -28, $x + 2, $y + 2, $shadow, self::FONT, self::TEXT);
                imagettftext($image, $size, -28, $x, $y, $ink, self::FONT, self::TEXT);
            }
            $row++;
        }
        ob_start();
        $written = imagejpeg($image, null, 82);
        $marked = ob_get_clean();
        if ($written !== true || ! is_string($marked) || $marked === '') {
            return null;
        }

        return ['mime' => 'image/jpeg', 'bytes' => $marked];
    }
}
