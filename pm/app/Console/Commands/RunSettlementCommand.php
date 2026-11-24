<?php

namespace App\Console\Commands;

use App\Services\Settlement\SettlementService;
use Illuminate\Console\Command;

class RunSettlementCommand extends Command
{
    protected $signature = 'marketplace:settle {frequency=weekly : daily|weekly|manual}';
    protected $description = 'Generate settlements (pending admin approval) for completed bookings';

    public function handle(SettlementService $svc): int
    {
        $this->info('Settlements created: ' . $svc->generate($this->argument('frequency')));
        return self::SUCCESS;
    }
}
