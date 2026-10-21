<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additive refund total. Does not rewrite quotas or catalog tables.
 * A partial refund keeps the order paid. Only a full refund changes the plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_orders') && ! Schema::hasColumn('billing_orders', 'refunded_gel')) {
            Schema::table('billing_orders', function (Blueprint $table): void {
                $table->decimal('refunded_gel', 12, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('billing_orders') && Schema::hasColumn('billing_orders', 'refunded_gel')) {
            Schema::table('billing_orders', function (Blueprint $table): void {
                $table->dropColumn('refunded_gel');
            });
        }
    }
};
