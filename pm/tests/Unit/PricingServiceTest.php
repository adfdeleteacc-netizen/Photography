<?php

namespace Tests\Unit;

use App\Services\Payment\PricingService;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    public function test_spec_example_is_reproduced_exactly(): void
    {
        $b = (new PricingService)->breakdown(1_000_000, 0, 0, 20.0);   // ₹10,000 at 20%

        $this->assertSame(200_000, $b['commission_paise']);            // ₹2,000
        $this->assertSame(800_000, $b['creator_share_paise']);         // ₹8,000 (spec: photographer receives)
        $this->assertSame(36_000, $b['gst_on_commission_paise']);      // 18% of commission
        $this->assertSame(5_000, $b['tcs_paise']);                     // 0.5% of ₹10,000
        $this->assertSame(759_000, $b['creator_net_paise']);           // after GST + TCS
    }

    public function test_discounts_are_platform_funded(): void
    {
        $b = (new PricingService)->breakdown(1_000_000, 100_000, 50_000, 20.0);

        $this->assertSame(850_000, $b['total_amount_paise']);          // customer pays less
        $this->assertSame(200_000, $b['commission_paise']);            // creator side unchanged
        $this->assertSame(759_000, $b['creator_net_paise']);
    }

    public function test_discount_never_exceeds_base(): void
    {
        $b = (new PricingService)->breakdown(10_000, 9_000, 9_000, 20.0);
        $this->assertSame(0, $b['total_amount_paise']);
    }
}
