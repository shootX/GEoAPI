<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\V2\Checkout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDOException;

/**
 * A paid callback grants one project. A full refund restores the quota stored on the order.
 * A partial refund records refunded_gel and leaves the paid plan in place.
 * Expiry drops that project to the free monthly cap and keeps catalog access.
 */
final class Entitlements
{
    /** @return array{monthly_quota:int, plan_code:?string, unlimited:bool, plan_ends_at:?string, entitlement_plan_id:?int} */
    public static function snapshot(int $legacyUserId, int $projectId): array
    {
        $row = self::limits($projectId);
        $productId = self::carsProductId();
        $entitlementPlan = null;
        if ($productId !== null) {
            $entitlementPlan = DB::table('api_entitlements')
                ->where('legacy_user_id', $legacyUserId)
                ->where('api_product_id', $productId)
                ->value('api_plan_id');
        }

        return [
            'monthly_quota' => $row['monthly_quota'],
            'plan_code' => $row['plan_code'],
            'unlimited' => $row['unlimited'],
            'plan_ends_at' => $row['plan_ends_at'],
            'entitlement_plan_id' => is_numeric($entitlementPlan) ? (int) $entitlementPlan : null,
        ];
    }

    public static function chooseFree(int $actorUserId, int $legacyUserId, int $projectId): void
    {
        $previous = self::snapshot($legacyUserId, $projectId);
        self::apply($projectId, Plans::FREE, null, false);
        self::setEntitlement($legacyUserId, Plans::FREE, null, 'billing');
        AuditLog::write($actorUserId, 'choose_free', 'project', (string) $projectId, [
            'plan' => Plans::FREE,
            'previous_quota' => $previous['monthly_quota'],
        ]);
    }

    /**
     * @param  array{code:string, rate:string, quantity:int, rated_on:string}  $rate
     * @param  array<string, mixed>  $buyer
     */
    public static function openOrder(int $actorUserId, int $legacyUserId, int $projectId, string $plan, string $interval, array $rate, array $buyer): string
    {
        $code = Plans::code($plan, $interval);
        $cents = Plans::usdCents($plan, $interval);
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
            'previous' => json_encode(self::snapshot($legacyUserId, $projectId), JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'bog_order_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        AuditLog::write($actorUserId, 'checkout', 'order', $publicId, [
            'plan' => $code,
            'project_id' => $projectId,
            'usd_cents' => $cents,
            'gel' => Plans::formatTetri($tetri),
            'rate_date' => $rate['rated_on'],
        ]);

        return $publicId;
    }

    public static function attachBogOrder(string $publicId, string $bogOrderId): void
    {
        DB::table('billing_orders')->where('public_id', $publicId)->update([
            'bog_order_id' => $bogOrderId,
            'updated_at' => now(),
        ]);
    }

    public static function failOrder(string $publicId): void
    {
        DB::table('billing_orders')->where('public_id', $publicId)->where('status', 'pending')->update([
            'status' => 'failed',
            'updated_at' => now(),
        ]);
    }

    public static function grant(string $publicId): void
    {
        $order = DB::table('billing_orders')->where('public_id', $publicId)->first();
        if ($order === null || in_array((string) $order->status, ['paid', 'refunded'], true)) {
            return;
        }
        $ends = (string) $order->interval === 'year' ? now()->addYear() : now()->addMonth();
        $endsAt = $ends->utc()->format('Y-m-d H:i:s');
        if (str_starts_with((string) $order->plan_code, 'v2:')) {
            Checkout::applyPaid($order);
        } else {
            self::apply((int) $order->project_id, (string) $order->plan_code, $endsAt, Plans::unlimited((string) $order->plan_code));
            self::setEntitlement((int) $order->legacy_user_id, (string) $order->plan_code, null, MockPay::isMock($order) ? MockPay::PROVIDER : 'bog');
        }
        DB::table('billing_orders')->where('id', $order->id)->update([
            'status' => 'paid',
            'updated_at' => now(),
        ]);
        self::invoice($order, $endsAt);
        AuditLog::write((int) $order->user_id, 'grant', 'order', (string) $order->public_id, [
            'plan' => (string) $order->plan_code,
            'project_id' => (int) $order->project_id,
            'ends_at' => $endsAt,
        ]);
    }

    public static function refund(string $publicId, ?string $amount = null): void
    {
        $order = DB::table('billing_orders')->where('public_id', $publicId)->first();
        if ($order === null || (string) $order->status === 'refunded') {
            return;
        }
        $previous = self::jsonArray($order->previous);
        if ((string) $order->status === 'paid') {
            if (str_starts_with((string) $order->plan_code, 'v2:')) {
                Checkout::reverse($order);
            } else {
                self::restore((int) $order->project_id, $previous);
                $planId = $previous['entitlement_plan_id'] ?? null;
                if (is_numeric($planId)) {
                    self::setEntitlementById((int) $order->legacy_user_id, (int) $planId, 'bog-refund');
                } else {
                    $code = is_string($previous['plan_code'] ?? null) && $previous['plan_code'] !== ''
                        ? (string) $previous['plan_code']
                        : 'cars-included';
                    if ($code === 'cars-included') {
                        $included = DB::table('api_plans')->where('code', 'cars-included')->value('id');
                        if (is_numeric($included)) {
                            self::setEntitlementById((int) $order->legacy_user_id, (int) $included, 'bog-refund');
                        }
                    } else {
                        self::setEntitlement((int) $order->legacy_user_id, $code, null, 'bog-refund');
                    }
                }
            }
        }
        $recorded = $amount ?? self::money($order->gel_amount);
        DB::table('billing_orders')->where('id', $order->id)->update([
            'status' => 'refunded',
            'refunded_gel' => $recorded,
            'updated_at' => now(),
        ]);
        AuditLog::write((int) $order->user_id, 'refund', 'order', (string) $order->public_id, [
            'plan' => (string) $order->plan_code,
            'project_id' => (int) $order->project_id,
            'restored_quota' => $previous['monthly_quota'] ?? null,
            'refunded_gel' => $recorded,
        ]);
    }

    /**
     * Record a partial refund total without restoring the paid plan.
     * The order stays paid when it was paid, so a later completed callback cannot grant twice.
     */
    public static function recordPartialRefund(string $publicId, string $amount): bool
    {
        $order = DB::table('billing_orders')->where('public_id', $publicId)->first();
        if ($order === null) {
            return false;
        }
        if ((string) $order->status === 'refunded') {
            return true;
        }
        // BOG purchase_units.refund_amount is the order's refunded total so far, not the increment.
        // Keep the largest total seen so replays and out-of-order callbacks never double count.
        $gel = self::money($order->gel_amount);
        $current = self::money($order->refunded_gel ?? '0');
        if (bccomp($amount, '0.00', 2) !== 1 || bccomp($amount, $gel, 2) === 1) {
            return false;
        }
        if (bccomp($amount, $current, 2) !== 1) {
            return true;
        }
        $next = $amount;
        DB::table('billing_orders')->where('id', $order->id)->update([
            'refunded_gel' => $next,
            'updated_at' => now(),
        ]);
        AuditLog::write((int) $order->user_id, 'partial_refund', 'order', (string) $order->public_id, [
            'amount' => $amount,
            'refunded_gel' => $next,
            'plan' => (string) $order->plan_code,
            'project_id' => (int) $order->project_id,
        ]);

        return true;
    }

    /**
     * A partial refund identified by refund_id is its own amount, even when
     * that amount equals an earlier partial. Replaying the same event id does nothing.
     */
    public static function recordDistinctPartial(string $publicId, string $amount, string $eventId): bool
    {
        $order = DB::table('billing_orders')->where('public_id', $publicId)->first();
        if ($order === null) {
            return false;
        }
        if ((string) $order->status === 'refunded') {
            return true;
        }
        if (DB::table('billing_refund_entries')->where('provider_event_id', $eventId)->exists()) {
            return true;
        }
        $gel = self::money($order->gel_amount);
        $current = self::money($order->refunded_gel ?? '0');
        if (bccomp($amount, '0.00', 2) !== 1) {
            return false;
        }
        $next = bcadd($current, $amount, 2);
        if (bccomp($next, $gel, 2) === 1) {
            return false;
        }
        $now = now();
        DB::table('billing_refund_entries')->insert([
            'billing_order_id' => $order->id,
            'provider_event_id' => $eventId,
            'amount' => $amount,
            'kind' => 'partial',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('billing_orders')->where('id', $order->id)->update([
            'refunded_gel' => $next,
            'updated_at' => $now,
        ]);
        AuditLog::write((int) $order->user_id, 'partial_refund', 'order', (string) $order->public_id, [
            'amount' => $amount,
            'refunded_gel' => $next,
            'plan' => (string) $order->plan_code,
            'project_id' => (int) $order->project_id,
            'event' => $eventId,
        ]);

        return true;
    }

    public static function expireDue(): int
    {
        $now = now()->utc()->format('Y-m-d H:i:s');
        try {
            $rows = DB::table('platform.project_limits')
                ->whereIn('plan_code', Plans::PAID)
                ->whereNotNull('plan_ends_at')
                ->where('plan_ends_at', '<=', $now)
                ->get(['project_id', 'plan_code']);
        } catch (PDOException) {
            return 0;
        }
        $count = 0;
        foreach ($rows as $row) {
            $projectId = (int) $row->project_id;
            $legacyUserId = DB::table('platform.projects')->where('id', $projectId)->value('user_id');
            self::apply($projectId, Plans::FREE, null, false);
            if (is_numeric($legacyUserId)) {
                self::setEntitlement((int) $legacyUserId, Plans::FREE, null, 'expiry');
            }
            AuditLog::write(null, 'expire', 'project', (string) $projectId, [
                'plan' => (string) $row->plan_code,
                'quota' => Plans::quota(Plans::FREE),
            ]);
            DB::table('billing_orders')
                ->where('project_id', $projectId)
                ->where('plan_code', (string) $row->plan_code)
                ->where('status', 'paid')
                ->update(['status' => 'expired', 'updated_at' => now()]);
            $count++;
        }

        return $count;
    }

    /** @param  array<string, mixed>  $previous */
    private static function restore(int $projectId, array $previous): void
    {
        $quota = is_numeric($previous['monthly_quota'] ?? null) ? (int) $previous['monthly_quota'] : 10000;
        $plan = $previous['plan_code'] ?? null;
        $plan = is_string($plan) && $plan !== '' ? $plan : null;
        $unlimited = filter_var($previous['unlimited'] ?? false, FILTER_VALIDATE_BOOL);
        $ends = $previous['plan_ends_at'] ?? null;
        $ends = is_string($ends) && $ends !== '' ? $ends : null;
        $update = ['monthly_quota' => $quota];
        if (self::hasColumn('plan_code')) {
            $update['plan_code'] = $plan;
        }
        if (self::hasColumn('plan_ends_at')) {
            $update['plan_ends_at'] = $ends;
        }
        if (self::hasColumn('unlimited_month')) {
            $update['unlimited_month'] = $unlimited;
        }
        DB::table('platform.project_limits')->where('project_id', $projectId)->update($update);
    }

    private static function apply(int $projectId, string $code, ?string $endsAt, bool $unlimited): void
    {
        $update = ['monthly_quota' => $unlimited ? 0 : Plans::quota($code)];
        if (self::hasColumn('plan_code')) {
            $update['plan_code'] = $code;
        }
        if (self::hasColumn('plan_ends_at')) {
            $update['plan_ends_at'] = $endsAt;
        }
        if (self::hasColumn('unlimited_month')) {
            $update['unlimited_month'] = $unlimited;
        }
        DB::table('platform.project_limits')->where('project_id', $projectId)->update($update);
    }

    private static function setEntitlement(int $legacyUserId, string $code, ?string $endsAt, string $source): void
    {
        $planId = DB::table('api_plans')->where('code', $code)->value('id');
        if (! is_numeric($planId)) {
            return;
        }
        self::setEntitlementById($legacyUserId, (int) $planId, $source, $endsAt);
    }

    private static function setEntitlementById(int $legacyUserId, int $planId, string $source, ?string $endsAt = null): void
    {
        $productId = self::carsProductId();
        if ($productId === null) {
            return;
        }
        $existing = DB::table('api_entitlements')
            ->where('legacy_user_id', $legacyUserId)
            ->where('api_product_id', $productId)
            ->first();
        $values = [
            'api_plan_id' => $planId,
            'status' => 'active',
            'ends_at' => $endsAt,
            'cancelled_at' => null,
            'source' => $source,
            'updated_at' => now(),
        ];
        if ($existing === null) {
            DB::table('api_entitlements')->insert($values + [
                'legacy_user_id' => $legacyUserId,
                'api_product_id' => $productId,
                'starts_at' => now(),
                'created_at' => now(),
            ]);

            return;
        }
        DB::table('api_entitlements')->where('id', $existing->id)->update($values);
    }

    private static function invoice(object $order, string $endsAt): void
    {
        $buyer = self::jsonArray($order->buyer);
        $test = MockPay::isMock($order);
        $number = ($test ? 'TEST-' : 'GEO-').now()->utc()->format('Ymd').'-'.$order->id;
        $pdf = InvoicePdf::render([
            'kind' => (string) ($buyer['kind'] ?? 'individual'),
            'name' => (string) ($buyer['name'] ?? ''),
            'company_name' => (string) ($buyer['company_name'] ?? ''),
            'company_code' => (string) ($buyer['company_code'] ?? ''),
            'company_address' => (string) ($buyer['company_address'] ?? ''),
            'vat_payer' => (bool) ($buyer['vat_payer'] ?? false),
        ], [
            'number' => $number,
            'plan' => (string) $order->plan_code.($test ? ' — TEST / სატესტო, არ არის გადახდილი' : ''),
            'usd' => number_format(((int) $order->usd_cents) / 100, 2, '.', ''),
            'gel' => (string) $order->gel_amount,
            'rate' => self::rateString($order->rate),
            'rate_date' => substr((string) $order->rate_date, 0, 10),
        ]);
        DB::table('billing_invoices')->insert([
            'billing_order_id' => $order->id,
            'number' => $number,
            'pdf' => $pdf,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Plan in force for a project: free|basic|premium..., or null for legacy (grandfathered, no plan). */
    public static function currentPlan(int $projectId): ?string
    {
        $limits = self::limits($projectId);
        $code = $limits['plan_code'];
        if ($code !== null && $code !== Plans::FREE && $limits['plan_ends_at'] !== null && strtotime($limits['plan_ends_at']) !== false && strtotime($limits['plan_ends_at']) <= time()) {
            return Plans::FREE;
        }

        return $code;
    }

    /** @return array{monthly_quota:int, plan_code:?string, unlimited:bool, plan_ends_at:?string} */
    private static function limits(int $projectId): array
    {
        $select = ['monthly_quota'];
        foreach (['plan_code', 'plan_ends_at', 'unlimited_month'] as $column) {
            if (self::hasColumn($column)) {
                $select[] = $column;
            }
        }
        $row = DB::table('platform.project_limits')->where('project_id', $projectId)->first($select);
        if ($row === null) {
            return ['monthly_quota' => 10000, 'plan_code' => null, 'unlimited' => false, 'plan_ends_at' => null];
        }

        return [
            'monthly_quota' => (int) $row->monthly_quota,
            'plan_code' => isset($row->plan_code) && $row->plan_code !== null && $row->plan_code !== '' ? (string) $row->plan_code : null,
            'unlimited' => isset($row->unlimited_month) && self::truthy($row->unlimited_month),
            'plan_ends_at' => isset($row->plan_ends_at) && $row->plan_ends_at !== null && $row->plan_ends_at !== '' ? (string) $row->plan_ends_at : null,
        ];
    }

    private static function carsProductId(): ?int
    {
        $id = DB::table('api_products')->where('slug', 'cars')->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private static function hasColumn(string $column): bool
    {
        if (! in_array($column, ['plan_code', 'plan_ends_at', 'unlimited_month'], true)) {
            return false;
        }
        try {
            DB::table('platform.project_limits')->limit(0)->get([$column]);

            return true;
        } catch (PDOException) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private static function jsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function truthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 't' || $value === 'true';
    }

    private static function money(mixed $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private static function rateString(mixed $rate): string
    {
        $value = (string) $rate;
        if (preg_match('/^\d+(\.\d+)?/', $value, $match)) {
            return $match[0];
        }

        return $value;
    }
}
