<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/*
 * One account per mailbox regardless of letter case (QA GEO-QA-001). PostgreSQL compares
 * text case-sensitively, so users.email UNIQUE let OPS@… sit beside ops@…. If such duplicates
 * already exist the index is not created (the deploy must not fail): resolve them, then re-run
 * this migration with `php artisan migrate:refresh --path=` on this file.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        $duplicates = DB::select('SELECT lower(email) AS email FROM users GROUP BY lower(email) HAVING count(*) > 1');
        if ($duplicates !== []) {
            Log::warning('users.email has case-variant duplicates; users_email_lower_unique not created', ['count' => count($duplicates)]);

            return;
        }
        DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS users_email_lower_unique ON users (lower(email))');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('DROP INDEX IF EXISTS users_email_lower_unique');
    }
};
