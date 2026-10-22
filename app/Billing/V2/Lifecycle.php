<?php

declare(strict_types=1);

namespace App\Billing\V2;

use App\Billing\AuditLog;
use Illuminate\Support\Facades\DB;

final class Lifecycle
{
    public static function rollDue(): int
    {
        if (! Workspace::ready()) {
            return 0;
        }
        $now = time();
        $stamp = Workspace::stamp($now);
        $ids = DB::table('billing_workspace_subscriptions')
            ->where('period_end', '<=', $stamp)
            ->pluck('workspace_id');
        $count = 0;
        foreach ($ids as $id) {
            $before = (string) DB::table('billing_workspace_subscriptions')->where('workspace_id', $id)->value('period_end');
            DB::transaction(function () use ($id, $now): void {
                self::rollLocked((int) $id, $now);
            });
            $after = (string) DB::table('billing_workspace_subscriptions')->where('workspace_id', $id)->value('period_end');
            if ($after !== $before) {
                $count++;
            }
        }

        return $count;
    }

    public static function rollLocked(int $workspaceId, int $now): void
    {
        for ($guard = 0; $guard < 36; $guard++) {
            $sub = DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->lockForUpdate()->first();
            if ($sub === null || ! self::step($sub, $now)) {
                return;
            }
        }
    }

    public static function schedule(int $legacyUserId, string $plan): bool
    {
        $workspace = Workspace::find($legacyUserId);
        if ($workspace === null) {
            return false;
        }
        $sub = Workspace::subscription((int) $workspace->id);
        if ($sub === null || ! Flag::is($sub->shared_quota)) {
            return false;
        }
        if ($plan !== 'free' && ! isset(Catalog::plans()[$plan])) {
            return false;
        }
        if (Catalog::rank($plan) >= Catalog::rank((string) $sub->plan_code)) {
            return false;
        }
        DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspace->id)->update([
            'pending_plan_code' => $plan,
            'pending_interval' => 'month',
            'updated_at' => Workspace::stamp(),
        ]);
        AuditLog::write(Workspace::ownerId($legacyUserId), 'v2_downgrade_scheduled', 'workspace', (string) $workspace->id, [
            'plan' => $plan,
            'at' => (string) $sub->term_end,
        ]);

        return true;
    }

    private static function step(object $sub, int $now): bool
    {
        $stamp = Workspace::stamp($now);
        if ((string) $sub->period_end > $stamp) {
            return false;
        }
        if (! Flag::is($sub->shared_quota)) {
            if (Flag::is($sub->legacy_preserved) && (string) $sub->term_end <= $stamp && Flag::on()) {
                self::activate($sub, 'free', 'month', (string) $sub->term_end, true);

                return true;
            }

            return false;
        }
        if ((string) $sub->plan_code !== 'free' && (string) $sub->term_end <= $stamp) {
            $next = is_string($sub->pending_plan_code) && $sub->pending_plan_code !== '' ? (string) $sub->pending_plan_code : 'free';
            $interval = is_string($sub->pending_interval) && $sub->pending_interval !== '' ? (string) $sub->pending_interval : 'month';
            self::activate($sub, $next, $interval, (string) $sub->term_end, true);
            AuditLog::write(null, 'v2_term_ended', 'workspace', (string) $sub->workspace_id, [
                'plan' => $next,
            ]);

            return true;
        }
        $nextEnd = Workspace::plusMonth((string) $sub->period_end);
        if ($nextEnd > (string) $sub->term_end) {
            $nextEnd = (string) $sub->term_end;
        }
        if ($nextEnd <= (string) $sub->period_end) {
            return false;
        }
        DB::table('billing_workspace_subscriptions')->where('id', $sub->id)->update([
            'period_start' => (string) $sub->period_end,
            'period_end' => $nextEnd,
            'term_end' => (string) $sub->plan_code === 'free' ? $nextEnd : (string) $sub->term_end,
            'updated_at' => Workspace::stamp(),
        ]);

        return true;
    }

    public static function activate(object $sub, string $plan, string $interval, string $start, bool $clearPending): void
    {
        $spec = $plan === 'enterprise'
            ? ['quota' => (int) $sub->monthly_quota, 'projects' => (int) $sub->included_projects]
            : Catalog::plans()[$plan];
        $end = Workspace::plusMonth($start);
        $term = $interval === 'year' && $plan !== 'free' ? Workspace::plusYear($start) : $end;
        $rpm = $plan === 'enterprise' ? (int) $sub->rpm : Catalog::rpm($plan);
        DB::table('billing_workspace_subscriptions')->where('id', $sub->id)->update([
            'plan_code' => $plan,
            'interval' => $plan === 'free' ? 'month' : $interval,
            'status' => 'active',
            'monthly_quota' => (int) $spec['quota'],
            'rpm' => $rpm,
            'included_projects' => (int) $spec['projects'],
            'period_start' => $start,
            'period_end' => $end,
            'term_end' => $term,
            'pending_plan_code' => $clearPending ? null : $sub->pending_plan_code,
            'pending_interval' => $clearPending ? null : $sub->pending_interval,
            'legacy_preserved' => false,
            'unlimited' => false,
            'shared_quota' => true,
            'updated_at' => Workspace::stamp(),
        ]);
        Workspace::entitle((int) $sub->workspace_id, $plan, 'renewal', $start, $term);
    }
}
