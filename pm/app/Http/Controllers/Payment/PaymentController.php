<?php

namespace App\Http\Controllers\Payment;

use App\Exceptions\MarketplaceException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\VerifyPaymentRequest;
use App\Models\{Booking, Payment};
use App\Services\Payment\PaymentService;
use Illuminate\Http\{JsonResponse, Request, Response};

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /** POST /payments — returns the Razorpay order for an accepted booking. Amount is server-side only. */
    public function create(Request $r): JsonResponse
    {
        $booking = Booking::findOrFail($r->validate(['booking_id' => 'required|exists:bookings,id'])['booking_id']);
        $this->authorize('pay', $booking);

        if ($booking->status !== Booking::PENDING_PAYMENT || ($booking->payment_due_at && $booking->payment_due_at->isPast())) {
            throw new MarketplaceException('This booking is not awaiting payment.', 'not_payable', 409);
        }
        $payment = Payment::where('booking_id', $booking->id)->where('status', 'created')->latest('id')->first()
            ?? $this->payments->createOrderForBooking($booking);

        return response()->json([
            'order_id' => $payment->razorpay_order_id, 'amount' => $payment->amount_paise,
            'currency' => 'INR', 'key' => config('marketplace.razorpay.key_id'),
        ]);
    }

    public function verify(VerifyPaymentRequest $r): JsonResponse
    {
        $payment = Payment::where('razorpay_order_id', $r->razorpay_order_id)->firstOrFail();
        abort_unless($payment->user_id === $r->user()->id, 403);

        $this->payments->handleCheckoutCallback($r->razorpay_order_id, $r->razorpay_payment_id, $r->razorpay_signature);
        return response()->json(['status' => $payment->refresh()->status]);
    }

    /** Public, signature-verified, CSRF-exempt (api routes). Always 200 once verified so Razorpay stops retrying. */
    public function webhook(Request $r): Response
    {
        try {
            $this->payments->handleWebhook($r->getContent(), (string) $r->header('X-Razorpay-Signature'));
        } catch (MarketplaceException $e) {
            if ($e->errorCode === 'bad_signature') { return response('invalid', 400); }
            report($e);
        }
        return response('ok', 200);
    }
}
