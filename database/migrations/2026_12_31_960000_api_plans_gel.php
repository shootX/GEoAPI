<?php

declare(strict_types=1);

use App\Billing\Plans;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * 2026_12_31_910000_billing_bog seeded the Cars plans in USD at the old prices.
 * GeoAPI charges only in GEL (App\Billing\Plans). Rows an operator already moved
 * to GEL are left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_plans')) {
            return;
        }
        $prices = [
            'free' => null,
            'basic' => Plans::tetri('basic', 'month'),
            'basic-year' => Plans::tetri('basic', 'year'),
            'premium' => Plans::tetri('premium', 'month'),
            'premium-year' => Plans::tetri('premium', 'year'),
        ];
        foreach ($prices as $code => $tetri) {
            DB::table('api_plans')
                ->where('code', $code)
                ->where('currency', 'USD')
                ->update(['currency' => 'GEL', 'price_minor' => $tetri, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The USD prices were wrong; they are not restored.
    }
};
