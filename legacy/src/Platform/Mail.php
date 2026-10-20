<?php

declare(strict_types=1);

namespace Carapi\Platform;

final class Mail
{
    public static function make(): Mailer
    {
        $transport = carapi_env('CARAPI_MAIL_TRANSPORT');
        $transport = is_string($transport) ? strtolower(trim($transport)) : '';
        if ($transport === 'fake') {
            return new FakeMailer();
        }
        if ($transport === 'smtp' && SmtpMailer::configured()) {
            return new SmtpMailer();
        }

        throw new UnavailableException();
    }

    public static function appUrl(): string
    {
        $url = carapi_env('CARAPI_APP_URL');
        $url = is_string($url) ? rtrim(trim($url), '/') : '';
        $parts = parse_url($url);
        $host = is_array($parts) ? (string) ($parts['host'] ?? '') : '';
        $scheme = is_array($parts) ? (string) ($parts['scheme'] ?? '') : '';
        $local = in_array($host, ['localhost', '127.0.0.1'], true);
        if ($url === '' || ($scheme !== 'https' && !$local) || ($scheme !== 'http' && $scheme !== 'https')) {
            throw new UnavailableException();
        }

        return $url;
    }
}
