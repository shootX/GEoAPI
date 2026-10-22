<?php

declare(strict_types=1);

namespace App\Billing\V2;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class Gate
{
    public static function prepareForCreate(int $legacyUserId): void
    {
        if (! Flag::on() || ! Workspace::ready() || Workspace::find($legacyUserId) !== null) {
            return;
        }
        if (self::grandfathered($legacyUserId) || self::hasActiveLegacyPaid($legacyUserId)) {
            return;
        }
        $owner = Workspace::ownerId($legacyUserId);
        if ($owner === null) {
            return;
        }
        $now = Workspace::stamp();
        $free = Catalog::plans()['free'];
        Workspace::create($owner, $legacyUserId, 'signup', [
            'plan_code' => 'free',
            'interval' => 'month',
            'monthly_quota' => $free['quota'],
            'rpm' => $free['rpm'],
            'included_projects' => $free['projects'],
            'period_start' => $now,
            'period_end' => Workspace::plusMonth($now),
            'term_end' => Workspace::plusMonth($now),
            'shared_quota' => true,
            'source' => 'signup',
        ]);
    }

    public static function blockCreate(int $legacyUserId): ?string
    {
        if (! Flag::on() || ! Workspace::ready()) {
            return null;
        }
        $workspace = Workspace::find($legacyUserId);
        if ($workspace === null) {
            return null;
        }
        $sub = Workspace::subscription((int) $workspace->id);
        if ($sub === null || ! Flag::is($sub->shared_quota) || Flag::is($sub->legacy_preserved)) {
            return null;
        }
        $now = Workspace::stamp();
        $allowed = Workspace::allowedProjects($sub, (int) $workspace->id, $now);
        if (Workspace::activeProjects($legacyUserId) < $allowed) {
            return null;
        }
        $en = function_exists('app') && app()->getLocale() === 'en';

        return $en
            ? 'This workspace has no free project slot. An extra active project is 5 USD a month.'
            : 'ამ სამუშაო სივრცეს თავისუფალი პროექტის ადგილი არ აქვს. დამატებითი აქტიური პროექტი თვეში 5 USD ღირს.';
    }

    public static function setCap(int $legacyUserId, int $projectId, ?int $cap): bool
    {
        if (! Flag::on() || ! Workspace::ownsProject($legacyUserId, $projectId)) {
            return false;
        }
        $workspace = Workspace::find($legacyUserId);
        if ($workspace === null) {
            return false;
        }
        $sub = Workspace::subscription((int) $workspace->id);
        if ($sub === null || ! Flag::is($sub->shared_quota)) {
            return false;
        }
        if ($cap !== null && ($cap < 1 || $cap > (int) $sub->monthly_quota)) {
            return false;
        }
        $now = Workspace::stamp();
        if ($cap === null) {
            DB::table('billing_project_caps')
                ->where('workspace_id', $workspace->id)
                ->where('project_id', $projectId)
                ->delete();

            return true;
        }
        DB::table('billing_project_caps')->updateOrInsert(
            ['workspace_id' => (int) $workspace->id, 'project_id' => $projectId],
            ['monthly_cap' => $cap, 'created_at' => $now, 'updated_at' => $now],
        );

        return true;
    }

    private static function grandfathered(int $legacyUserId): bool
    {
        try {
            $row = DB::table('platform.users')->where('id', $legacyUserId)->first();
        } catch (QueryException) {
            return true;
        }
        if ($row === null) {
            return true;
        }
        if (! property_exists($row, 'grandfathered')) {
            return true;
        }

        return Flag::is($row->grandfathered);
    }

    private static function hasActiveLegacyPaid(int $legacyUserId): bool
    {
        try {
            return DB::table('platform.project_limits as l')
                ->join('platform.projects as p', 'p.id', '=', 'l.project_id')
                ->where('p.user_id', $legacyUserId)
                ->whereIn('l.plan_code', ['basic', 'premium', 'basic-year', 'premium-year'])
                ->where('l.plan_ends_at', '>', Workspace::stamp())
                ->count() > 0;
        } catch (QueryException) {
            return false;
        }
    }
}
