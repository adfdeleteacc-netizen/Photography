<?php

namespace App\Console\Commands;

use App\Services\Booking\BookingService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Console\Command;

class MarketplaceMaintenance extends Command
{
    protected $signature = 'marketplace:maintain';
    protected $description = 'Expire unpaid booking holds, run subscription lifecycle (renewals, grace, expiry, reminders)';

    public function handle(BookingService $bookings, SubscriptionService $subs): int
    {
        $this->info('Expired unpaid bookings: ' . $bookings->expireUnpaid());
        $this->table(['metric', 'count'], collect($subs->processLifecycle())->map(fn ($v, $k) => [$k, $v])->values());
        return self::SUCCESS;
    }
}
