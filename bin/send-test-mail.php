<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$to = null;
$send = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--send') {
        $send = true;
        continue;
    }
    if (str_starts_with($arg, '--to=')) {
        $to = substr($arg, 5);
    }
}

if (!$send || !is_string($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "not sent: --to and --send are required\n");
    exit(2);
}

foreach (file('/www/server/carapi/mail.env', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
        continue;
    }
    [$name, $value] = explode('=', $line, 2);
    putenv($name . '=' . $value);
}

try {
    $mailer = Carapi\Platform\Mail::make();
    if (!$mailer instanceof Carapi\Platform\SmtpMailer) {
        throw new Carapi\Platform\UnavailableException();
    }
    $mailer->send($to, 'CarAPI SMTP test', 'CarAPI SMTP connection test.');
} catch (Throwable $e) {
    fwrite(STDERR, "not sent\n");
    exit(1);
}

echo "SMTP სერვერმა მიიღო\n";
