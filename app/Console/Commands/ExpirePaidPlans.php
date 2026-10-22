<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Billing\Entitlements;
use App\Billing\V2\Lifecycle;
use Illuminate\Console\Command;

final class ExpirePaidPlans extends Command
{
    protected $signature = 'billing:expire';

    protected $description = 'End paid plans whose end time has passed';

    public function handle(): int
    {
        $count = Entitlements::expireDue();
        $rolled = Lifecycle::rollDue();
        $this->info('Expired '.$count.' project plans. Rolled '.$rolled.' workspace periods.');

        return 0;
    }
}
