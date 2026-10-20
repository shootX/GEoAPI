<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coming_soon_signups')) {
            Schema::create('coming_soon_signups', function (Blueprint $table): void {
                $table->id();
                $table->string('email')->unique();
                $table->string('locale', 8)->default('ka');
                $table->timestamps();
            });
        }

        if (! DB::table('settings')->where('key', 'coming_soon')->exists()) {
            DB::table('settings')->insert([
                'key' => 'coming_soon',
                'value' => json_encode(false),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'coming_soon')->delete();
        Schema::dropIfExists('coming_soon_signups');
    }
};
