<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Account\IdentitySync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ImportLegacy extends Command
{
    protected $signature = 'geoapi:import-legacy';

    protected $description = 'Import platform users into Electrik and grant existing cars access';

    public function handle(): int
    {
        $users = DB::table('platform.users')->orderBy('id')->get();
        $admins = [];

        foreach ($users as $row) {
            IdentitySync::ensure((int) $row->id);
            $this->grantCars((int) $row->id, (string) $row->status);
            if ((string) $row->role === 'admin') {
                $admins[] = mb_strtolower(trim((string) $row->email));
            }
        }

        $projects = DB::table('platform.projects')->orderBy('id')->get(['id', 'user_id']);
        foreach ($projects as $project) {
            $newUser = DB::table('legacy_id_maps')
                ->where('entity', 'user')
                ->where('old_id', $project->user_id)
                ->value('new_id');
            DB::table('legacy_id_maps')->updateOrInsert(
                ['entity' => 'project', 'old_id' => $project->id],
                ['new_id' => $project->id, 'updated_at' => now(), 'created_at' => now()]
            );
            if (is_numeric($newUser)) {
                $teamId = DB::table('team_user')->where('user_id', $newUser)->orderBy('team_id')->value('team_id');
                if (is_numeric($teamId)) {
                    DB::table('api_entitlements')
                        ->where('legacy_user_id', $project->user_id)
                        ->whereNull('team_id')
                        ->update(['team_id' => $teamId, 'updated_at' => now()]);
                }
            }
        }

        foreach (['api_key', 'widget'] as $entity) {
            $table = $entity === 'api_key' ? 'platform.api_keys' : 'platform.widgets';
            $column = $entity === 'api_key' ? 'id' : 'project_id';
            foreach (DB::table($table)->orderBy($column)->get([$column]) as $row) {
                $id = (int) $row->{$column};
                DB::table('legacy_id_maps')->updateOrInsert(
                    ['entity' => $entity, 'old_id' => $id],
                    ['new_id' => $id, 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }

        if ($admins !== []) {
            $this->writeOperators($admins);
        }

        $this->info('imported users: '.$users->count());

        return self::SUCCESS;
    }

    private function grantCars(int $legacyUserId, string $status): void
    {
        $productId = (int) DB::table('api_products')->where('slug', 'cars')->value('id');
        $planId = (int) DB::table('api_plans')->where('code', 'cars-included')->value('id');
        $existing = DB::table('api_entitlements')
            ->where('legacy_user_id', $legacyUserId)
            ->where('api_product_id', $productId)
            ->first();

        if ($existing !== null && (string) $existing->status === 'cancelled') {
            return;
        }

        $next = $status === 'suspended' ? 'suspended' : 'active';
        if ($existing !== null) {
            if ((string) $existing->source !== 'legacy_grant') {
                return;
            }
            DB::table('api_entitlements')->where('id', $existing->id)->update([
                'status' => $next,
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('api_entitlements')->insert([
            'legacy_user_id' => $legacyUserId,
            'api_product_id' => $productId,
            'api_plan_id' => $planId,
            'status' => $next,
            'starts_at' => now(),
            'source' => 'legacy_grant',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $emails
     */
    private function writeOperators(array $emails): void
    {
        $path = base_path('.env');
        $contents = is_file($path) ? (string) file_get_contents($path) : '';
        $line = 'ELECTRIK_OPERATOR_EMAILS='.implode(',', $emails);
        if (preg_match('/^ELECTRIK_OPERATOR_EMAILS=.*$/m', $contents) === 1) {
            $contents = (string) preg_replace('/^ELECTRIK_OPERATOR_EMAILS=.*$/m', $line, $contents);
        } else {
            $contents .= PHP_EOL.$line.PHP_EOL;
        }
        file_put_contents($path, $contents);
        $this->info('operators updated: '.count($emails));
    }
}
