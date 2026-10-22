<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('billing_orders') && ! Schema::hasColumn('billing_orders', 'provider')) {
            Schema::table('billing_orders', function (Blueprint $table): void {
                $table->string('provider', 16)->default('bog');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('billing_orders') && Schema::hasColumn('billing_orders', 'provider')) {
            Schema::table('billing_orders', function (Blueprint $table): void {
                $table->dropColumn('provider');
            });
        }
    }
};
