<?php

declare(strict_types=1);

namespace App\Billing;

use Illuminate\Support\Facades\DB;

final class PaymentEvents
{
    /**
     * Record a provider event once. A repeated id does not insert again.
     */
    public static function record(string $provider, string $eventId, string $type, array $summary = []): bool
    {
        $now = now();
        $inserted = DB::table('payment_events')->insertOrIgnore([
            'provider' => $provider,
            'event_id' => $eventId,
            'type' => $type,
            'summary' => json_encode($summary, JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $inserted === 1;
    }
}
