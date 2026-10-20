<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class VerifyImport extends Command
{
    protected $signature = 'carapi:verify-import';

    protected $description = 'Compare legacy platform rows with Electrik identities and entitlements';

    public function handle(): int
    {
        $users = (int) DB::table('platform.users')->count();
        $mapped = (int) DB::table('legacy_id_maps')->where('entity', 'user')->count();
        $linked = (int) DB::table('users')->whereNotNull('legacy_platform_user_id')->count();
        $cars = (int) DB::table('api_entitlements as e')
            ->join('api_products as p', 'p.id', '=', 'e.api_product_id')
            ->where('p.slug', 'cars')
            ->where('e.status', 'active')
            ->count();
        $activeUsers = (int) DB::table('platform.users')->where('status', 'active')->count();
        $projects = (int) DB::table('platform.projects')->count();
        $projectMaps = (int) DB::table('legacy_id_maps')->where('entity', 'project')->where('old_id', '>', 0)->count();
        $keys = (int) DB::table('platform.api_keys')->count();
        $keyMaps = (int) DB::table('legacy_id_maps')->where('entity', 'api_key')->count();
        $drafts = (int) DB::table('api_products')->where('status', 'draft')->count();

        $this->line("users {$users} mapped {$mapped} linked {$linked}");
        $this->line("active_users {$activeUsers} cars_entitlements {$cars}");
        $this->line("projects {$projects} project_maps {$projectMaps}");
        $this->line("keys {$keys} key_maps {$keyMaps}");
        $this->line("draft_products {$drafts}");

        $ok = $users === $mapped && $users === $linked && $cars === $activeUsers
            && $projects === $projectMaps && $keys === $keyMaps && $drafts === 2;

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
