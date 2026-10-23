<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/** The project was renamed from carapi; old CARAPI_* server settings keep working until they are renamed. */
final class GeoapiEnvTest extends TestCase
{
    protected function tearDown(): void
    {
        foreach (['GEOAPI_RENAME_PROBE', 'CARAPI_RENAME_PROBE'] as $name) {
            unset($_ENV[$name], $_SERVER[$name]);
            putenv($name);
        }
        parent::tearDown();
    }

    public function test_the_new_name_wins_and_the_old_name_is_a_fallback(): void
    {
        $this->assertFalse(geoapi_env('GEOAPI_RENAME_PROBE'));

        putenv('CARAPI_RENAME_PROBE=old');
        $this->assertSame('old', geoapi_env('GEOAPI_RENAME_PROBE'));

        $_SERVER['GEOAPI_RENAME_PROBE'] = 'new';
        $this->assertSame('new', geoapi_env('GEOAPI_RENAME_PROBE'));
    }

    public function test_compat_copies_old_names_without_overwriting_new_ones(): void
    {
        $_ENV['CARAPI_RENAME_PROBE'] = 'old';
        geoapi_env_compat();
        $this->assertSame('old', $_SERVER['GEOAPI_RENAME_PROBE']);
        $this->assertSame('old', getenv('GEOAPI_RENAME_PROBE'));

        $_ENV['GEOAPI_RENAME_PROBE'] = 'new';
        $_SERVER['GEOAPI_RENAME_PROBE'] = 'new';
        putenv('GEOAPI_RENAME_PROBE=new');
        geoapi_env_compat();
        $this->assertSame('new', getenv('GEOAPI_RENAME_PROBE'));
    }
}
