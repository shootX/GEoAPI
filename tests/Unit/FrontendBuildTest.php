<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FrontendBuildTest extends TestCase
{
    public function test_sources_do_not_request_the_missing_bpg_arial_font(): void
    {
        $root = dirname(__DIR__, 2);
        $files = array_merge(glob($root.'/resources/css/*.css') ?: [], glob($root.'/public/assets/fonts/*.css') ?: [], glob($root.'/public/assets/*.css') ?: []);
        $this->assertNotEmpty($files);
        foreach ($files as $file) {
            $this->assertStringNotContainsStringIgnoringCase('bpg-arial', (string) file_get_contents($file), $file);
        }
    }

    public function test_deploy_rebuilds_the_gitignored_vite_bundle(): void
    {
        $deploy = (string) file_get_contents(dirname(__DIR__, 2).'/.github/workflows/deploy.yml');
        $this->assertStringContainsString('npm ci', $deploy);
        $this->assertStringContainsString('npm run build', $deploy);
        $this->assertLessThan(strpos($deploy, 'npm run build'), strpos($deploy, 'php artisan view:cache'));
    }
}
