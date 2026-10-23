<?php

declare(strict_types=1);

/**
 * Reads GEOAPI_* settings. Until the server's env files are renamed, a missing GEOAPI_X falls back to
 * the old CARAPI_X name (the project was called carapi before 2026-10-23).
 */
function geoapi_env(string $name): mixed
{
    $value = geoapi_env_raw($name);
    if (($value === false || $value === '') && str_starts_with($name, 'GEOAPI_')) {
        $value = geoapi_env_raw('CARAPI_'.substr($name, 7));
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

/** Copies every old CARAPI_X into GEOAPI_X when GEOAPI_X is not set, so Laravel's env() sees the new names too. */
function geoapi_env_compat(): void
{
    $sources = [$_ENV, $_SERVER];
    if (function_exists('getenv')) {
        $sources[] = getenv();
    }
    foreach ($sources as $source) {
        foreach ($source as $old => $value) {
            if (! is_string($old) || ! str_starts_with($old, 'CARAPI_') || ! is_string($value)) {
                continue;
            }
            $new = 'GEOAPI_'.substr($old, 7);
            if (geoapi_env_raw($new) !== false && geoapi_env_raw($new) !== '') {
                continue;
            }
            $_ENV[$new] = $value;
            $_SERVER[$new] = $value;
            if (function_exists('putenv')) {
                putenv($new.'='.$value);
            }
        }
    }
}

geoapi_env_compat();
