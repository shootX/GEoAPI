<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$host = '127.0.0.1';
$port = 18082;
$php = PHP_BINARY;
$cmd = escapeshellarg($php) . ' -S ' . $host . ':' . $port
    . ' -t ' . escapeshellarg($root . '/public') . ' ' . escapeshellarg($root . '/public/index.php');
$proc = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', '/tmp/docs-access-test.log', 'w'], 2 => ['file', '/tmp/docs-access-test.err', 'w']], $pipes, $root, [
    'PATH' => getenv('PATH') ?: '/usr/bin',
]);
if (!is_resource($proc)) {
    fwrite(STDERR, "server failed to start\n");
    exit(1);
}

$stop = static function () use ($proc, &$pipes): void {
    if (isset($pipes[0]) && is_resource($pipes[0])) {
        fclose($pipes[0]);
    }
    proc_terminate($proc);
    proc_close($proc);
};

$ready = false;
for ($i = 0; $i < 40; $i++) {
    $probe = @fsockopen($host, $port, $errno, $err, 0.2);
    if (is_resource($probe)) {
        fclose($probe);
        $ready = true;
        break;
    }
    usleep(100000);
}
if (!$ready) {
    fwrite(STDERR, "server did not listen\n");
    $stop();
    exit(1);
}

/** @return array{0:int,1:string} */
function docs_raw(string $host, int $port, string $target): array
{
    $fp = fsockopen($host, $port, $errno, $err, 3);
    if (!is_resource($fp)) {
        return [0, ''];
    }
    fwrite($fp, "GET {$target} HTTP/1.0\r\nHost: {$host}\r\nConnection: close\r\n\r\n");
    $raw = stream_get_contents($fp);
    fclose($fp);
    if (!is_string($raw) || !str_contains($raw, "\r\n\r\n")) {
        return [0, ''];
    }
    [$head, $body] = explode("\r\n\r\n", $raw, 2);
    if (preg_match('#^HTTP/\S+\s+(\d+)#', $head, $match) !== 1) {
        return [0, $body];
    }

    return [(int) $match[1], $body];
}

$failures = 0;
$check = static function (string $label, int $status, string $body, int $expect, ?string $need, ?string $forbid) use (&$failures): void {
    $ok = $status === $expect
        && ($need === null || str_contains($body, $need))
        && ($forbid === null || !str_contains($body, $forbid));
    if ($ok) {
        echo "ok {$label} {$status}\n";

        return;
    }
    $failures++;
    echo "fail {$label} status={$status} expect={$expect} need=" . ($need !== null && str_contains($body, $need) ? 'yes' : 'no')
        . ' forbid=' . ($forbid !== null && str_contains($body, $forbid) ? 'yes' : 'no') . "\n";
};

$public = [
    '/docs/CATALOG_API.md' => 'კატალოგის შიდა API',
    '/docs/catalog_api.md' => 'კატალოგის შიდა API',
    '/docs/PROJECTS_API_KEYS.md' => 'პროექტები და სერვერული გასაღებები',
    '/docs/projects_api_keys.md' => 'პროექტები და სერვერული გასაღებები',
    '/docs/USAGE_AND_LIMITS.md' => 'კატალოგის ლიმიტები და მოხმარება',
    '/docs/usage_and_limits.md' => 'კატალოგის ლიმიტები და მოხმარება',
    '/docs/WIDGET_INTEGRATION.md' => 'ჩასასმელი ფილტრი',
    '/docs/widget_integration.md' => 'ჩასასმელი ფილტრი',
];
foreach ($public as $path => $marker) {
    [$status, $body] = docs_raw($host, $port, $path);
    $check($path, $status, $body, 200, $marker, null);
}

$private = [
    '/docs/EMAIL_AND_PASSWORD_RESET.md' => 'CARAPI_MAIL_PASSWORD',
    '/docs/email_and_password_reset.md' => 'CARAPI_MAIL_PASSWORD',
    '/docs/PLATFORM_AUTH.md' => 'CARAPI_PLATFORM_ADMIN_PASSWORD',
    '/docs/platform_auth.md' => 'CARAPI_PLATFORM_ADMIN_PASSWORD',
    '/docs/DEPLOYMENT.md' => 'carapi-fpm-proxy.service',
    '/docs/deployment.md' => 'carapi-fpm-proxy.service',
    '/docs/PROJECT_AUDIT.md' => 'carapi.sqlite',
    '/docs/project_audit.md' => 'carapi.sqlite',
    '/docs/copy-audit-ka.md' => 'ქართული ტექსტების აუდიტი',
    '/docs/COPY-AUDIT-KA.md' => 'ქართული ტექსტების აუდიტი',
];
foreach ($private as $path => $marker) {
    [$status, $body] = docs_raw($host, $port, $path);
    $check($path, $status, $body, 404, 'გვერდი ვერ მოიძებნა', $marker);
}

$blocked = [
    '/docs/%2e%2e/composer.json',
    '/docs/%252e%252e/composer.json',
    '/docs/%00.md',
    '/docs/./CATALOG_API.md',
    '/docs/CATALOG_API.md/%2e%2e/DEPLOYMENT.md',
];
foreach ($blocked as $path) {
    [$status, $body] = docs_raw($host, $port, $path);
    $check($path, $status, $body, 404, null, 'carapi-fpm-proxy.service');
}

foreach (['/guide' => 'CarAPI-ის დოკუმენტაცია', '/privacy' => 'კონფიდენციალურობის პოლიტიკა', '/docs' => 'CarAPI'] as $path => $marker) {
    [$status, $body] = docs_raw($host, $port, $path);
    $check($path, $status, $body, 200, $marker, null);
}

$disk = [
    'EMAIL_AND_PASSWORD_RESET.md',
    'PLATFORM_AUTH.md',
    'DEPLOYMENT.md',
    'PROJECT_AUDIT.md',
    'copy-audit-ka.md',
    'CATALOG_API.md',
    'PROJECTS_API_KEYS.md',
    'USAGE_AND_LIMITS.md',
    'WIDGET_INTEGRATION.md',
];
foreach ($disk as $name) {
    if (!is_file($root . '/docs/' . $name)) {
        $failures++;
        echo "fail disk missing {$name}\n";
    }
}

$stop();
if ($failures > 0) {
    fwrite(STDERR, "failures {$failures}\n");
    exit(1);
}
echo "ok\n";
