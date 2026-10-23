<?php

declare(strict_types=1);

namespace Tests\Unit;

use Carapi\Catalog\DemoWatermark;
use PHPUnit\Framework\TestCase;

final class DemoWatermarkTest extends TestCase
{
    public function test_invalid_bytes_are_not_marked(): void
    {
        $this->assertNull(DemoWatermark::apply('not-an-image'));
    }

    public function test_mark_covers_the_frame_and_leaves_the_car_visible(): void
    {
        $source = $this->jpeg(960, 540, 30, 90, 140);
        $marked = DemoWatermark::apply($source);

        $this->assertIsArray($marked);
        $this->assertSame('image/jpeg', $marked['mime']);
        $this->assertNotSame($source, $marked['bytes']);
        $this->assertStringStartsWith("\xFF\xD8", $marked['bytes']);

        $image = imagecreatefromstring($marked['bytes']);
        $this->assertNotFalse($image);

        $changed = ['nw' => 0, 'ne' => 0, 'sw' => 0, 'se' => 0, 'center' => 0];
        $samples = ['nw' => 0, 'ne' => 0, 'sw' => 0, 'se' => 0, 'center' => 0];
        $width = imagesx($image);
        $height = imagesy($image);
        for ($y = 0; $y < $height; $y += 4) {
            for ($x = 0; $x < $width; $x += 4) {
                $region = $this->region($x, $y, $width, $height);
                $samples[$region]++;
                $color = imagecolorat($image, $x, $y);
                $red = ($color >> 16) & 0xFF;
                $green = ($color >> 8) & 0xFF;
                $blue = $color & 0xFF;
                if (abs($red - 30) + abs($green - 90) + abs($blue - 140) > 36) {
                    $changed[$region]++;
                }
            }
        }

        foreach ($changed as $region => $count) {
            $share = $count / $samples[$region];
            $this->assertGreaterThan(0.015, $share, $region.' has no tiled mark');
            $this->assertLessThan(0.55, $share, $region.' hides the photo');
        }
    }

    private function region(int $x, int $y, int $width, int $height): string
    {
        $midX = $width / 2;
        $midY = $height / 2;
        if ($x > $width * 0.35 && $x < $width * 0.65 && $y > $height * 0.35 && $y < $height * 0.65) {
            return 'center';
        }
        if ($x < $midX && $y < $midY) {
            return 'nw';
        }
        if ($x >= $midX && $y < $midY) {
            return 'ne';
        }
        if ($x < $midX) {
            return 'sw';
        }

        return 'se';
    }

    private function jpeg(int $width, int $height, int $red, int $green, int $blue): string
    {
        $image = imagecreatetruecolor($width, $height);
        $color = imagecolorallocate($image, $red, $green, $blue);
        imagefilledrectangle($image, 0, 0, $width, $height, $color);
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = ob_get_clean();

        return is_string($bytes) ? $bytes : '';
    }
}
