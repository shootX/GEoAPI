<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Tests\TestCase;

/** artisan with a cached config does not load .env; legacy settings then come from config('geoapi.env'). */
final class GeoapiEnvTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['GEOAPI_ENV_PROBE'], $_SERVER['GEOAPI_ENV_PROBE']);
        putenv('GEOAPI_ENV_PROBE');
        Container::setInstance($this->app);
        parent::tearDown();
    }

    public function test_the_environment_wins_and_the_cached_value_is_only_a_fallback(): void
    {
        config(['geoapi.env' => ['GEOAPI_ENV_PROBE' => 'cached']]);
        $this->assertFalse(geoapi_env('GEOAPI_ENV_PROBE'), 'without a cached config the snapshot is not used');

        $this->useAppWithCachedConfig(['GEOAPI_ENV_PROBE' => 'cached']);
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

    /** @param array<string, string> $env */
    private function useAppWithCachedConfig(array $env): void
    {
        $app = new class(base_path()) extends Application
        {
            public function configurationIsCached(): bool
            {
                return true;
            }
        };
        $app->instance('config', new Repository(['geoapi' => ['env' => $env]]));
        Container::setInstance($app);
    }
}
