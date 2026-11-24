<?php

namespace App\Services\Payment;

use App\Exceptions\MarketplaceException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** Thin Razorpay REST wrapper (no SDK dependency) with signature verification. */
class RazorpayClient
{
    private function http(): PendingRequest
    {
        return Http::baseUrl('https://api.razorpay.com/v1')
            ->withBasicAuth(config('marketplace.razorpay.key_id'), config('marketplace.razorpay.key_secret'))
            ->acceptJson()->asJson()->timeout(15)->retry(2, 200, throw: false);
    }

    public function createOrder(int $amountPaise, string $receipt, array $notes = []): array
    {
        $res = $this->http()->post('/orders', [
            'amount' => $amountPaise, 'currency' => config('marketplace.currency'),
            'receipt' => $receipt, 'notes' => $notes,
        ]);
        if ($res->failed()) {
            throw new MarketplaceException('Payment gateway unavailable. Please retry.', 'gateway_error', 502);
        }
        return $res->json();
    }

    public function refund(string $paymentId, int $amountPaise, array $notes = []): array
    {
        $res = $this->http()->post("/payments/{$paymentId}/refund", ['amount' => $amountPaise, 'notes' => $notes]);
        if ($res->failed()) {
            throw new MarketplaceException('Refund could not be processed by the gateway.', 'refund_failed', 502);
        }
        return $res->json();
    }

    /** RazorpayX payout to a creator's fund account. */
    public function payout(string $fundAccountId, int $amountPaise, string $reference): array
    {
        $res = $this->http()->withHeaders(['X-Payout-Idempotency' => $reference])->post('/payouts', [
            'account_number' => config('marketplace.razorpay.x_account_number'),
            'fund_account_id' => $fundAccountId, 'amount' => $amountPaise, 'currency' => 'INR',
            'mode' => 'IMPS', 'purpose' => 'payout', 'reference_id' => $reference,
        ]);
        if ($res->failed()) {
            throw new MarketplaceException('Payout failed: ' . ($res->json('error.description') ?? 'gateway error'), 'payout_failed', 502);
        }
        return $res->json();
    }

    /** Checkout callback: HMAC_SHA256(order_id|payment_id, key_secret). */
    public function verifyPaymentSignature(string $orderId, string $paymentId, string $signature): bool
    {
        $expected = hash_hmac('sha256', "{$orderId}|{$paymentId}", (string) config('marketplace.razorpay.key_secret'));
        return hash_equals($expected, $signature);
    }

    /** Webhook: HMAC_SHA256(raw_body, webhook_secret). */
    public function verifyWebhookSignature(string $rawBody, string $signature): bool
    {
        $expected = hash_hmac('sha256', $rawBody, (string) config('marketplace.razorpay.webhook_secret'));
        return hash_equals($expected, $signature);
    }
}
