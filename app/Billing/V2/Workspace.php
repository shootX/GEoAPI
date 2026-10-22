<?php

declare(strict_types=1);

namespace App\Billing\V2;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDOException;

final class Workspace
{
    public static function find(int $legacyUserId): ?object
    {
        if (! self::ready()) {
            return null;
        }

        return DB::table('billing_workspaces')->where('legacy_user_id', $legacyUserId)->first();
    }

    public static function subscription(int $workspaceId): ?object
    {
        return DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->first();
    }

    public static function forProject(int $projectId): ?object
    {
        if (! self::ready()) {
            return null;
        }
        try {
            $legacyUserId = DB::table('platform.projects')->where('id', $projectId)->value('user_id');
        } catch (PDOException) {
            return null;
        }
        if (! is_numeric($legacyUserId)) {
            return null;
        }
        $workspace = self::find((int) $legacyUserId);
        if ($workspace === null) {
            return null;
        }
        $subscription = self::subscription((int) $workspace->id);
        if ($subscription === null) {
            return null;
        }
        $workspace->subscription = $subscription;

        return $workspace;
    }

    public static function shared(int $projectId): ?object
    {
        $workspace = self::forProject($projectId);
        if ($workspace === null) {
            return null;
        }
        $subscription = $workspace->subscription;
        if (! Flag::is($subscription->shared_quota) || Flag::is($subscription->legacy_preserved)) {
            return null;
        }

        return $workspace;
    }

    /** @param  array<string, mixed>  $values */
    public static function create(int $ownerUserId, int $legacyUserId, string $origin, array $values): int
    {
        $now = self::stamp();
        $id = DB::table('billing_workspaces')->insertGetId([
            'public_id' => (string) Str::uuid(),
            'owner_user_id' => $ownerUserId,
            'legacy_user_id' => $legacyUserId,
            'origin' => $origin,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        self::insertSubscription((int) $id, $values, $now);

        return (int) $id;
    }

    /** @param  array<string, mixed>  $values */
    public static function insertSubscription(int $workspaceId, array $values, ?string $now = null): void
    {
        $now ??= self::stamp();
        $start = (string) ($values['period_start'] ?? $now);
        $end = (string) ($values['period_end'] ?? self::plusMonth($start));
        $term = (string) ($values['term_end'] ?? $end);
        DB::table('billing_workspace_subscriptions')->insert([
            'workspace_id' => $workspaceId,
            'plan_code' => (string) $values['plan_code'],
            'interval' => (string) ($values['interval'] ?? 'month'),
            'status' => 'active',
            'monthly_quota' => (int) $values['monthly_quota'],
            'rpm' => (int) $values['rpm'],
            'included_projects' => (int) $values['included_projects'],
            'period_start' => $start,
            'period_end' => $end,
            'term_end' => $term,
            'pending_plan_code' => $values['pending_plan_code'] ?? null,
            'pending_interval' => $values['pending_interval'] ?? null,
            'legacy_preserved' => (bool) ($values['legacy_preserved'] ?? false),
            'grandfathered' => (bool) ($values['grandfathered'] ?? false),
            'unlimited' => (bool) ($values['unlimited'] ?? false),
            'shared_quota' => (bool) ($values['shared_quota'] ?? false),
            'activated_at' => (string) ($values['activated_at'] ?? $now),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        self::entitle($workspaceId, (string) $values['plan_code'], (string) ($values['source'] ?? 'billing'), $start, $term);
    }

    public static function entitle(int $workspaceId, string $plan, string $source, string $starts, ?string $ends): void
    {
        $now = self::stamp();
        DB::table('billing_workspace_entitlements')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->update(['status' => 'ended', 'ends_at' => $now, 'updated_at' => $now]);
        DB::table('billing_workspace_entitlements')->insert([
            'workspace_id' => $workspaceId,
            'plan_code' => $plan,
            'status' => 'active',
            'starts_at' => $starts,
            'ends_at' => $ends,
            'source' => $source,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public static function ownerId(int $legacyUserId): ?int
    {
        $id = DB::table('users')->where('legacy_platform_user_id', $legacyUserId)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public static function activeProjects(int $legacyUserId): int
    {
        try {
            return (int) DB::table('platform.projects')
                ->where('user_id', $legacyUserId)
                ->where('status', 'active')
                ->count();
        } catch (PDOException) {
            return (int) DB::table('platform.projects')->where('user_id', $legacyUserId)->count();
        }
    }

    public static function ownsProject(int $legacyUserId, int $projectId): bool
    {
        try {
            $owner = DB::table('platform.projects')->where('id', $projectId)->value('user_id');
        } catch (PDOException) {
            return false;
        }

        return is_numeric($owner) && (int) $owner === $legacyUserId;
    }

    public static function extraSlots(int $workspaceId, string $now): int
    {
        return (int) DB::table('billing_project_addons')
            ->where('workspace_id', $workspaceId)
            ->where('status', 'active')
            ->where('ends_at', '>', $now)
            ->sum('quantity');
    }

    public static function allowedProjects(object $subscription, int $workspaceId, string $now): int
    {
        return (int) $subscription->included_projects + self::extraSlots($workspaceId, $now);
    }

    /** @return array<string, mixed>|null */
    public static function present(int $legacyUserId): ?array
    {
        if (! Flag::on() || ! self::ready()) {
            return null;
        }
        $workspace = self::find($legacyUserId);
        if ($workspace === null) {
            return [
                'shared' => false,
                'label' => 'Free',
                'active' => self::activeProjects($legacyUserId),
                'allowed' => 1,
                'used' => 0,
                'quota' => 1000,
                'remaining' => 1000,
                'credits' => 0,
                'rpm' => 15,
                'projects' => [],
                'credit_rows' => [],
                'pending' => null,
                'period_end' => null,
                'term_end' => null,
                'plan' => null,
            ];
        }
        $subscription = self::subscription((int) $workspace->id);
        if ($subscription === null || ! Flag::is($subscription->shared_quota) || Flag::is($subscription->legacy_preserved)) {
            return null;
        }
        $now = time();
        Lifecycle::rollLocked((int) $workspace->id, $now);
        $subscription = self::subscription((int) $workspace->id);
        if ($subscription === null) {
            return null;
        }
        $used = (int) (DB::table('billing_period_usage')
            ->where('workspace_id', $workspace->id)
            ->where('period_start', $subscription->period_start)
            ->value('used_count') ?? 0);
        $credits = self::creditRemaining((int) $workspace->id, self::stamp($now));
        $quota = (int) $subscription->monthly_quota;
        $rows = DB::table('billing_project_period_usage')
            ->where('workspace_id', $workspace->id)
            ->where('period_start', $subscription->period_start)
            ->get();
        $caps = DB::table('billing_project_caps')->where('workspace_id', $workspace->id)->get()->keyBy('project_id');
        $projects = [];
        foreach ($rows as $row) {
            $cap = $caps->get($row->project_id);
            $projects[(int) $row->project_id] = [
                'used' => (int) $row->used_count,
                'cap' => $cap !== null && $cap->monthly_cap !== null ? (int) $cap->monthly_cap : null,
            ];
        }
        $plan = (string) $subscription->plan_code;

        return [
            'shared' => true,
            'label' => ucfirst($plan === 'legacy-grandfathered' ? 'free' : $plan),
            'plan' => $plan,
            'active' => self::activeProjects($legacyUserId),
            'allowed' => self::allowedProjects($subscription, (int) $workspace->id, self::stamp($now)),
            'used' => $used,
            'quota' => $quota,
            'remaining' => max(0, $quota - $used) + $credits,
            'credits' => $credits,
            'rpm' => (int) $subscription->rpm,
            'projects' => $projects,
            'credit_rows' => DB::table('billing_credit_ledger')
                ->where('workspace_id', $workspace->id)
                ->where('remaining', '>', 0)
                ->orderBy('expires_at')
                ->get(['pack_code', 'remaining', 'expires_at'])
                ->all(),
            'pending' => $subscription->pending_plan_code !== null ? (string) $subscription->pending_plan_code : null,
            'period_end' => (string) $subscription->period_end,
            'term_end' => (string) $subscription->term_end,
            'workspace_id' => (int) $workspace->id,
        ];
    }

    public static function creditRemaining(int $workspaceId, string $now): int
    {
        return (int) DB::table('billing_credit_ledger')
            ->where('workspace_id', $workspaceId)
            ->where('remaining', '>', 0)
            ->where('expires_at', '>', $now)
            ->sum('remaining');
    }

    public static function ready(): bool
    {
        try {
            return Schema::hasTable('billing_workspaces');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function stamp(?int $now = null): string
    {
        return gmdate('Y-m-d H:i:s', $now ?? time());
    }

    public static function plusMonth(string $from): string
    {
        return CarbonImmutable::parse($from, 'UTC')->addMonth()->utc()->format('Y-m-d H:i:s');
    }

    public static function plusYear(string $from): string
    {
        return CarbonImmutable::parse($from, 'UTC')->addYear()->utc()->format('Y-m-d H:i:s');
    }
}
