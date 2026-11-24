<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\CheckoutRequest;
use App\Models\SubscriptionPlan;
use App\Models\Invoice;
use App\Services\Payment\PaymentService;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Http\{JsonResponse, Request};

class SubscriptionController extends Controller
{
    public function __construct(private SubscriptionService $subs) {}

    public function plans(Request $r): JsonResponse
    {
        $q = SubscriptionPlan::where('is_active', true)->orderBy('price_paise');
        if (! $r->user()?->hasRole('studio')) { $q->where('for_studios', false); }
        return response()->json($q->get());
    }

    public function current(Request $r): JsonResponse
    {
        return response()->json($r->user()->activeSubscription()?->load('plan'));
    }

    public function trial(CheckoutRequest $r): JsonResponse
    {
        return response()->json($this->subs->startTrial($r->user(), SubscriptionPlan::findOrFail($r->plan_id)), 201);
    }

    /** Returns a Razorpay order; the subscription activates when payment is captured. */
    public function checkout(CheckoutRequest $r): JsonResponse
    {
        $inv = $this->subs->checkout($r->user(), SubscriptionPlan::findOrFail($r->plan_id));
        return response()->json(['invoice' => $inv->number, 'order_id' => $inv->razorpay_order_id, 'amount' => $inv->total_paise, 'currency' => 'INR', 'key' => config('marketplace.razorpay.key_id')]);
    }

    /** Verify Razorpay subscription checkout payment. */
    public function verify(Request $r, PaymentService $payments): JsonResponse
    {
        $d = $r->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $invoice = Invoice::where('razorpay_order_id', $d['razorpay_order_id'])
            ->where('user_id', $r->user()->id)
            ->firstOrFail();

        $payments->handleCheckoutCallback(
            $d['razorpay_order_id'],
            $d['razorpay_payment_id'],
            $d['razorpay_signature']
        );

        return response()->json(['status' => $invoice->refresh()->status]);
    }

    public function downgrade(CheckoutRequest $r): JsonResponse
    {
        return response()->json($this->subs->scheduleDowngrade($r->user(), SubscriptionPlan::findOrFail($r->plan_id)));
    }

    public function cancel(Request $r): JsonResponse
    {
        $this->subs->cancel($r->user());
        return response()->json(['auto_renew' => false]);
    }

    public function invoices(Request $r): JsonResponse
    {
        return response()->json(\App\Models\Invoice::where('user_id', $r->user()->id)->latest()->paginate(20));
    }
}
