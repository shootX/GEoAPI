<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** QA retest L11: every committed third-party file has a recorded version and checksum. */
final class VendorAssetsTest extends TestCase
{
    public function test_every_vendor_file_is_listed_with_its_current_checksum(): void
    {
        $root = dirname(__DIR__, 2);
        $manifest = json_decode((string) file_get_contents($root.'/resources/vendor-assets.json'), true, flags: JSON_THROW_ON_ERROR);
        $listed = [];
        foreach ($manifest['files'] as $file) {
            $this->assertNotSame('', $file['version'], $file['path']);
            $this->assertSame($file['sha256'], hash_file('sha256', $root.'/'.$file['path']), $file['path'].' changed; update its version and sha256');
            $listed[] = $file['path'];
        }

        $onDisk = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/public/vendor', \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $path) {
            $name = $path->getFilename();
            if (str_ends_with($name, '.map') || str_starts_with($name, 'LICENSE') || str_ends_with($name, '.LICENSE.txt')) {
                continue;
            }
            $onDisk[] = substr($path->getPathname(), strlen($root) + 1);
        }
        sort($listed);
        sort($onDisk);
        $this->assertSame($onDisk, $listed);
    }
}
