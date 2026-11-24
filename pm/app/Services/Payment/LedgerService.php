<?php

namespace App\Services\Payment;

use App\Models\Booking;
use App\Models\Ledger;
use Illuminate\Support\Facades\DB;

/**
 * Double-entry ledger. Every business event writes balanced rows
 * (sum of debits == sum of credits) inside the caller's DB transaction.
 *
 * Accounts: gateway_receivable, creator_payable:{userId}, platform_revenue,
 *           gst_payable, tcs_payable, promo_expense
 */
class LedgerService
{
    public static function creatorAccount(int $userId): string { return "creator_payable:{$userId}"; }

    /** Payee: studio owner for studio bookings, otherwise the photographer. */
    public function payeeId(Booking $b): int
    {
        $b->loadMissing('photographer', 'studio');
        return $b->studio?->owner_id ?? $b->photographer->user_id;
    }

    public function recordCapture(Booking $b): void
    {
        $payee = $this->payeeId($b);
        $discount = $b->coupon_discount_paise + $b->loyalty_discount_paise;
        $rows = [
            ['gateway_receivable', 'debit', $b->total_amount_paise, 'payment'],
            ['promo_expense', 'debit', $discount, 'payment'],
            [self::creatorAccount($payee), 'credit', $b->creator_net_paise, 'payment'],
            ['platform_revenue', 'credit', $b->commission_paise, 'commission'],
            ['gst_payable', 'credit', $b->gst_on_commission_paise, 'gst'],
            ['tcs_payable', 'credit', $b->tcs_paise, 'tcs'],
        ];
        $this->write($b, $rows, $payee, 'Booking ' . $b->reference . ' captured');
    }

    /** Reverse a proportional slice of every leg for a (partial) refund. */
    public function recordRefund(Booking $b, int $refundPaise): void
    {
        $payee = $this->payeeId($b);
        $ratioOf = fn (int $part) => intdiv($part * $refundPaise, max($b->total_amount_paise, 1));

        $baseR = $ratioOf($b->base_amount_paise);
        $commissionR = $ratioOf($b->commission_paise);
        $gstR = $ratioOf($b->gst_on_commission_paise);
        $tcsR = $ratioOf($b->tcs_paise);
        $creatorR = $baseR - $commissionR - $gstR - $tcsR;   // remainder absorbs rounding
        $discountR = $baseR - $refundPaise;

        $rows = [
            [self::creatorAccount($payee), 'debit', $creatorR, 'refund'],
            ['platform_revenue', 'debit', $commissionR, 'refund_reversal'],
            ['gst_payable', 'debit', $gstR, 'refund_reversal'],
            ['tcs_payable', 'debit', $tcsR, 'refund_reversal'],
            ['promo_expense', 'credit', max($discountR, 0), 'refund'],
            ['gateway_receivable', 'credit', $refundPaise, 'refund'],
        ];
        $this->write($b, $rows, $payee, 'Refund for ' . $b->reference);
    }

    public function recordPayout(Booking|null $b, int $userId, int $settlementId, int $netPaise, string $ref): void
    {
        Ledger::insert([
            $this->row(self::creatorAccount($userId), 'debit', $netPaise, 'settlement', $userId, null, $settlementId, "Settlement {$ref}"),
            $this->row('gateway_receivable', 'credit', $netPaise, 'settlement', $userId, null, $settlementId, "Settlement {$ref}"),
        ]);
    }

    /** What is still owed to a creator for one booking (credits − debits, excl. settlements). */
    public function bookingPayable(int $bookingId, int $payeeId): int
    {
        $acct = self::creatorAccount($payeeId);
        return (int) Ledger::where('booking_id', $bookingId)->where('account', $acct)
            ->selectRaw("COALESCE(SUM(CASE direction WHEN 'credit' THEN amount_paise ELSE -amount_paise END),0) AS bal")
            ->value('bal');
    }

    private function write(Booking $b, array $rows, int $payee, string $desc): void
    {
        $debits = collect($rows)->where(1, 'debit')->sum(2);
        $credits = collect($rows)->where(1, 'credit')->sum(2);
        if ($debits !== $credits) {
            throw new \LogicException("Unbalanced ledger entry for booking {$b->id}: debits {$debits} != credits {$credits}");
        }
        $now = now();
        Ledger::insert(collect($rows)->filter(fn ($r) => $r[2] > 0)->map(
            fn ($r) => $this->row($r[0], $r[1], $r[2], $r[3], str_starts_with($r[0], 'creator_payable') ? $payee : null, $b->id, null, $desc, $now)
        )->values()->all());
    }

    private function row($account, $dir, $amt, $type, $userId, $bookingId, $settlementId, $desc, $now = null): array
    {
        $now ??= now();
        return [
            'account' => $account, 'direction' => $dir, 'amount_paise' => $amt, 'entry_type' => $type,
            'user_id' => $userId, 'booking_id' => $bookingId, 'settlement_id' => $settlementId,
            'description' => $desc, 'created_at' => $now, 'updated_at' => $now,
        ];
    }
}
