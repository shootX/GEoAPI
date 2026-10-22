<?php

declare(strict_types=1);

namespace App\Billing\V2;

use App\Billing\AuditLog;
use App\Billing\Plans;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class Checkout
{
    /**
     * @param  array{code:string, rate:string, quantity:int, rated_on:string}  $rate
     * @param  array<string, mixed>  $buyer
     */
    public static function open(int $actorUserId, int $legacyUserId, int $projectId, string $product, string $interval, array $rate, array $buyer): string
    {
        if (! Workspace::ownsProject($legacyUserId, $projectId)) {
            throw new InvalidArgumentException('project');
        }
        if ($product === 'business' && ! Catalog::purchasable('business')) {
            throw new InvalidArgumentException('plan');
        }
        if (! in_array($product, ['extra-project'], true) && ! str_starts_with($product, 'credits:') && ! Catalog::purchasable($product)) {
            throw new InvalidArgumentException('plan');
        }
        if (str_starts_with($product, 'credits:')) {
            $pack = substr($product, 8);
            $current = self::currentPlan($legacyUserId);
            if ($current !== $pack || ! isset(Catalog::packs()[$pack])) {
                throw new InvalidArgumentException('plan');
            }
        }
        if (isset(Catalog::plans()[$product])) {
            $currentPlan = self::sharedPlan($legacyUserId);
            if ($currentPlan !== null && Catalog::rank($product) < Catalog::rank($currentPlan)) {
                throw new InvalidArgumentException('plan');
            }
        }
        $code = Catalog::code($product, $interval);
        $cents = Catalog::usdCents($product, $interval);
        $tetri = Plans::gelTetri($cents, $rate['rate'], $rate['quantity']);
        $publicId = (string) Str::uuid();
        $now = now();
        DB::table('billing_orders')->insert([
            'public_id' => $publicId,
            'user_id' => $actorUserId,
            'legacy_user_id' => $legacyUserId,
            'project_id' => $projectId,
            'plan_code' => $code,
            'interval' => $interval,
            'usd_cents' => $cents,
            'gel_amount' => Plans::formatTetri($tetri),
            'rate' => $rate['rate'],
            'rate_quantity' => $rate['quantity'],
            'rate_date' => $rate['rated_on'],
            'buyer' => json_encode($buyer, JSON_UNESCAPED_UNICODE),
            'previous' => json_encode(self::snapshot($legacyUserId), JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'bog_order_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        AuditLog::write($actorUserId, 'checkout', 'order', $publicId, [
            'plan' => $code,
            'project_id' => $projectId,
            'usd_cents' => $cents,
        ]);

        return $publicId;
    }

    public static function applyPaid(object $order): void
    {
        $parsed = Catalog::parse((string) $order->plan_code);
        $legacyUserId = (int) $order->legacy_user_id;
        $owner = (int) $order->user_id;
        $workspace = Workspace::find($legacyUserId);
        if ($workspace === null) {
            $free = Catalog::plans()['free'];
            $now = Workspace::stamp();
            $id = Workspace::create($owner, $legacyUserId, 'checkout', [
                'plan_code' => 'free',
                'monthly_quota' => $free['quota'],
                'rpm' => $free['rpm'],
                'included_projects' => $free['projects'],
                'period_start' => $now,
                'period_end' => Workspace::plusMonth($now),
                'term_end' => Workspace::plusMonth($now),
                'shared_quota' => true,
                'source' => 'checkout',
            ]);
            $workspace = Workspace::find($legacyUserId);
            unset($id);
        }
        if ($workspace === null) {
            return;
        }
        $workspaceId = (int) $workspace->id;
        if ($parsed['kind'] === 'credits') {
            self::grantCredits($workspaceId, (int) $order->id, $parsed['plan']);

            return;
        }
        if ($parsed['kind'] === 'extra-project') {
            self::grantExtra($workspaceId, (int) $order->id);

            return;
        }
        self::grantPlan($workspaceId, $parsed['plan'], $parsed['interval']);
    }

    public static function reverse(object $order): void
    {
        $parsed = Catalog::parse((string) $order->plan_code);
        $workspace = Workspace::find((int) $order->legacy_user_id);
        if ($workspace === null) {
            return;
        }
        $workspaceId = (int) $workspace->id;
        if ($parsed['kind'] === 'credits') {
            DB::table('billing_credit_ledger')->where('billing_order_id', $order->id)->update([
                'remaining' => 0,
                'updated_at' => Workspace::stamp(),
            ]);

            return;
        }
        if ($parsed['kind'] === 'extra-project') {
            DB::table('billing_project_addons')->where('billing_order_id', $order->id)->update([
                'status' => 'refunded',
                'updated_at' => Workspace::stamp(),
            ]);

            return;
        }
        $previous = json_decode((string) $order->previous, true);
        $saved = is_array($previous) ? ($previous['subscription'] ?? null) : null;
        if (! is_array($saved)) {
            $sub = Workspace::subscription($workspaceId);
            if ($sub !== null) {
                Lifecycle::activate($sub, 'free', 'month', Workspace::stamp(), true);
            }

            return;
        }
        DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->update([
            'plan_code' => $saved['plan_code'],
            'interval' => $saved['interval'],
            'status' => $saved['status'] ?? 'active',
            'monthly_quota' => (int) $saved['monthly_quota'],
            'rpm' => (int) $saved['rpm'],
            'included_projects' => (int) $saved['included_projects'],
            'period_start' => $saved['period_start'],
            'period_end' => $saved['period_end'],
            'term_end' => $saved['term_end'],
            'pending_plan_code' => $saved['pending_plan_code'],
            'pending_interval' => $saved['pending_interval'],
            'legacy_preserved' => Flag::is($saved['legacy_preserved'] ?? false),
            'grandfathered' => Flag::is($saved['grandfathered'] ?? false),
            'unlimited' => Flag::is($saved['unlimited'] ?? false),
            'shared_quota' => Flag::is($saved['shared_quota'] ?? false),
            'updated_at' => Workspace::stamp(),
        ]);
        Workspace::entitle($workspaceId, (string) $saved['plan_code'], 'refund', Workspace::stamp(), (string) $saved['term_end']);
    }

    /** @return array{v2:bool, subscription:?array<string, mixed>} */
    public static function snapshot(int $legacyUserId): array
    {
        $workspace = Workspace::find($legacyUserId);
        if ($workspace === null) {
            return ['v2' => true, 'subscription' => null];
        }
        $sub = Workspace::subscription((int) $workspace->id);
        if ($sub === null) {
            return ['v2' => true, 'subscription' => null];
        }

        return ['v2' => true, 'subscription' => (array) $sub];
    }

    private static function grantPlan(int $workspaceId, string $plan, string $interval): void
    {
        $sub = Workspace::subscription($workspaceId);
        $spec = Catalog::plans()[$plan];
        $now = Workspace::stamp();
        if ($sub !== null && Flag::is($sub->shared_quota) && ! Flag::is($sub->legacy_preserved) && Catalog::rank($plan) > Catalog::rank((string) $sub->plan_code)) {
            $term = (string) $sub->term_end;
            if ($interval === 'year') {
                $extended = Workspace::plusYear($now);
                if ($extended > $term) {
                    $term = $extended;
                }
            }
            DB::table('billing_workspace_subscriptions')->where('id', $sub->id)->update([
                'plan_code' => $plan,
                'interval' => $interval,
                'monthly_quota' => $spec['quota'],
                'rpm' => Catalog::rpm($plan),
                'included_projects' => $spec['projects'],
                'term_end' => $term,
                'pending_plan_code' => null,
                'pending_interval' => null,
                'unlimited' => false,
                'shared_quota' => true,
                'legacy_preserved' => false,
                'updated_at' => $now,
            ]);
            Workspace::entitle($workspaceId, $plan, 'upgrade', (string) $sub->period_start, $term);

            return;
        }
        if ($sub !== null && Flag::is($sub->shared_quota) && (string) $sub->plan_code === $plan) {
            $base = (string) $sub->term_end > $now ? (string) $sub->term_end : $now;
            $term = $interval === 'year' ? Workspace::plusYear($base) : Workspace::plusMonth($base);
            DB::table('billing_workspace_subscriptions')->where('id', $sub->id)->update([
                'interval' => $interval,
                'term_end' => $term,
                'updated_at' => $now,
            ]);

            return;
        }
        $end = Workspace::plusMonth($now);
        $term = $interval === 'year' ? Workspace::plusYear($now) : $end;
        DB::table('billing_workspace_subscriptions')->where('workspace_id', $workspaceId)->update([
            'plan_code' => $plan,
            'interval' => $interval,
            'status' => 'active',
            'monthly_quota' => $spec['quota'],
            'rpm' => Catalog::rpm($plan),
            'included_projects' => $spec['projects'],
            'period_start' => $now,
            'period_end' => $end,
            'term_end' => $term,
            'pending_plan_code' => null,
            'pending_interval' => null,
            'legacy_preserved' => false,
            'unlimited' => false,
            'shared_quota' => true,
            'activated_at' => $now,
            'updated_at' => $now,
        ]);
        Workspace::entitle($workspaceId, $plan, 'purchase', $now, $term);
    }

    private static function grantCredits(int $workspaceId, int $orderId, string $pack): void
    {
        $spec = Catalog::packs()[$pack];
        $now = Workspace::stamp();
        DB::table('billing_credit_ledger')->insert([
            'workspace_id' => $workspaceId,
            'billing_order_id' => $orderId,
            'pack_code' => $pack,
            'granted' => $spec['requests'],
            'remaining' => $spec['requests'],
            'expires_at' => CarbonImmutable::parse($now, 'UTC')->addDays(Catalog::CREDIT_DAYS)->utc()->format('Y-m-d H:i:s'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function grantExtra(int $workspaceId, int $orderId): void
    {
        $sub = Workspace::subscription($workspaceId);
        $now = Workspace::stamp();
        $end = $sub !== null && (string) $sub->period_end > $now ? (string) $sub->period_end : Workspace::plusMonth($now);
        DB::table('billing_project_addons')->insert([
            'workspace_id' => $workspaceId,
            'billing_order_id' => $orderId,
            'quantity' => 1,
            'starts_at' => $now,
            'ends_at' => $end,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private static function currentPlan(int $legacyUserId): ?string
    {
        $plan = self::sharedPlan($legacyUserId);

        return $plan !== null && isset(Catalog::packs()[$plan]) ? $plan : null;
    }

    private static function sharedPlan(int $legacyUserId): ?string
    {
        $workspace = Workspace::find($legacyUserId);
        if ($workspace === null) {
            return null;
        }
        $sub = Workspace::subscription((int) $workspace->id);
        if ($sub === null || ! Flag::is($sub->shared_quota) || Flag::is($sub->legacy_preserved)) {
            return null;
        }

        return (string) $sub->plan_code;
    }
}
