<?php

namespace App\Services\Coupon;

use App\Exceptions\MarketplaceException;
use App\Models\{Booking, Coupon, CouponUsage};

class CouponService
{
    /** Validate and return the discount in paise. Pass $lock=true inside a transaction when consuming. */
    public function evaluate(string $code, int $userId, int $orderPaise, bool $lock = false): array
    {
        $coupon = Coupon::where('code', strtoupper(trim($code)))->when($lock, fn ($q) => $q->lockForUpdate())->first();

        $fail = fn (string $m) => throw new MarketplaceException($m, 'invalid_coupon', 422);

        if (! $coupon || ! $coupon->is_active) { $fail('Invalid coupon code.'); }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) { $fail('This coupon is not active yet.'); }
        if ($coupon->expires_at && $coupon->expires_at->isPast()) { $fail('This coupon has expired.'); }
        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) { $fail('This coupon has reached its usage limit.'); }
        if ($orderPaise < $coupon->min_order_paise) { $fail('Order value is below the coupon minimum.'); }
        if (CouponUsage::where('coupon_id', $coupon->id)->where('user_id', $userId)->count() >= $coupon->per_user_limit) {
            $fail('You have already used this coupon.');
        }

        $discount = $coupon->type === 'percentage'
            ? (int) floor($orderPaise * $coupon->value / 100)
            : (int) $coupon->value;
        if ($coupon->max_discount_paise) { $discount = min($discount, $coupon->max_discount_paise); }

        return [$coupon, min($discount, $orderPaise)];
    }

    public function consume(Coupon $coupon, Booking $booking, int $discount): void
    {
        CouponUsage::create(['coupon_id' => $coupon->id, 'user_id' => $booking->customer_id, 'booking_id' => $booking->id, 'discount_paise' => $discount]);
        $coupon->increment('used_count');
    }

    public function release(Booking $booking): void
    {
        $usage = CouponUsage::where('booking_id', $booking->id)->first();
        if ($usage) {
            Coupon::whereKey($usage->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
            $usage->delete();
        }
    }
}
