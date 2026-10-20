<?php

declare(strict_types=1);

$fail = static function (string $reason): void {
    fwrite(STDERR, $reason . "\n");
    exit(1);
};

$code = trim((string) shell_exec('curl -sk -o /dev/null -w %{http_code} --max-time 15 https://car.socialsave.cc/'));
if ($code !== '200') {
    $fail('homepage is not reachable');
}

foreach (['carapi-php-fpm.service', 'carapi-fpm-proxy.service'] as $service) {
    $status = trim((string) shell_exec('systemctl is-active ' . escapeshellarg($service) . ' 2>/dev/null'));
    if ($status !== 'active') {
        $fail($service . ' is ' . $status);
    }
}

$cert = shell_exec('echo | openssl s_client -starttls smtp -connect mail.socialsave.cc:587 -servername mail.socialsave.cc 2>/dev/null | openssl x509 -noout -checkend 1209600');
if ($cert === null || !str_contains($cert, 'Certificate will not expire')) {
    $fail('mail certificate expires within 14 days or could not be read');
}

echo "ok\n";
