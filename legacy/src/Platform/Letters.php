<?php

declare(strict_types=1);

namespace Geoapi\Platform;

/** Account emails, Georgian first and English below, so every caller sends the same explained text. */
final class Letters
{
    /** @return array{0:string,1:string} subject and text */
    public static function verifyEmail(string $url): array
    {
        return self::both(
            ['ელფოსტის დადასტურება', "დაადასტურეთ GeoAPI ანგარიშის ელფოსტა ამ ბმულით. ბმული 24 საათი მოქმედებს.\n{$url}\n\nთუ GeoAPI-ზე არ დარეგისტრირებულხართ, ეს წერილი უგულებელყავით."],
            ['Confirm your email', "Confirm the email for your GeoAPI account with this link. It works for 24 hours.\n{$url}\n\nIf you did not sign up for GeoAPI, ignore this email."],
        );
    }

    /** @return array{0:string,1:string} */
    public static function resetPassword(string $url): array
    {
        return self::both(
            ['პაროლის აღდგენა', "პაროლის აღდგენის ბმული 30 წუთი მოქმედებს.\n{$url}\n\nთუ აღდგენა თქვენ არ მოგითხოვიათ, ეს წერილი უგულებელყავით."],
            ['Reset your password', "This password reset link works for 30 minutes.\n{$url}\n\nIf you did not ask for a reset, ignore this email."],
        );
    }

    /** @return array{0:string,1:string} */
    public static function passwordChanged(): array
    {
        return self::both(
            ['პაროლი შეიცვალა', 'თქვენი GeoAPI ანგარიშის პაროლი შეიცვალა. თუ ეს ცვლილება თქვენ არ განგიხორციელებიათ, დაგვიკავშირდით: hello@geoapi.ge.'],
            ['Your password was changed', 'The password for your GeoAPI account was changed. If this was not you, contact hello@geoapi.ge.'],
        );
    }

    /**
     * @param array{0:string,1:string} $ka
     * @param array{0:string,1:string} $en
     * @return array{0:string,1:string}
     */
    private static function both(array $ka, array $en): array
    {
        return [$ka[0] . ' / ' . $en[0], $ka[1] . "\n\n---\n\n" . $en[1]];
    }
}
