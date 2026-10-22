<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Billing\V2\Backfill;
use Illuminate\Console\Command;

final class BillingV2Backfill extends Command
{
    protected $signature = 'billing:v2-backfill {--dry-run : Print the rows that would be inserted} {--rollback : Delete backfill workspaces that have no paid v2 order}';

    protected $description = 'Copy existing billing rights into workspace rows without changing project quotas';

    public function handle(): int
    {
        $result = Backfill::run((bool) $this->option('dry-run'), (bool) $this->option('rollback'));
        $this->line((string) json_encode($result, JSON_UNESCAPED_UNICODE));

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
