<?php

declare(strict_types=1);

namespace App\Billing\V2;

use Geoapi\Platform\UnavailableException;
use PDOException;

final class Meter
{
    public static function applies(int $projectId): bool
    {
        if (! Flag::on()) {
            return false;
        }
        try {
            return Workspace::shared($projectId) !== null;
        } catch (PDOException) {
            return false;
        }
    }

    /** @return array{id:int, limit:int}|null */
    public static function rpmFor(int $projectId): ?array
    {
        if (! self::applies($projectId)) {
            return null;
        }
        $workspace = Workspace::shared($projectId);
        if ($workspace === null) {
            return null;
        }

        return [
            'id' => (int) $workspace->id,
            'limit' => (int) $workspace->subscription->rpm,
        ];
    }

    /** @return array{limit:int, used:int, remaining:int, exhausted:bool, reset_at:int, cap?:bool}|null */
    public static function preview(int $projectId, int $now): ?array
    {
        if (! self::applies($projectId)) {
            return null;
        }
        $workspace = Workspace::shared($projectId);
        if ($workspace === null) {
            return null;
        }
        try {
            return Ledger::preview((int) $workspace->id, $projectId, $now);
        } catch (PDOException) {
            throw new UnavailableException;
        }
    }

    /** @return array{ok:bool, reason?:string, limit:int, used:int, remaining:int, reset_at:int}|null */
    public static function commit(int $projectId, string $route, int $now): ?array
    {
        if (! self::applies($projectId)) {
            return null;
        }
        $workspace = Workspace::shared($projectId);
        if ($workspace === null) {
            return null;
        }
        try {
            $saved = Ledger::consume((int) $workspace->id, $projectId, $now);
        } catch (PDOException) {
            throw new UnavailableException;
        }
        unset($route);

        return $saved;
    }
}
