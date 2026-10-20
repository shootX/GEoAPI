<?php

declare(strict_types=1);

function carapi_env(string $name): mixed
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
