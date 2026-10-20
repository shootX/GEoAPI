<?php

declare(strict_types=1);

$fail = static function (string $reason): void {
    fwrite(STDERR, $reason . "\n");
    exit(1);
};

$vhost = file_get_contents('/www/server/panel/vhost/nginx/car.socialsave.cc.conf');
if ($vhost === false) {
    $fail('vhost unreadable');
}
if (!str_contains($vhost, 'enable-php-carapi.conf')) {
    $fail('domain does not use the CarAPI PHP include');
}
if (str_contains($vhost, 'enable-php-84.conf')) {
    $fail('domain still includes the shared PHP 8.4 pool');
}
if (!str_contains($vhost, 'carapi_safe')) {
    $fail('safe access log is not active');
}
if (str_contains($vhost, 'site_total.sock') || str_contains($vhost, 'tag=3__access')) {
    $fail('site_total log returned on this domain');
}

$expect = [
    '/www/server/php/carapi/fpm.sock' => 'carapi:carapi:600',
    '/www/server/php/carapi/nginx.sock' => 'root:www:660',
    '/www/server/carapi/catalog.env' => 'root:root:600',
    '/www/server/carapi/platform.env' => 'root:root:600',
    '/www/server/carapi/mail.env' => 'root:root:600',
    '/www/wwwroot/car.socialsave.cc/data/sessions' => 'carapi:carapi:700',
];
foreach ($expect as $path => $want) {
    $stat = stat($path);
    if ($stat === false) {
        $fail('missing ' . $path);
    }
    $owner = posix_getpwuid($stat['uid'])['name'] ?? '?';
    $group = posix_getgrgid($stat['gid'])['name'] ?? '?';
    $mode = $stat['mode'] & 0777;
    $got = $owner . ':' . $group . ':' . decoct($mode);
    if ($got !== $want) {
        $fail($path . ' mode is ' . $got);
    }
}

$conf = file_get_contents('/www/server/php/carapi/php-fpm.conf');
if ($conf === false) {
    $fail('pool config unreadable');
}
foreach (['CARAPI_CATALOG_ENABLED] = 1', 'CARAPI_PLATFORM_ENABLED] = 1', 'CARAPI_WIDGET_ENABLED] = 1'] as $flag) {
    if (!str_contains($conf, $flag)) {
        $fail('flag is not on: ' . $flag);
    }
}
if (str_contains($conf, 'CARAPI_PLATFORM_ADMIN_')) {
    $fail('admin secret is present in the pool');
}
foreach ([
    'env[CARAPI_MAIL_TRANSPORT] = smtp',
    'env[CARAPI_MAIL_HOST] = mail.socialsave.cc',
    'env[CARAPI_MAIL_PORT] = 587',
    'env[CARAPI_MAIL_ENCRYPTION] = tls',
    'env[CARAPI_MAIL_USERNAME] = info@socialsave.cc',
    'env[CARAPI_MAIL_FROM_ADDRESS] = info@socialsave.cc',
    'env[CARAPI_MAIL_FROM_NAME] = CarAPI',
    'env[CARAPI_APP_URL] = https://car.socialsave.cc',
] as $mail) {
    if (!str_contains($conf, $mail)) {
        $fail('mail setting missing from the pool');
    }
}
$mailFile = file_get_contents('/www/server/carapi/mail.env');
if ($mailFile === false) {
    $fail('mail env unreadable');
}
$filePassword = null;
if (preg_match('/^CARAPI_MAIL_PASSWORD=(.*)$/m', $mailFile, $match) === 1) {
    $filePassword = $match[1];
}
$poolPassword = null;
if (preg_match('/^env\[CARAPI_MAIL_PASSWORD\] = (.*)$/m', $conf, $match) === 1) {
    $poolPassword = $match[1];
}
if ($filePassword === null || $filePassword === '' || $poolPassword === null || $poolPassword === '') {
    $fail('mail password is not set');
}

echo "ok\n";
