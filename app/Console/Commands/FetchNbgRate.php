<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Billing\NbgRate;
use Illuminate\Console\Command;

final class FetchNbgRate extends Command
{
    protected $signature = 'billing:nbg-rate';

    protected $description = 'Cache the National Bank of Georgia USD rate';

    public function handle(): int
    {
        $rate = NbgRate::refresh();
        if ($rate === null) {
            $this->error('NBG rate is unavailable and no stored rate exists.');

            return 1;
        }
        $this->info(($rate['fetched'] ? 'Stored' : 'Kept').' USD '.$rate['rate'].' dated '.$rate['rated_on']);

        return 0;
    }
}
