<?php

namespace App\Jobs;

use App\Services\Settlement\SettlementService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RunSettlements implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public function __construct(public string $frequency) {}

    public function handle(SettlementService $svc): void
    {
        $svc->runScheduled($this->frequency);
    }
}
