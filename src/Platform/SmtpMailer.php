<?php

declare(strict_types=1);

namespace Carapi\Platform;

/**
 * SMTP without PHPMailer. The class must load even when vendor/ is absent.
 */
final class SmtpMailer implements Mailer
{
    public function send(string $to, string $subject, string $text): void
    {
        $host = self::env('CARAPI_MAIL_HOST');
        $from = self::env('CARAPI_MAIL_FROM_ADDRESS');
        $user = self::env('CARAPI_MAIL_USERNAME');
        $password = getenv('CARAPI_MAIL_PASSWORD');
        $password = is_string($password) ? $password : '';
        if ($host === '' || $from === '' || $user === '' || $password === '' || !self::address($to) || !self::address($from) || strpbrk($subject, "\r\n") !== false) {
            throw new UnavailableException();
        }

        $encryption = strtolower(self::env('CARAPI_MAIL_ENCRYPTION'));
        $remote = ($encryption === 'ssl' ? 'ssl' : 'tcp') . '://' . $host . ':' . self::port();
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);
        $socket = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) {
            error_log('[platform] smtp connect failed');
            throw new UnavailableException();
        }
        stream_set_timeout($socket, 10);
        try {
            self::expect($socket, [220]);
            self::command($socket, 'EHLO carapi', [250]);
            if ($encryption !== 'ssl') {
                self::command($socket, 'STARTTLS', [220]);
                $crypto = stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) {
                    error_log('[platform] smtp tls failed');
                    throw new UnavailableException();
                }
                self::command($socket, 'EHLO carapi', [250]);
            }
            self::command($socket, 'AUTH LOGIN', [334]);
            self::command($socket, base64_encode($user), [334]);
            self::command($socket, base64_encode($password), [235]);
            self::command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            self::command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::command($socket, 'DATA', [354]);
            $written = fwrite($socket, self::message($from, $to, $subject, $text) . "\r\n.\r\n");
            if ($written === false) {
                throw new UnavailableException();
            }
            self::expect($socket, [250]);
            self::command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
    }

    public static function configured(): bool
    {
        foreach (['CARAPI_MAIL_HOST', 'CARAPI_MAIL_USERNAME', 'CARAPI_MAIL_FROM_ADDRESS'] as $name) {
            if (self::env($name) === '') {
                return false;
            }
        }
        $password = getenv('CARAPI_MAIL_PASSWORD');

        return is_string($password) && $password !== '';
    }

    private static function address(string $value): bool
    {
        return preg_match('/^[^\s@<>"]+@[^\s@<>"]+$/', $value) === 1 && strpbrk($value, "\r\n") === false;
    }

    /** @param resource $socket @param list<int> $ok */
    private static function command($socket, string $line, array $ok): void
    {
        if (strpbrk($line, "\r\n") !== false) {
            throw new UnavailableException();
        }
        if (fwrite($socket, $line . "\r\n") === false) {
            throw new UnavailableException();
        }
        self::expect($socket, $ok);
    }

    /** @param resource $socket @param list<int> $ok */
    private static function expect($socket, array $ok): void
    {
        $buffer = '';
        while (($line = fgets($socket, 515)) !== false) {
            $buffer .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        $code = (int) substr($buffer, 0, 3);
        if (!in_array($code, $ok, true)) {
            error_log('[platform] smtp rejected');
            throw new UnavailableException();
        }
    }

    private static function message(string $from, string $to, string $subject, string $text): string
    {
        $name = self::env('CARAPI_MAIL_FROM_NAME');
        $name = $name !== '' && strpbrk($name, "\r\n\"") === false ? $name : 'carapi';
        $encoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $body = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = explode("\n", $body);
        foreach ($lines as $i => $line) {
            if (str_starts_with($line, '.')) {
                $lines[$i] = '.' . $line;
            }
        }
        $headers = [
            'From: ' . $name . ' <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encoded,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . implode("\r\n", $lines);
    }

    private static function port(): int
    {
        $raw = getenv('CARAPI_MAIL_PORT');

        return is_string($raw) && preg_match('/^[0-9]+$/', $raw) ? (int) $raw : 587;
    }

    private static function env(string $name): string
    {
        $value = getenv($name);

        return is_string($value) ? trim($value) : '';
    }
}
