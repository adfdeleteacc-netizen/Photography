<?php

namespace App\Services\Coupon;

use App\Exceptions\MarketplaceException;
use App\Models\{Booking, LoyaltyTransaction, LoyaltyWallet};

class LoyaltyService
{
    public function wallet(int $userId, bool $lock = false): LoyaltyWallet
    {
        $w = LoyaltyWallet::firstOrCreate(['user_id' => $userId]);
        return $lock ? LoyaltyWallet::whereKey($w->id)->lockForUpdate()->first() : $w;
    }

    /** Rupee value (paise) of redeeming $points against an order, capped by policy. */
    public function discountFor(int $points, int $orderPaise): int
    {
        $cap = (int) floor($orderPaise * config('marketplace.loyalty.max_redeem_percent') / 100);
        return min($points * config('marketplace.loyalty.paise_per_point'), $cap);
    }

    public function pointsNeeded(int $discountPaise): int
    {
        return (int) ceil($discountPaise / config('marketplace.loyalty.paise_per_point'));
    }

    public function earn(int $userId, int $points, string $type, ?int $bookingId = null, ?string $note = null): void
    {
        if ($points <= 0) { return; }
        $w = $this->wallet($userId, true);
        $w->increment('balance', $points);
        LoyaltyTransaction::create(['loyalty_wallet_id' => $w->id, 'type' => $type, 'points' => $points, 'booking_id' => $bookingId, 'note' => $note]);
    }

    public function redeem(int $userId, int $points, Booking $booking): void
    {
        if ($points <= 0) { return; }
        $w = $this->wallet($userId, true);
        if ($w->balance < $points) {
            throw new MarketplaceException('Not enough loyalty points.', 'insufficient_points', 422);
        }
        $w->decrement('balance', $points);
        LoyaltyTransaction::create(['loyalty_wallet_id' => $w->id, 'type' => 'redeem', 'points' => -$points, 'booking_id' => $booking->id]);
    }

    public function refundRedeemed(Booking $booking): void
    {
        if ($booking->loyalty_points_used > 0) {
            $w = $this->wallet($booking->customer_id, true);
            $w->increment('balance', $booking->loyalty_points_used);
            LoyaltyTransaction::create(['loyalty_wallet_id' => $w->id, 'type' => 'reversal', 'points' => $booking->loyalty_points_used, 'booking_id' => $booking->id, 'note' => 'Booking not completed']);
        }
    }

    public function earnForCompletedBooking(Booking $b): void
    {
        $points = intdiv($b->total_amount_paise, 10000) * config('marketplace.loyalty.points_per_100_rupees');
        $this->earn($b->customer_id, $points, 'earn', $b->id, 'Booking ' . $b->reference);
    }
}
