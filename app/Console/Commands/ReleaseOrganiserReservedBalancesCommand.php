<?php

namespace App\Console\Commands;

use App\Services\OrganiserBalanceService;
use Illuminate\Console\Command;

class ReleaseOrganiserReservedBalancesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'organiser:release-reserved-balances';

    /**
     * @var string
     */
    protected $description = 'Move organiser reserved balances to available after event completed + payout hold days';

    public function handle(OrganiserBalanceService $balances): int
    {
        $released = $balances->releaseEligibleReservedBalances();

        $this->info("Released reserved balance for {$released} order item(s).");

        return self::SUCCESS;
    }
}
