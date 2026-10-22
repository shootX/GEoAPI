<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** QA retest M5: 419, 429, 500 and 503 showed Laravel's plain English pages. */
final class BrandedErrorPagesTest extends TestCase
{
    /** @return array<string, array{int, string}> */
    public static function codes(): array
    {
        return [
            '419' => [419, 'გვერდის ვადა ამოიწურა'],
            '429' => [429, 'ძალიან ბევრი მოთხოვნა'],
            '500' => [500, 'სერვერის შეცდომა'],
            '503' => [503, 'სერვისი დროებით მიუწვდომელია'],
        ];
    }

    #[DataProvider('codes')]
    public function test_the_error_page_is_branded_and_georgian(int $code, string $text): void
    {
        config(['app.debug' => false]);
        Route::get('/_error/{code}', static fn (string $code) => abort((int) $code));

        $this->withSession(['locale' => 'ka'])->get('/_error/'.$code)
            ->assertStatus($code)
            ->assertSee('geoapi-lockup-light.svg', false)
            ->assertSee($text);
    }
}
