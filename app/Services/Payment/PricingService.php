<?php

namespace App\Services\Payment;

use App\Models\Setting;

/**
 * Single source of truth for money maths. All values are integer paise.
 *
 * Model used (matches the spec example):
 *   base            = package price                       ₹10,000
 *   commission      = base × commission%                  ₹2,000  (20%)
 *   gst             = commission × 18%                    ₹360    (charged on the platform's fee)
 *   tcs             = base × 0.5%                         ₹50     (GST s.52 TCS withheld from supplier)
 *   creator_net     = base − commission − gst − tcs       ₹7,590  (what is actually paid out)
 *   creator_share   = base − commission                   ₹8,000  (spec's "photographer receives")
 *
 * Coupon / loyalty discounts are platform-funded: the customer pays less but
 * the creator's commission and payout are computed on the undiscounted base.
 */
class PricingService
{
    public function commissionPercent(): float
    {
        return (float) Setting::get('commission_percent', config('marketplace.commission_percent'));
    }

    /** @return array<string,int|float> */
    public function breakdown(int $basePaise, int $couponDiscount = 0, int $loyaltyDiscount = 0, ?float $commissionPercent = null): array
    {
        $pct = $commissionPercent ?? $this->commissionPercent();
        $discount = min($basePaise, $couponDiscount + $loyaltyDiscount);

        $commission = (int) round($basePaise * $pct / 100);
        $gst = (int) round($commission * config('marketplace.gst_percent') / 100);
        $tcs = (int) round($basePaise * config('marketplace.tcs_percent') / 100);

        return [
            'base_amount_paise' => $basePaise,
            'coupon_discount_paise' => $couponDiscount,
            'loyalty_discount_paise' => $loyaltyDiscount,
            'total_amount_paise' => $basePaise - $discount,
            'commission_percent' => $pct,
            'commission_paise' => $commission,
            'gst_on_commission_paise' => $gst,
            'tcs_paise' => $tcs,
            'creator_share_paise' => $basePaise - $commission,
            'creator_net_paise' => $basePaise - $commission - $gst - $tcs,
        ];
    }
}
