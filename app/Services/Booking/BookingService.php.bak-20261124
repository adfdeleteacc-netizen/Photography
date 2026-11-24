<?php

namespace App\Services\Booking;

use App\Exceptions\MarketplaceException;
use App\Models\{Booking, BookingStatusLog, Conversation, ServicePackage, User};
use App\Services\Coupon\{CouponService, LoyaltyService};
use App\Services\Notification\NotificationService;
use App\Services\Payment\{LedgerService, PaymentService, PricingService};
use App\Services\Subscription\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Booking flow: inquiry → (creator accepts) pending_payment → (payment captured) confirmed → completed
 * Every state change runs in a DB transaction under a row lock on the creator's profile.
 */
class BookingService
{
    public function __construct(
        private AvailabilityService $availability,
        private PricingService $pricing,
        private CouponService $coupons,
        private LoyaltyService $loyalty,
        private SubscriptionService $subscriptions,
        private PaymentService $payments,
        private LedgerService $ledger,
        private NotificationService $notify,
    ) {}

    /** Steps 1-4: customer picks a package + date and sends a request. */
    public function createInquiry(User $customer, array $data): Booking
    {
        $package = ServicePackage::with('owner')->where('is_active', true)->findOrFail($data['service_package_id']);
        $profile = $package->owner_type === \App\Models\Studio::class
            ? \App\Models\PhotographerProfile::findOrFail($data['photographer_profile_id'])
            : $package->owner;

        $this->assertCreatorCanReceive($profile);

        $start = Carbon::parse($data['starts_at']);
        $end = $start->copy()->addMinutes($package->duration_minutes);

        $booking = DB::transaction(function () use ($customer, $data, $package, $profile, $start, $end) {
            $this->availability->assertAvailableAndLock($profile->id, $start, $end);
            $this->assertMonthlyQuota($profile);

            // Quote only; coupon & points are reserved on acceptance.
            [$couponDiscount, $loyaltyDiscount] = $this->quoteDiscounts($customer, $data, $package->price_paise);
            $price = $this->pricing->breakdown($package->price_paise, $couponDiscount, $loyaltyDiscount);
            unset($price['creator_share_paise']);

            $booking = Booking::create($price + [
                'reference' => 'BK' . strtoupper(Str::random(10)),
                'customer_id' => $customer->id,
                'photographer_profile_id' => $profile->id,
                'studio_id' => $profile->studio_id,
                'service_package_id' => $package->id,
                'starts_at' => $start, 'ends_at' => $end,
                'location' => $data['location'] ?? null, 'notes' => $data['notes'] ?? null,
                'status' => Booking::INQUIRY,
                'coupon_code' => $data['coupon_code'] ?? null,
                'loyalty_points_used' => (int) ($data['loyalty_points'] ?? 0),
            ]);
            $this->log($booking, null, Booking::INQUIRY, $customer->id);

            // Chat is only allowed once an inquiry exists.
            Conversation::create(['booking_id' => $booking->id, 'customer_id' => $customer->id, 'creator_user_id' => $profile->user_id]);
            return $booking;
        });

        $this->notify->bookingRequested($booking);
        return $booking;
    }

    /** Step 5 (accept): re-check slot, reserve coupon/points, hold the slot while payment is pending. */
    public function accept(Booking $booking, User $actor): Booking
    {
        $booking = DB::transaction(function () use ($booking, $actor) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($b, Booking::PENDING_PAYMENT);
            $this->assertCreatorCanReceive($b->photographer);
            $this->availability->assertAvailableAndLock($b->photographer_profile_id, $b->starts_at, $b->ends_at, $b->id);

            // Recompute price now that coupon/points are being consumed.
            $couponDiscount = 0; $coupon = null;
            if ($b->coupon_code) {
                [$coupon, $couponDiscount] = $this->coupons->evaluate($b->coupon_code, $b->customer_id, $b->base_amount_paise, lock: true);
            }
            $loyaltyDiscount = $this->loyalty->discountFor($b->loyalty_points_used, $b->base_amount_paise - $couponDiscount);
            $pointsUsed = $this->loyalty->pointsNeeded($loyaltyDiscount);

            $price = $this->pricing->breakdown($b->base_amount_paise, $couponDiscount, $loyaltyDiscount, (float) $b->commission_percent);
            unset($price['creator_share_paise']);
            $b->fill($price + [
                'coupon_id' => $coupon?->id, 'loyalty_points_used' => $pointsUsed,
                'payment_due_at' => now()->addMinutes(config('marketplace.payment_hold_minutes')),
            ]);

            if ($coupon) { $this->coupons->consume($coupon, $b, $couponDiscount); }
            $this->loyalty->redeem($b->customer_id, $pointsUsed, $b);

            $this->transition($b, Booking::PENDING_PAYMENT, $actor->id, 'Accepted by creator');
            return $b;
        });

        $this->payments->createOrderForBooking($booking);   // outside the lock: network call
        $this->notify->bookingAccepted($booking);
        return $booking->refresh();
    }

    public function reject(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        return $this->cancel($booking, $actor, $reason ?? 'Declined by creator');
    }

    /** Step 7: called by PaymentService after a verified capture. Idempotent. */
    public function confirmAfterPayment(Booking $booking): Booking
    {
        return DB::transaction(function () use ($booking) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($b->status === Booking::CONFIRMED) { return $b; }           // webhook + callback race

            if ($b->status !== Booking::PENDING_PAYMENT) {
                // Money arrived for a booking we already released (e.g. hold expired) → refund automatically.
                throw new MarketplaceException('Booking is no longer awaiting payment.', 'booking_not_payable', 409);
            }
            $this->availability->assertAvailableAndLock($b->photographer_profile_id, $b->starts_at, $b->ends_at, $b->id);
            $this->availability->lockForBooking($b);
            $b->confirmed_at = now();
            $this->transition($b, Booking::CONFIRMED, null, 'Payment captured');
            $this->ledger->recordCapture($b);
            return $b;
        });
    }

    public function complete(Booking $booking, User $actor): Booking
    {
        $done = DB::transaction(function () use ($booking, $actor) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($b, Booking::COMPLETED);
            $b->completed_at = now();
            $this->transition($b, Booking::COMPLETED, $actor->id);
            $this->loyalty->earnForCompletedBooking($b);
            return $b;
        });
        app(\App\Services\Auth\AccountService::class)->rewardReferralIfFirst($done->customer);
        return $done;
    }

    /** Cancel before payment, or after payment (with refund per policy). */
    public function cancel(Booking $booking, User $actor, ?string $reason = null): Booking
    {
        $needsRefund = false;
        $b = DB::transaction(function () use ($booking, $actor, $reason, &$needsRefund) {
            $b = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            $this->assertTransition($b, Booking::CANCELLED);

            $needsRefund = $b->status === Booking::CONFIRMED;
            if (in_array($b->status, [Booking::PENDING_PAYMENT], true)) {
                $this->coupons->release($b);
                $this->loyalty->refundRedeemed($b);
            }
            $this->availability->unlockForBooking($b);
            $b->cancelled_at = now();
            $this->transition($b, Booking::CANCELLED, $actor->id, $reason);
            return $b;
        });

        if ($needsRefund) {
            $this->payments->refundBooking($b, $this->refundAmountFor($b), $reason ?? 'Booking cancelled');
        }
        $this->notify->bookingCancelled($b);
        return $b->refresh();
    }

    /** Default cancellation policy: full refund >7 days out, 50% 2–7 days, none <2 days. Override per business. */
    public function refundAmountFor(Booking $b): int
    {
        $days = now()->diffInDays($b->starts_at, false);
        return match (true) {
            $days >= 7 => $b->total_amount_paise,
            $days >= 2 => intdiv($b->total_amount_paise, 2),
            default => 0,
        };
    }

    /** Hold expiry: called by scheduler. */
    public function expireUnpaid(): int
    {
        $n = 0;
        Booking::where('status', Booking::PENDING_PAYMENT)->where('payment_due_at', '<', now())->pluck('id')
            ->each(function ($id) use (&$n) {
                DB::transaction(function () use ($id, &$n) {
                    $b = Booking::whereKey($id)->lockForUpdate()->first();
                    if ($b && $b->status === Booking::PENDING_PAYMENT) {
                        $this->coupons->release($b);
                        $this->loyalty->refundRedeemed($b);
                        $b->cancelled_at = now();
                        $this->transition($b, Booking::CANCELLED, null, 'Payment window expired');
                        $n++;
                    }
                });
            });
        return $n;
    }

    // ---------------------------------------------------------------- helpers

    public function transition(Booking $b, string $to, ?int $actorId = null, ?string $note = null): void
    {
        $this->assertTransition($b, $to);
        $from = $b->status;
        $b->status = $to;
        $b->save();
        $this->log($b, $from, $to, $actorId, $note);
    }

    private function assertTransition(Booking $b, string $to): void
    {
        if (! $b->canTransitionTo($to)) {
            throw new MarketplaceException("Cannot move booking from {$b->status} to {$to}.", 'invalid_transition', 409);
        }
    }

    private function log(Booking $b, ?string $from, string $to, ?int $by, ?string $note = null): void
    {
        BookingStatusLog::create(['booking_id' => $b->id, 'from_status' => $from, 'to_status' => $to, 'changed_by' => $by, 'note' => $note]);
    }

    private function assertCreatorCanReceive($profile): void
    {
        $profile->loadMissing('user');
        if (! $profile->user->activeSubscription()) {
            throw new MarketplaceException('This creator is not currently accepting bookings.', 'creator_inactive', 422);
        }
    }

    private function assertMonthlyQuota($profile): void
    {
        $this->subscriptions->assertBookingQuota($profile->user ?? $profile->load('user')->user);
    }

    /** @return array{int,int} [couponDiscount, loyaltyDiscount] */
    private function quoteDiscounts(User $customer, array $data, int $base): array
    {
        $couponDiscount = 0;
        if (! empty($data['coupon_code'])) {
            [, $couponDiscount] = $this->coupons->evaluate($data['coupon_code'], $customer->id, $base);
        }
        $loyaltyDiscount = $this->loyalty->discountFor((int) ($data['loyalty_points'] ?? 0), $base - $couponDiscount);
        return [$couponDiscount, $loyaltyDiscount];
    }
}
