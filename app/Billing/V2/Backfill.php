<?php

declare(strict_types=1);

namespace App\Billing\V2;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Insert-only copy of existing rights into workspace rows.
 * It does not UPDATE platform.project_limits, monthly_usage, keys, or orders.
 * --rollback deletes only origin=backfill workspaces that have no paid v2 order.
 */
final class Backfill
{
    /** @return array{ok:bool, dry_run:bool, rollback:bool, created:int, skipped:int, grandfathered:int, preserved:int, removed:int, message?:string} */
    public static function run(bool $dryRun, bool $rollback): array
    {
        $result = [
            'ok' => true,
            'dry_run' => $dryRun,
            'rollback' => $rollback,
            'created' => 0,
            'skipped' => 0,
            'grandfathered' => 0,
            'preserved' => 0,
            'removed' => 0,
        ];
        if ($dryRun && $rollback) {
            $result['ok'] = false;
            $result['message'] = 'Choose either --dry-run or --rollback.';

            return $result;
        }
        if (! Workspace::ready()) {
            $result['ok'] = false;
            $result['message'] = 'billing_workspaces is missing. Run the additive migration first.';

            return $result;
        }
        if ($rollback) {
            $result['removed'] = self::rollback();

            return $result;
        }
        try {
            $users = DB::table('platform.users')->get();
        } catch (QueryException) {
            $result['ok'] = false;
            $result['message'] = 'platform.users is not visible on this connection.';

            return $result;
        }
        foreach ($users as $user) {
            $legacyId = (int) $user->id;
            $owner = Workspace::ownerId($legacyId);
            if ($owner === null || Workspace::find($legacyId) !== null) {
                $result['skipped']++;

                continue;
            }
            $plan = self::preservedPaid($legacyId);
            $grandfathered = property_exists($user, 'grandfathered') ? Flag::is($user->grandfathered) : true;
            if ($plan !== null) {
                $result['preserved']++;
            } elseif ($grandfathered) {
                $result['grandfathered']++;
            } else {
                $result['skipped']++;

                continue;
            }
            $result['created']++;
            if ($dryRun) {
                continue;
            }
            $now = Workspace::stamp();
            if ($plan !== null) {
                Workspace::create($owner, $legacyId, 'backfill', [
                    'plan_code' => $plan['code'],
                    'interval' => str_ends_with($plan['code'], '-year') ? 'year' : 'month',
                    'monthly_quota' => $plan['quota'],
                    'rpm' => 60,
                    'included_projects' => 100,
                    'period_start' => $now,
                    'period_end' => $plan['ends'],
                    'term_end' => $plan['ends'],
                    'legacy_preserved' => true,
                    'grandfathered' => $grandfathered,
                    'unlimited' => $plan['unlimited'],
                    'shared_quota' => false,
                    'source' => 'backfill',
                ]);

                continue;
            }
            Workspace::create($owner, $legacyId, 'backfill', [
                'plan_code' => 'legacy-grandfathered',
                'interval' => 'month',
                'monthly_quota' => 10000,
                'rpm' => 60,
                'included_projects' => 100,
                'period_start' => $now,
                'period_end' => Workspace::plusMonth($now),
                'term_end' => Workspace::plusMonth($now),
                'legacy_preserved' => false,
                'grandfathered' => true,
                'unlimited' => false,
                'shared_quota' => false,
                'source' => 'backfill',
            ]);
        }

        return $result;
    }

    private static function rollback(): int
    {
        $rows = DB::table('billing_workspaces')->where('origin', 'backfill')->get();
        $removed = 0;
        foreach ($rows as $row) {
            $paid = DB::table('billing_orders')
                ->where('legacy_user_id', $row->legacy_user_id)
                ->where('plan_code', 'like', 'v2:%')
                ->where('status', 'paid')
                ->exists();
            if ($paid) {
                continue;
            }
            DB::table('billing_workspaces')->where('id', $row->id)->delete();
            $removed++;
        }

        return $removed;
    }

    /** @return array{code:string, quota:int, ends:string, unlimited:bool}|null */
    private static function preservedPaid(int $legacyUserId): ?array
    {
        try {
            $row = DB::table('platform.project_limits as l')
                ->join('platform.projects as p', 'p.id', '=', 'l.project_id')
                ->where('p.user_id', $legacyUserId)
                ->whereIn('l.plan_code', ['basic', 'premium', 'basic-year', 'premium-year'])
                ->where('l.plan_ends_at', '>', Workspace::stamp())
                ->orderByDesc('l.plan_ends_at')
                ->first(['l.plan_code', 'l.monthly_quota', 'l.plan_ends_at', 'l.unlimited_month']);
        } catch (QueryException) {
            return null;
        }
        if ($row === null) {
            return null;
        }

        return [
            'code' => (string) $row->plan_code,
            'quota' => (int) $row->monthly_quota,
            'ends' => (string) $row->plan_ends_at,
            'unlimited' => Flag::is($row->unlimited_month ?? false),
        ];
    }
}
