<?php

declare(strict_types=1);

namespace App\Billing;

use Illuminate\Support\Facades\DB;

final class AuditLog
{
    /** @param  array<string, mixed>  $detail */
    public static function write(?int $actorUserId, string $action, string $subjectType, string $subjectId, array $detail): void
    {
        DB::table('billing_audit')->insert([
            'actor_user_id' => $actorUserId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'detail' => json_encode(self::scrub($detail), JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $detail
     * @return array<string, mixed>
     */
    private static function scrub(array $detail): array
    {
        $clean = [];
        foreach ($detail as $key => $value) {
            $name = strtolower((string) $key);
            if (str_contains($name, 'secret') || str_contains($name, 'token') || str_contains($name, 'password') || str_contains($name, 'authorization')) {
                continue;
            }
            if (is_array($value)) {
                $clean[$key] = self::scrub($value);

                continue;
            }
            if (is_string($value)) {
                $value = preg_replace('/geoapi_sk_[A-Za-z0-9_-]+/', '[redacted]', $value) ?? $value;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }
}
