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
        $job = substr($deploy, (int) strpos($deploy, "\n  deploy:\n"));
        $this->assertStringContainsString('npm ci', $job);
        $this->assertStringContainsString('npm run build', $job);
        $this->assertLessThan(strpos($job, 'npm run build'), strpos($job, 'php artisan view:cache'));
        $this->assertStringContainsString('needs: [test, sdk]', $job);
        $this->assertStringContainsString('packages/sdk-js', $deploy);
    }
}
