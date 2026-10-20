<?php

declare(strict_types=1);

namespace Carapi\Platform;

use PHPMailer\PHPMailer\PHPMailer;

final class SmtpMailer implements Mailer
{
    public function send(string $to, string $subject, string $text): void
    {
        $host = self::env('CARAPI_MAIL_HOST');
        $from = self::env('CARAPI_MAIL_FROM_ADDRESS');
        $user = self::env('CARAPI_MAIL_USERNAME');
        $password = carapi_env('CARAPI_MAIL_PASSWORD');
        $password = is_string($password) ? $password : '';
        if ($host === '' || $from === '' || $user === '' || $password === '' || ! class_exists(PHPMailer::class)) {
            throw new UnavailableException;
        }

        try {
            $this->deliver($host, $from, $user, $password, $to, $subject, $text);
        } catch (\Throwable) {
            throw new UnavailableException;
        }
    }

    private function deliver(string $host, string $from, string $user, string $password, string $to, string $subject, string $text): void
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = self::port();
        $mail->SMTPAuth = true;
        $mail->Username = $user;
        $mail->Password = $password;
        $mail->SMTPSecure = self::env('CARAPI_MAIL_ENCRYPTION') === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->SMTPAutoTLS = true;
        $mail->Timeout = 10;
        $mail->SMTPDebug = 0;
        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ];
        $mail->CharSet = 'UTF-8';
        $mail->setFrom($from, self::env('CARAPI_MAIL_FROM_NAME') ?: 'carapi');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $text;
        $mail->send();
    }

    public static function configured(): bool
    {
        foreach (['CARAPI_MAIL_HOST', 'CARAPI_MAIL_USERNAME', 'CARAPI_MAIL_FROM_ADDRESS'] as $name) {
            if (self::env($name) === '') {
                return false;
            }
        }
        $password = carapi_env('CARAPI_MAIL_PASSWORD');

        return is_string($password) && $password !== '';
    }

    private static function port(): int
    {
        $raw = carapi_env('CARAPI_MAIL_PORT');

        return is_string($raw) && preg_match('/^[0-9]+$/', $raw) ? (int) $raw : 587;
    }

    private static function env(string $name): string
    {
        $value = carapi_env($name);

        return is_string($value) ? trim($value) : '';
    }
}
