<?php

namespace App\Services\Payment;

use App\Exceptions\MarketplaceException;
use App\Models\{Booking, Invoice, Payment, Transaction};
use App\Services\Booking\BookingService;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tax\TaxService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        private RazorpayClient $gateway,
        private LedgerService $ledger,
        private TaxService $tax,
    ) {}

    /** BookingService/SubscriptionService are resolved lazily to avoid a circular dependency. */
    private function bookings(): BookingService { return app(BookingService::class); }
    private function subscriptions(): SubscriptionService { return app(SubscriptionService::class); }

    public function createOrderForBooking(Booking $booking): Payment
    {
        // Amount is ALWAYS taken from the server-side booking snapshot — never from the client.
        $order = $this->gateway->createOrder($booking->total_amount_paise, $booking->reference, ['booking_id' => $booking->id]);

        return Payment::create([
            'booking_id' => $booking->id, 'user_id' => $booking->customer_id,
            'razorpay_order_id' => $order['id'], 'amount_paise' => $booking->total_amount_paise,
            'status' => 'created', 'gateway_payload' => $order,
        ]);
    }

    /** Client-side checkout callback. Verifies signature, then runs the shared capture path. */
    public function handleCheckoutCallback(string $orderId, string $paymentId, string $signature): void
    {
        if (! $this->gateway->verifyPaymentSignature($orderId, $paymentId, $signature)) {
            throw new MarketplaceException('Payment verification failed.', 'bad_signature', 400);
        }
        $this->markCaptured($orderId, $paymentId);
    }

    /** Razorpay webhook (source of truth when the browser closes early). */
    public function handleWebhook(string $rawBody, string $signature): void
    {
        if (! $this->gateway->verifyWebhookSignature($rawBody, $signature)) {
            throw new MarketplaceException('Invalid webhook signature.', 'bad_signature', 400);
        }
        $event = json_decode($rawBody, true);
        $entity = data_get($event, 'payload.payment.entity');

        match ($event['event'] ?? null) {
            'payment.captured', 'order.paid' => $this->markCaptured(
                data_get($event, 'payload.order.entity.id', $entity['order_id'] ?? null),
                $entity['id'] ?? null,
                $entity['amount'] ?? null
            ),
            'payment.failed' => Payment::where('razorpay_order_id', $entity['order_id'] ?? '')->where('status', 'created')->update(['status' => 'failed']),
            default => null,
        };
    }

    /**
     * Idempotent: safe to call from both the callback and the webhook.
     * Routes to a booking payment or a subscription invoice by order id.
     */
    public function markCaptured(?string $orderId, ?string $gatewayPaymentId, ?int $amountPaise = null): void
    {
        if (! $orderId || ! $gatewayPaymentId) { return; }

        if ($invoice = Invoice::where('razorpay_order_id', $orderId)->first()) {
            if ($amountPaise !== null && $amountPaise !== (int) $invoice->total_paise) {
                throw new MarketplaceException('Amount mismatch.', 'amount_mismatch', 400);
            }
            $this->subscriptions()->activateFromInvoice($invoice, $gatewayPaymentId);
            return;
        }

        $payment = Payment::where('razorpay_order_id', $orderId)->firstOrFail();
        if ($amountPaise !== null && $amountPaise !== (int) $payment->amount_paise) {
            // Payment tampering / mismatch: do not confirm.
            Log::alert('Razorpay amount mismatch', compact('orderId', 'amountPaise') + ['expected' => $payment->amount_paise]);
            throw new MarketplaceException('Amount mismatch.', 'amount_mismatch', 400);
        }

        try {
            DB::transaction(function () use ($payment, $gatewayPaymentId) {
                $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
                if ($p->status === 'captured') { return; }   // already processed

                $p->update(['status' => 'captured', 'razorpay_payment_id' => $gatewayPaymentId]);
                Transaction::create(['payment_id' => $p->id, 'booking_id' => $p->booking_id, 'type' => 'payment', 'amount_paise' => $p->amount_paise, 'gateway_ref' => $gatewayPaymentId]);

                $booking = $this->bookings()->confirmAfterPayment($p->booking);
                $this->tax->issueInvoiceForBooking($booking);
            });
        } catch (MarketplaceException $e) {
            // Customer paid but we can't honour the booking (hold expired / slot lost) → refund in full.
            Log::warning('Auto-refunding unfulfillable payment', ['payment' => $payment->id, 'reason' => $e->getMessage()]);
            $p = Payment::find($payment->id);
            $p->update(['status' => 'captured', 'razorpay_payment_id' => $gatewayPaymentId]);
            $this->refundPaymentOnly($p, $p->amount_paise, 'Auto-refund: ' . $e->errorCode);
        }
    }

    /** Full / partial refund of a confirmed booking. Reverses commission, GST, TCS and updates ledgers. */
    public function refundBooking(Booking $booking, int $refundPaise, string $reason): void
    {
        if ($refundPaise <= 0) { return; }

        $payment = Payment::where('booking_id', $booking->id)->whereIn('status', ['captured', 'partially_refunded'])->latest('id')->firstOrFail();
        $remaining = $payment->amount_paise - $payment->refunded_paise;
        if ($refundPaise > $remaining) {
            throw new MarketplaceException('Refund exceeds the amount paid.', 'refund_too_large', 422);
        }

        // Gateway first; if it throws we have changed nothing locally.
        $res = $this->gateway->refund($payment->razorpay_payment_id, $refundPaise, ['booking' => $booking->reference, 'reason' => $reason]);

        DB::transaction(function () use ($booking, $payment, $refundPaise, $res) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->first();
            $p->refunded_paise += $refundPaise;
            $p->status = $p->refunded_paise >= $p->amount_paise ? 'refunded' : 'partially_refunded';
            $p->save();

            Transaction::create(['payment_id' => $p->id, 'booking_id' => $booking->id, 'type' => 'refund', 'amount_paise' => $refundPaise, 'gateway_ref' => $res['id'] ?? null]);
            $this->ledger->recordRefund($booking, $refundPaise);
            $this->tax->issueCreditNote($booking, $refundPaise);

            if ($p->status === 'refunded') {
                $b = Booking::whereKey($booking->id)->lockForUpdate()->first();
                if ($b->canTransitionTo(Booking::REFUNDED)) {
                    $this->bookings()->transition($b, Booking::REFUNDED, null, 'Fully refunded');
                }
            }
        });
    }

    /** Refund for a payment that never produced a confirmed booking (no ledger legs exist). */
    private function refundPaymentOnly(Payment $p, int $amount, string $reason): void
    {
        $res = $this->gateway->refund($p->razorpay_payment_id, $amount, ['reason' => $reason]);
        $p->update(['refunded_paise' => $amount, 'status' => 'refunded']);
        Transaction::create(['payment_id' => $p->id, 'booking_id' => $p->booking_id, 'type' => 'refund', 'amount_paise' => $amount, 'gateway_ref' => $res['id'] ?? null]);
    }
}
