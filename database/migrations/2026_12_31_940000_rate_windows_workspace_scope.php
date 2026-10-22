<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Billing V2 counts workspace RPM in platform.rate_windows with scope 'workspace'.
 * The original CHECK allows only 'project' and 'ip', so every request from a shared
 * workspace failed with 503. platform.rate_windows is owned by postgres: run this
 * migration as the owner (the app role cannot ALTER it).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('ALTER TABLE platform.rate_windows DROP CONSTRAINT IF EXISTS rate_windows_scope_check');
        DB::statement("ALTER TABLE platform.rate_windows ADD CONSTRAINT rate_windows_scope_check CHECK (scope = ANY (ARRAY['project'::text, 'ip'::text, 'workspace'::text]))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement("DELETE FROM platform.rate_windows WHERE scope = 'workspace'");
        DB::statement('ALTER TABLE platform.rate_windows DROP CONSTRAINT IF EXISTS rate_windows_scope_check');
        DB::statement("ALTER TABLE platform.rate_windows ADD CONSTRAINT rate_windows_scope_check CHECK (scope = ANY (ARRAY['project'::text, 'ip'::text]))");
    }
};
