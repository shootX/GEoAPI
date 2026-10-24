<?php

declare(strict_types=1);

/**
 * Reads a GEOAPI_* setting. On the web, PHP-FPM passes it in the environment. In artisan with a cached
 * config, Laravel does not load .env, so the value config:cache captured in config('geoapi.env') is used.
 */
function geoapi_env(string $name): mixed
{
    $value = geoapi_env_raw($name);
    if ($value === false || $value === '') {
        return geoapi_env_cached($name) ?? $value;
    }

    return $value;
}

function geoapi_env_raw(string $name): mixed
{
    if (array_key_exists($name, $_ENV) && $_ENV[$name] !== '') {
        return $_ENV[$name];
    }
    if (array_key_exists($name, $_SERVER) && $_SERVER[$name] !== '') {
        return $_SERVER[$name];
    }
    if (! function_exists('getenv')) {
        return false;
    }

    return getenv($name);
}

function geoapi_env_cached(string $name): ?string
{
    if (PHP_SAPI !== 'cli' || ! class_exists(\Illuminate\Foundation\Application::class)) {
        return null;
    }
    $app = \Illuminate\Container\Container::getInstance();
    if (! $app instanceof \Illuminate\Foundation\Application || ! $app->bound('config') || ! $app->configurationIsCached()) {
        return null;
    }
    $value = ((array) $app['config']->get('geoapi.env', []))[$name] ?? null;

    return is_string($value) && $value !== '' ? $value : null;
}

/** @return array<string, string> every GEOAPI_* value visible now, for config/geoapi.php */
function geoapi_env_snapshot(): array
{
    $sources = [$_ENV, $_SERVER];
    if (function_exists('getenv')) {
        $sources[] = getenv();
    }
    $values = [];
    foreach ($sources as $source) {
        foreach ($source as $name => $value) {
            if (is_string($name) && str_starts_with($name, 'GEOAPI_') && is_string($value) && $value !== '' && ! isset($values[$name])) {
                $values[$name] = $value;
            }
        }
    }
    ksort($values);

    return $values;
}
