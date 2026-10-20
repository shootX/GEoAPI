<?php

declare(strict_types=1);

namespace Carapi\Platform;

final class FakeMailer implements Mailer
{
    /** @var list<array{to:string,subject:string,text:string}> */
    public static array $messages = [];

    public static bool $fail = false;

    public function send(string $to, string $subject, string $text): void
    {
        if (self::$fail) {
            throw new UnavailableException();
        }
        self::$messages[] = ['to' => $to, 'subject' => $subject, 'text' => $text];
        $path = carapi_env('CARAPI_MAIL_FAKE_PATH');
        if (is_string($path) && $path !== '') {
            file_put_contents($path, json_encode(['to' => $to, 'subject' => $subject, 'text' => $text], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        }
    }

    public static function reset(): void
    {
        self::$messages = [];
        self::$fail = false;
    }
}
