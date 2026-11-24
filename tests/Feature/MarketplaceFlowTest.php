<?php

namespace Tests\Feature;

use App\Exceptions\MarketplaceException;
use App\Models\{AvailabilitySlot, Booking, Category, Ledger, PhotographerProfile, ServicePackage, Subscription, SubscriptionPlan, User};
use App\Services\Booking\BookingService;
use App\Services\Payment\{LedgerService, RazorpayClient};
use Database\Seeders\{CatalogSeeder, RolesAndPermissionsSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;
    private PhotographerProfile $profile;
    private ServicePackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesAndPermissionsSeeder::class, CatalogSeeder::class]);

        $this->customer = User::factory()->create()->assignRole('customer');
        $creator = User::factory()->create()->assignRole('photographer');
        $this->profile = PhotographerProfile::create(['user_id' => $creator->id, 'display_name' => 'T', 'slug' => 't', 'city' => 'Mumbai']);

        // Next Monday 09:00–20:00
        AvailabilitySlot::create(['photographer_profile_id' => $this->profile->id, 'day_of_week' => 1, 'start_time' => '09:00:00', 'end_time' => '20:00:00']);
        Subscription::create(['user_id' => $creator->id, 'subscription_plan_id' => SubscriptionPlan::first()->id, 'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addMonth()]);

        $this->package = $this->profile->packages()->create(['category_id' => Category::first()->id, 'title' => 'Shoot', 'price_paise' => 1_000_000, 'duration_minutes' => 120]);
    }

    private function request(string $when = '10:00'): array
    {
        return ['service_package_id' => $this->package->id, 'photographer_profile_id' => $this->profile->id,
                'starts_at' => now()->next('Monday')->setTimeFromTimeString($when)->toDateTimeString()];
    }

    public function test_inquiry_snapshots_pricing_and_opens_a_conversation(): void
    {
        $b = app(BookingService::class)->createInquiry($this->customer, $this->request());

        $this->assertSame(Booking::INQUIRY, $b->status);
        $this->assertSame(200_000, $b->commission_paise);
        $this->assertNotNull($b->conversation);
    }

    public function test_overlapping_confirmed_booking_blocks_a_second_request(): void
    {
        $svc = app(BookingService::class);
        $first = $svc->createInquiry($this->customer, $this->request('10:00'));
        $first->update(['status' => Booking::CONFIRMED]);

        $this->expectException(MarketplaceException::class);
        $svc->createInquiry(User::factory()->create()->assignRole('customer'), $this->request('11:00'));   // overlaps 10–12
    }

    public function test_creator_without_subscription_cannot_be_booked(): void
    {
        Subscription::query()->update(['status' => 'expired']);

        $this->expectException(MarketplaceException::class);
        app(BookingService::class)->createInquiry($this->customer, $this->request());
    }

    public function test_capture_and_refund_ledger_entries_balance(): void
    {
        $b = app(BookingService::class)->createInquiry($this->customer, $this->request());
        $ledger = app(LedgerService::class);

        $ledger->recordCapture($b);
        $ledger->recordRefund($b, 333_333);   // awkward partial amount to exercise rounding

        $debits = Ledger::where('direction', 'debit')->sum('amount_paise');
        $credits = Ledger::where('direction', 'credit')->sum('amount_paise');
        $this->assertSame((int) $debits, (int) $credits);
    }

    public function test_razorpay_signature_verification(): void
    {
        config(['marketplace.razorpay.key_secret' => 'secret']);
        $sig = hash_hmac('sha256', 'order_1|pay_1', 'secret');
        $gw = new RazorpayClient;

        $this->assertTrue($gw->verifyPaymentSignature('order_1', 'pay_1', $sig));
        $this->assertFalse($gw->verifyPaymentSignature('order_1', 'pay_2', $sig));   // tampered payment id
    }

    public function test_creator_routes_require_subscription(): void
    {
        $u = User::factory()->create(['email_verified_at' => now()])->assignRole('photographer');

        $response = $this->actingAs($u, 'sanctum')->getJson('/api/dashboard');
        $response->assertStatus(402)->assertJson(['code' => 'subscription_required']);
    }

    public function test_customers_cannot_hit_admin_routes(): void
    {
        $this->actingAs($this->customer, 'sanctum')->getJson('/api/admin/dashboard')->assertForbidden();
    }
}
