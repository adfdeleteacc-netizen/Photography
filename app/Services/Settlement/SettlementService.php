<?php

namespace App\Services\Settlement;

use App\Exceptions\MarketplaceException;
use App\Models\{Booking, Settlement, SettlementItem, User};
use App\Services\Payment\{LedgerService, RazorpayClient};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Settlement lifecycle: pending_approval → approved → processing → paid (or failed).
 * A booking is settled at most once (unique index on settlement_items.booking_id).
 * Net amount per booking is read from the ledger so partial refunds are honoured automatically.
 */
class SettlementService
{
    public function __construct(private LedgerService $ledger, private RazorpayClient $gateway) {}

    /** Build settlements for all creators with eligible bookings. $frequency: daily|weekly|manual. */
    public function generate(string $frequency, ?int $onlyUserId = null): int
    {
        $cutoff = now()->subDays(config('marketplace.settlement.hold_days_after_completion'));

        $eligible = Booking::query()
            ->with(['photographer', 'studio'])
            ->where(function ($q) use ($cutoff) {
                $q->where(fn ($w) => $w->where('status', Booking::COMPLETED)->where('completed_at', '<=', $cutoff))
                  // Cancelled after payment with a partial/no refund: the retained share is still owed to the creator
                  // (the ledger already holds only the un-refunded portion; fully refunded bookings net to 0 and are skipped).
                  ->orWhere(fn ($w) => $w->where('status', Booking::CANCELLED)->whereNotNull('confirmed_at')->where('cancelled_at', '<=', $cutoff));
            })
            ->whereDoesntHave('settlementItem')->get()
            ->groupBy(fn (Booking $b) => $this->ledger->payeeId($b));

        $created = 0;
        foreach ($eligible as $payeeId => $bookings) {
            if ($onlyUserId && $payeeId !== $onlyUserId) { continue; }
            $created += (int) $this->createFor((int) $payeeId, $bookings, $frequency);
        }
        return $created;
    }

    private function createFor(int $userId, $bookings, string $frequency): bool
    {
        return DB::transaction(function () use ($userId, $bookings, $frequency) {
            $items = [];
            $gross = $commission = $gst = $tcs = $net = 0;

            foreach ($bookings as $b) {
                $payable = $this->ledger->bookingPayable($b->id, $userId);
                if ($payable <= 0) { continue; }
                $items[] = ['booking_id' => $b->id, 'net_paise' => $payable];
                $gross += $b->base_amount_paise; $commission += $b->commission_paise;
                $gst += $b->gst_on_commission_paise; $tcs += $b->tcs_paise; $net += $payable;
            }

            if (! $items || $net < config('marketplace.settlement.minimum_payout_paise')) {
                return false;   // leave bookings unsettled; they roll into the next run
            }

            $s = Settlement::create([
                'reference' => 'ST' . now()->format('ymd') . strtoupper(Str::random(6)),
                'user_id' => $userId, 'frequency' => $frequency,
                'gross_paise' => $gross, 'commission_paise' => $commission, 'gst_paise' => $gst,
                'tcs_paise' => $tcs, 'net_paise' => $net, 'status' => 'pending_approval',
            ]);
            $s->items()->createMany($items);
            return true;
        });
    }

    public function approve(Settlement $s, User $admin): Settlement
    {
        return DB::transaction(function () use ($s, $admin) {
            $s = Settlement::whereKey($s->id)->lockForUpdate()->firstOrFail();
            if ($s->status !== 'pending_approval') {
                throw new MarketplaceException('Settlement is not awaiting approval.', 'invalid_state', 409);
            }
            $user = User::findOrFail($s->user_id);
            if (! $user->isKycVerified() || ! $user->bankAccounts()->where('status', 'verified')->exists()) {
                throw new MarketplaceException('Creator KYC and bank account must be verified before payout.', 'kyc_required', 422);
            }
            $s->update(['status' => 'approved', 'approved_by' => $admin->id]);
            return $s;
        });
    }

    /** Pay an approved settlement via RazorpayX. Idempotent through the settlement reference. */
    public function disburse(Settlement $s): Settlement
    {
        $s = DB::transaction(function () use ($s) {
            $s = Settlement::whereKey($s->id)->lockForUpdate()->firstOrFail();
            if ($s->status !== 'approved') {
                throw new MarketplaceException('Settlement must be approved first.', 'invalid_state', 409);
            }
            $s->update(['status' => 'processing']);
            return $s;
        });

        try {
            $bank = User::findOrFail($s->user_id)->bankAccounts()->where('status', 'verified')->where('is_primary', true)->firstOrFail();
            $res = $this->gateway->payout($bank->razorpay_fund_account_id, $s->net_paise, $s->reference);

            DB::transaction(function () use ($s, $res) {
                $s->update(['status' => 'paid', 'paid_at' => now(), 'payout_reference' => $res['id'] ?? null]);
                $this->ledger->recordPayout(null, $s->user_id, $s->id, $s->net_paise, $s->reference);
                \App\Models\Transaction::create(['type' => 'payout', 'amount_paise' => $s->net_paise, 'gateway_ref' => $res['id'] ?? null]);
            });
        } catch (\Throwable $e) {
            $s->update(['status' => 'failed']);   // admin can re-approve after fixing bank details
            throw $e;
        }
        return $s->refresh();
    }

    /** Daily/weekly auto-runs create + (optionally) auto-approve for verified creators. */
    public function runScheduled(string $frequency): int
    {
        return $this->generate($frequency);
    }
}
