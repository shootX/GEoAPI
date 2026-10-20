<?php

declare(strict_types=1);

namespace Carapi\Catalog;

final class DemoWatermark
{
    private const FONT = __DIR__ . '/../../fonts/DejaVuSans-Bold.ttf';

    private const TEXT = 'GEOAPI.GE';

    /** @return array{mime:string, bytes:string}|null */
    public static function apply(string $bytes): ?array
    {
        if (!function_exists('imagecreatefromstring') || !is_file(self::FONT)) {
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            return null;
        }
        imagealphablending($image, true);
        $width = imagesx($image);
        $height = imagesy($image);
        $size = max(11, min(16, (int) round($width * 0.022)));
        $shadow = imagecolorallocatealpha($image, 9, 20, 19, 100);
        $ink = imagecolorallocatealpha($image, 255, 255, 255, 90);
        if ($shadow === false || $ink === false) {
            imagedestroy($image);

            return null;
        }
        $box = imagettfbbox($size, 0, self::FONT, self::TEXT);
        $textWidth = (int) abs($box[2] - $box[0]);
        $x = max(8, $width - $textWidth - max(10, (int) round($width * 0.03)));
        $y = $height - max(10, (int) round($height * 0.045));
        imagettftext($image, $size, 0, $x + 1, $y + 1, $shadow, self::FONT, self::TEXT);
        imagettftext($image, $size, 0, $x, $y, $ink, self::FONT, self::TEXT);
        ob_start();
        $written = imagejpeg($image, null, 82);
        $marked = ob_get_clean();
        imagedestroy($image);
        if ($written !== true || !is_string($marked) || $marked === '') {
            return null;
        }

        return ['mime' => 'image/jpeg', 'bytes' => $marked];
    }
}
