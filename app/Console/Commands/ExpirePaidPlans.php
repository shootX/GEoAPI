<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Billing\Entitlements;
use Illuminate\Console\Command;

final class ExpirePaidPlans extends Command
{
    protected $signature = 'billing:expire';

    protected $description = 'End paid plans whose end time has passed';

    public function handle(): int
    {
        $count = Entitlements::expireDue();
        $this->info('Expired '.$count.' project plans.');

        return 0;
    }
}
