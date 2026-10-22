<?php

declare(strict_types=1);

namespace App\Billing\V2;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * One successful request. Monthly quota is spent first, then the credit
 * that expires soonest. A project cap never spends the shared quota.
 * The conditional UPDATE is the concurrency gate on PostgreSQL and SQLite.
 */
final class Ledger
{
    /** @return array{ok:bool, reason?:string, limit:int, used:int, remaining:int, reset_at:int, cap?:bool} */
    public static function consume(int $workspaceId, int $projectId, int $now): array
    {
        self::wait();
        $attempts = 0;
        beginning:
        $attempts++;
        try {
            return DB::transaction(function () use ($workspaceId, $projectId, $now): array {
                Lifecycle::rollLocked($workspaceId, $now);
                $sub = DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->lockForUpdate()->first();
                if ($sub === null || ! Flag::is($sub->shared_quota)) {
                    throw new QuotaExceeded;
                }
                $period = (string) $sub->period_start;
                $quota = (int) $sub->monthly_quota;
                $reset = self::unix((string) $sub->period_end);
                self::ensurePeriod($workspaceId, $period);
                self::ensureProject($workspaceId, $projectId, $period);
                $cap = DB::table('billing_project_caps')
                    ->where('workspace_id', $workspaceId)
                    ->where('project_id', $projectId)
                    ->value('monthly_cap');
                $capLimit = $cap === null ? null : (int) $cap;
                if (! self::bumpProject($workspaceId, $projectId, $period, $capLimit)) {
                    return [
                        'ok' => false,
                        'reason' => 'cap',
                        'limit' => $capLimit ?? 0,
                        'used' => $capLimit ?? 0,
                        'remaining' => 0,
                        'reset_at' => $reset,
                        'cap' => true,
                    ];
                }
                if (! self::bumpQuota($workspaceId, $period, $quota)) {
                    if (! self::takeCredit($workspaceId, $now)) {
                        throw new QuotaExceeded;
                    }
                }
                $used = (int) DB::table('billing_period_usage')
                    ->where('workspace_id', $workspaceId)
                    ->where('period_start', $period)
                    ->value('used_count');
                $credits = Workspace::creditRemaining($workspaceId, Workspace::stamp($now));

                return [
                    'ok' => true,
                    'limit' => $quota,
                    'used' => $used,
                    'remaining' => max(0, $quota - $used) + $credits,
                    'reset_at' => $reset,
                ];
            });
        } catch (QuotaExceeded) {
            $sub = Workspace::subscription($workspaceId);
            $quota = $sub === null ? 0 : (int) $sub->monthly_quota;
            $reset = $sub === null ? $now + 60 : self::unix((string) $sub->period_end);

            return [
                'ok' => false,
                'reason' => 'month',
                'limit' => $quota,
                'used' => $quota,
                'remaining' => 0,
                'reset_at' => $reset,
            ];
        } catch (QueryException $e) {
            if ($attempts < 8 && str_contains(strtolower($e->getMessage()), 'lock')) {
                usleep(30000 * $attempts);
                goto beginning;
            }
            throw $e;
        }
    }

    /** @return array{limit:int, used:int, remaining:int, exhausted:bool, reset_at:int, cap?:bool}|null */
    public static function preview(int $workspaceId, int $projectId, int $now): ?array
    {
        DB::transaction(function () use ($workspaceId, $now): void {
            Lifecycle::rollLocked($workspaceId, $now);
        });
        $sub = Workspace::subscription($workspaceId);
        if ($sub === null) {
            return null;
        }
        $quota = (int) $sub->monthly_quota;
        $used = (int) (DB::table('billing_period_usage')
            ->where('workspace_id', $workspaceId)
            ->where('period_start', $sub->period_start)
            ->value('used_count') ?? 0);
        $projectUsed = (int) (DB::table('billing_project_period_usage')
            ->where('workspace_id', $workspaceId)
            ->where('project_id', $projectId)
            ->where('period_start', $sub->period_start)
            ->value('used_count') ?? 0);
        $cap = DB::table('billing_project_caps')
            ->where('workspace_id', $workspaceId)
            ->where('project_id', $projectId)
            ->value('monthly_cap');
        $credits = Workspace::creditRemaining($workspaceId, Workspace::stamp($now));
        $reset = self::unix((string) $sub->period_end);
        if ($cap !== null && $projectUsed >= (int) $cap) {
            return [
                'limit' => (int) $cap,
                'used' => $projectUsed,
                'remaining' => 0,
                'exhausted' => true,
                'reset_at' => $reset,
                'cap' => true,
            ];
        }
        $remaining = max(0, $quota - $used) + $credits;

        return [
            'limit' => $quota,
            'used' => $used,
            'remaining' => $remaining,
            'exhausted' => $remaining <= 0,
            'reset_at' => $reset,
        ];
    }

    private static function ensurePeriod(int $workspaceId, string $period): void
    {
        $now = Workspace::stamp();
        DB::table('billing_period_usage')->insertOrIgnore([
            'workspace_id' => $workspaceId,
            'period_start' => $period,
            'used_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function ensureProject(int $workspaceId, int $projectId, string $period): void
    {
        $now = Workspace::stamp();
        DB::table('billing_project_period_usage')->insertOrIgnore([
            'workspace_id' => $workspaceId,
            'project_id' => $projectId,
            'period_start' => $period,
            'used_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function bumpProject(int $workspaceId, int $projectId, string $period, ?int $cap): bool
    {
        $query = DB::table('billing_project_period_usage')
            ->where('workspace_id', $workspaceId)
            ->where('project_id', $projectId)
            ->where('period_start', $period);
        if ($cap !== null) {
            $query->where('used_count', '<', $cap);
        }

        return $query->update([
            'used_count' => DB::raw('used_count + 1'),
            'updated_at' => Workspace::stamp(),
        ]) === 1;
    }

    private static function bumpQuota(int $workspaceId, string $period, int $quota): bool
    {
        return DB::table('billing_period_usage')
            ->where('workspace_id', $workspaceId)
            ->where('period_start', $period)
            ->where('used_count', '<', $quota)
            ->update([
                'used_count' => DB::raw('used_count + 1'),
                'updated_at' => Workspace::stamp(),
            ]) === 1;
    }

    private static function takeCredit(int $workspaceId, int $now): bool
    {
        $stamp = Workspace::stamp($now);
        for ($i = 0; $i < 3; $i++) {
            $id = DB::table('billing_credit_ledger')
                ->where('workspace_id', $workspaceId)
                ->where('remaining', '>', 0)
                ->where('expires_at', '>', $stamp)
                ->orderBy('expires_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->value('id');
            if (! is_numeric($id)) {
                return false;
            }
            $updated = DB::table('billing_credit_ledger')
                ->where('id', $id)
                ->where('remaining', '>', 0)
                ->update([
                    'remaining' => DB::raw('remaining - 1'),
                    'updated_at' => Workspace::stamp(),
                ]);
            if ($updated === 1) {
                return true;
            }
        }

        return false;
    }

    private static function wait(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }
        $pdo = DB::connection()->getPdo();
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 8);
        $pdo->exec('PRAGMA busy_timeout = 8000');
    }

    private static function unix(string $stamp): int
    {
        $parsed = strtotime($stamp.' UTC');

        return $parsed === false ? time() : $parsed;
    }
}
