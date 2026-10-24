<?php

declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;

/** artisan with a cached config does not load .env; legacy settings then come from config('geoapi.env'). */
final class GeoapiEnvTest extends TestCase
{
    private ?string $cacheFile = null;

    protected function tearDown(): void
    {
        unset($_ENV['GEOAPI_ENV_PROBE'], $_SERVER['GEOAPI_ENV_PROBE'], $_ENV['APP_CONFIG_CACHE'], $_SERVER['APP_CONFIG_CACHE']);
        putenv('GEOAPI_ENV_PROBE');
        putenv('APP_CONFIG_CACHE');
        if ($this->cacheFile !== null) {
            @unlink($this->cacheFile);
        }
        parent::tearDown();
    }

    public function test_the_environment_wins_and_the_cached_value_is_only_a_fallback(): void
    {
        config(['geoapi.env' => ['GEOAPI_ENV_PROBE' => 'cached']]);
        $this->assertFalse(geoapi_env('GEOAPI_ENV_PROBE'), 'without a cached config the snapshot is not used');

        $this->pretendConfigIsCached();
        $this->assertSame('cached', geoapi_env('GEOAPI_ENV_PROBE'));

        $_SERVER['GEOAPI_ENV_PROBE'] = 'live';
        $this->assertSame('live', geoapi_env('GEOAPI_ENV_PROBE'));
    }

    public function test_the_snapshot_keeps_only_geoapi_settings(): void
    {
        $_ENV['GEOAPI_ENV_PROBE'] = 'x';
        $snapshot = geoapi_env_snapshot();

        $this->assertSame('x', $snapshot['GEOAPI_ENV_PROBE']);
        foreach (array_keys($snapshot) as $name) {
            $this->assertStringStartsWith('GEOAPI_', $name);
        }
    }

    private function pretendConfigIsCached(): void
    {
        $this->cacheFile = tempnam(sys_get_temp_dir(), 'geoapi-config-');
        $_ENV['APP_CONFIG_CACHE'] = $_SERVER['APP_CONFIG_CACHE'] = $this->cacheFile;
        $this->assertTrue($this->app->configurationIsCached());
    }
}
