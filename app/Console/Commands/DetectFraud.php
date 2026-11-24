<?php

namespace App\Console\Commands;

use App\Models\{Booking, FraudFlag, Payment};
use Illuminate\Console\Command;

/**
 * Heuristic fraud monitoring. These are starter rules for the admin review queue, not a
 * substitute for Razorpay's risk tooling — tune thresholds against real traffic.
 */
class DetectFraud extends Command
{
    protected $signature = 'marketplace:detect-fraud';
    protected $description = 'Flag suspicious customers/creators for admin review';

    public function handle(): int
    {
        $n = 0;

        // 1. Many failed payments from one user in an hour (card testing)
        Payment::where('status', 'failed')->where('updated_at', '>=', now()->subHour())
            ->selectRaw('user_id, COUNT(*) c')->groupBy('user_id')->having('c', '>=', 5)->get()
            ->each(function ($r) use (&$n) { $n += $this->flag($r->user_id, null, "{$r->c} failed payments in 1 hour"); });

        // 2. Serial cancellers
        Booking::where('status', Booking::CANCELLED)->where('cancelled_at', '>=', now()->subDays(7))
            ->selectRaw('customer_id, COUNT(*) c')->groupBy('customer_id')->having('c', '>=', 5)->get()
            ->each(function ($r) use (&$n) { $n += $this->flag($r->customer_id, null, "{$r->c} cancellations in 7 days"); });

        // 3. Large refunds ratio for a creator (possible collusion / chargeback abuse)
        Booking::whereIn('status', [Booking::REFUNDED])->where('updated_at', '>=', now()->subDays(30))
            ->join('photographer_profiles as pp', 'pp.id', '=', 'bookings.photographer_profile_id')
            ->selectRaw('pp.user_id, COUNT(*) c')->groupBy('pp.user_id')->having('c', '>=', 5)->get()
            ->each(function ($r) use (&$n) { $n += $this->flag($r->user_id, null, "{$r->c} refunded bookings in 30 days"); });

        $this->info("Flags created: {$n}");
        return self::SUCCESS;
    }

    private function flag(?int $userId, ?int $bookingId, string $reason): int
    {
        return FraudFlag::firstOrCreate(['user_id' => $userId, 'booking_id' => $bookingId, 'reason' => $reason, 'status' => 'open'])->wasRecentlyCreated ? 1 : 0;
    }
}
