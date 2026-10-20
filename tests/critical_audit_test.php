<?php

declare(strict_types=1);

$root = dirname(__DIR__);
spl_autoload_register(static function (string $class) use ($root): void {
    if (str_starts_with($class, 'Carapi\\')) {
        $file = $root . '/src/' . str_replace('\\', '/', substr($class, 7)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$failures = [];
$check = static function (bool $ok, string $name) use (&$failures): void {
    echo ($ok ? 'ok ' : 'FAIL ') . $name . "\n";
    if (!$ok) {
        $failures[] = $name;
    }
};

$mailer = file_get_contents($root . '/src/Platform/SmtpMailer.php');
$check(is_string($mailer) && !str_contains($mailer, 'new PHPMailer') && !str_contains($mailer, 'use PHPMailer\\'), 'smtp mailer does not load PHPMailer');
$check(!class_exists(\PHPMailer\PHPMailer\PHPMailer::class, false), 'PHPMailer is not required to boot');

putenv('CARAPI_MAIL_HOST=127.0.0.1');
putenv('CARAPI_MAIL_PORT=9');
putenv('CARAPI_MAIL_USERNAME=info@example.com');
putenv('CARAPI_MAIL_PASSWORD=test-secret-value');
putenv('CARAPI_MAIL_FROM_ADDRESS=info@example.com');
putenv('CARAPI_MAIL_ENCRYPTION=ssl');
$leaked = false;
try {
    (new Carapi\Platform\SmtpMailer())->send('person@example.com', 'subject', 'body');
    $check(false, 'smtp failure is an exception');
} catch (Carapi\Platform\UnavailableException) {
    $check(true, 'smtp failure is an exception');
} catch (Throwable $e) {
    $leaked = str_contains($e->getMessage(), 'test-secret-value') || str_contains($e->getMessage(), 'PHPMailer');
    $check(false, 'smtp failure is an exception');
}
$check(!$leaked, 'smtp exception hides the password');

$sessionProc = proc_open([PHP_BINARY, $root . '/tests/session_cookie_check.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $sessionPipes);
$sessionOut = stream_get_contents($sessionPipes[1]);
$sessionErr = stream_get_contents($sessionPipes[2]);
fclose($sessionPipes[1]);
fclose($sessionPipes[2]);
proc_close($sessionProc);
$check($sessionOut === 'ok' && !str_contains($sessionErr, 'Session ID is too long'), 'illegal laravel session cookie does not break session_start');

$sql = new PDOException('SQLSTATE[23505] duplicate key DETAIL carbase 127.0.0.1 /opt/carapi-electrik/vendor/laravel');
$check(Carapi\Http\Failure::status($sql) === 409, 'unique violation is 409');
$json = Carapi\Http\Failure::jsonBody(409);
$check(!str_contains($json, 'SQLSTATE') && !str_contains($json, '/opt/') && !str_contains($json, 'carbase'), 'conflict json hides sql and paths');
$html = Carapi\Http\Failure::htmlBody(500);
$check(!str_contains($html, 'Stack trace') && !str_contains($html, '.php'), 'html error hides traces');

if ($failures !== []) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}
