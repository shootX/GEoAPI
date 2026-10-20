<?php

declare(strict_types=1);

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'Carapi\\')) {
        require $root . '/src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
    }
});

$dir = sys_get_temp_dir() . '/carapi-critical-sessions';
if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}
putenv('CARAPI_PLATFORM_SESSION_PATH=' . $dir);
$_COOKIE['carapi_platform'] = str_repeat('A', 180) . '+/=';
$_COOKIE['carapi-session'] = str_repeat('B', 400);
$_SERVER['HTTPS'] = 'off';
Carapi\Platform\Session::start();
$id = session_id();
echo preg_match('/^[a-zA-Z0-9,-]{1,128}$/', $id) === 1 ? 'ok' : 'bad';
