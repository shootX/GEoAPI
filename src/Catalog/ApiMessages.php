<?php

declare(strict_types=1);

namespace Carapi\Catalog;

final class ApiMessages
{
    /** @var array<string, string> */
    private const EN = [
        'მითითებული მისამართით მონაცემები ვერ მოიძებნა.' => 'Nothing was found at that address.',
        'მითითებული ბრენდი ვერ მოიძებნა.' => 'That brand was not found.',
        'მითითებული მოდელი ვერ მოიძებნა.' => 'That model was not found.',
        'მითითებული თაობა ვერ მოიძებნა.' => 'That generation was not found.',
        'მითითებული მოდიფიკაცია ვერ მოიძებნა.' => 'That modification was not found.',
        'სერვისი დროებით მიუწვდომელია. სცადეთ მოთხოვნის ხელახლა გაგზავნა მოგვიანებით.' => 'The service is temporarily unavailable. Try the request again later.',
        'ეს HTTP მეთოდი დაუშვებელია. გამოიყენეთ GET.' => 'This HTTP method is not allowed. Use GET.',
        'ეს HTTP მეთოდი დაუშვებელია. გამოიყენეთ POST.' => 'This HTTP method is not allowed. Use POST.',
        'მოთხოვნების ლიმიტი მიღწეულია.' => 'The request limit has been reached.',
        'მოთხოვნა არ არის დაშვებული.' => 'The request is not allowed.',
        'მოთხოვნა არ არის დაშვებული. მოთხოვნა არ აკმაყოფილებს გასაღების ან პროექტის წვდომის პირობებს.' => 'The request is not allowed for this key or project.',
        'მოთხოვნა არ არის დაშვებული. შეამოწმეთ საჯარო API გასაღები, დაშვებული origin და პროექტის მდგომარეობა.' => 'The request is not allowed. Check the public key, allowed origin, and project status.',
        'ავტორიზაცია ვერ შესრულდა. API გასაღები არ არის მითითებული, არასწორია ან ვეღარ გამოიყენება.' => 'Authorization failed. The API key is missing, invalid, or no longer usable.',
        'მოდელის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.' => 'The model id is invalid. Use a positive integer from an API response.',
        'თაობის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.' => 'The generation id is invalid. Use a positive integer from an API response.',
        'მოდიფიკაციის id არასწორია. გამოიყენეთ დადებითი მთელი რიცხვი, რომელიც API-ის პასუხშია.' => 'The modification id is invalid. Use a positive integer from an API response.',
    ];

    public static function localize(string $message): string
    {
        if (!self::english()) {
            return $message;
        }

        return self::EN[$message] ?? $message;
    }

    public static function english(): bool
    {
        $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        if (!is_string($header) || trim($header) === '') {
            return false;
        }
        $first = strtolower(trim(explode(',', $header)[0]));
        $primary = explode('-', explode(';', $first)[0])[0];

        return $primary !== 'ka';
    }
}
