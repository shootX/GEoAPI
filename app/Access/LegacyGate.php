<?php

declare(strict_types=1);

namespace App\Access;

final class LegacyGate
{
    /**
     * @return array{status:int,body:array{error:array{code:string,message:string}}}|null
     */
    public static function catalog(int $projectId): ?array
    {
        if (ProductAccess::allowsProject($projectId, 'cars')) {
            return null;
        }

        return [
            'status' => 403,
            'body' => [
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'მოთხოვნა არ არის დაშვებული. მოთხოვნა არ აკმაყოფილებს გასაღების ან პროექტის წვდომის პირობებს.',
                ],
            ],
        ];
    }

    /**
     * @return array{status:int,body:array{error:array{code:string,message:string}}}|null
     */
    public static function issueKey(int $legacyUserId): ?array
    {
        if (ProductAccess::allowsUser($legacyUserId, 'cars')) {
            return null;
        }

        return [
            'status' => 403,
            'body' => [
                'error' => [
                    'code' => 'forbidden',
                    'message' => app()->getLocale() === 'en'
                        ? 'The Cars API is not turned on for this account yet. Turn it on at /account/products, then create the key.'
                        : 'მანქანების API ამ ანგარიშზე ჯერ ჩართული არ არის. ჩართეთ გვერდზე /account/products და შემდეგ შექმენით გასაღები.',
                ],
            ],
        ];
    }
}
