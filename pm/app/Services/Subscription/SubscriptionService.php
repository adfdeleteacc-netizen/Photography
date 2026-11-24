<?php

namespace App\Services\Subscription;

use App\Exceptions\MarketplaceException;
use App\Models\{Booking, Invoice, Subscription, SubscriptionPlan, User};
use App\Services\Payment\RazorpayClient;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function __construct(private RazorpayClient $gateway) {}

    /** Start a free trial (once per user) for plans that offer one. */
    public function startTrial(User $user, SubscriptionPlan $plan): Subscription
    {
        if ($plan->trial_days <= 0) {
            throw new MarketplaceException('This plan has no trial.', 'no_trial', 422);
        }
        if ($user->subscriptions()->exists()) {
            throw new MarketplaceException('Trial already used.', 'trial_used', 422);
        }
        return Subscription::create([
            'user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => 'trialing',
            'starts_at' => now(), 'trial_ends_at' => now()->addDays($plan->trial_days),
            'ends_at' => now()->addDays($plan->trial_days), 'auto_renew' => false,
        ]);
    }

    /** Create the invoice + Razorpay order for buying / upgrading to a plan. Activation happens on capture. */
    public function checkout(User $user, SubscriptionPlan $plan): Invoice
    {
        if (! $plan->is_active) { throw new MarketplaceException('Plan unavailable.', 'plan_inactive', 422); }
        if ($plan->for_studios && ! $user->hasRole('studio')) {
            throw new MarketplaceException('This plan is for studios.', 'wrong_role', 403);
        }

        $current = $user->activeSubscription();
        $credit = $current && $current->status === 'active' ? $this->proratedCredit($current) : 0;
        $amount = max($plan->price_paise - $credit, 0);
        $gst = (int) round($amount * config('marketplace.gst_percent') / 100);

        $invoice = Invoice::create([
            'number' => 'TMP', 'user_id' => $user->id, 'amount_paise' => $amount, 'gst_paise' => $gst,
            'total_paise' => $amount + $gst, 'status' => 'pending', 'subscription_plan_id' => $plan->id,
        ]);
        $invoice->update(['number' => sprintf('SUB/%s/%06d', now()->format('Y'), $invoice->id)]);
        $order = $this->gateway->createOrder($invoice->total_paise, $invoice->number, ['invoice_id' => $invoice->id, 'plan_id' => $plan->id]);
        $invoice->update(['razorpay_order_id' => $order['id']]);
        return $invoice;
    }

    /** Called from PaymentService on a verified, matching capture. Idempotent. */
    public function activateFromInvoice(Invoice $invoice, string $gatewayPaymentId): Subscription
    {
        $sub = DB::transaction(function () use ($invoice, $gatewayPaymentId) {
            $inv = Invoice::whereKey($invoice->id)->lockForUpdate()->first();
            if ($inv->status === 'paid') {
                return Subscription::findOrFail($inv->subscription_id);
            }
            $plan = SubscriptionPlan::findOrFail($inv->subscription_plan_id);
            $user = User::findOrFail($inv->user_id);

            // Upgrade: close the old subscription immediately.
            $user->subscriptions()->whereIn('status', ['trialing', 'active', 'past_due'])->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            $start = now();
            $sub = Subscription::create([
                'user_id' => $user->id, 'subscription_plan_id' => $plan->id, 'status' => 'active',
                'starts_at' => $start, 'ends_at' => $plan->interval === 'annual' ? $start->copy()->addYear() : $start->copy()->addMonth(),
                'auto_renew' => true,
            ]);
            $inv->update(['status' => 'paid', 'paid_at' => now(), 'razorpay_payment_id' => $gatewayPaymentId, 'subscription_id' => $sub->id]);
            return $sub;
        });
        // Creator referrals reward on first paid subscription.
        app(\App\Services\Auth\AccountService::class)->rewardReferralIfFirst($sub->user);
        return $sub;
    }

    /** Downgrade takes effect at the end of the current period. */
    public function scheduleDowngrade(User $user, SubscriptionPlan $plan): Subscription
    {
        $sub = $user->activeSubscription() ?? throw new MarketplaceException('No active subscription.', 'no_subscription', 422);
        $sub->update(['pending_plan_id' => $plan->id]);
        return $sub;
    }

    public function cancel(User $user): void
    {
        $user->subscriptions()->whereIn('status', ['trialing', 'active', 'past_due'])->update(['auto_renew' => false]);
    }

    /** Scheduler: renew, move to grace (past_due), expire, send reminders. */
    public function processLifecycle(): array
    {
        $stats = ['renewal_invoices' => 0, 'past_due' => 0, 'expired' => 0, 'reminders' => 0];
        $grace = config('marketplace.subscription.grace_days');

        // 1. Period ended → grace window (or expire immediately if not auto-renewing)
        Subscription::whereIn('status', ['trialing', 'active'])->where('ends_at', '<', now())->each(function (Subscription $s) use ($grace, &$stats) {
            if ($s->pending_plan_id && $s->status === 'active') {
                $s->update(['subscription_plan_id' => $s->pending_plan_id, 'pending_plan_id' => null]);
            }
            if ($s->auto_renew) {
                $s->update(['status' => 'past_due', 'grace_ends_at' => $s->ends_at->copy()->addDays($grace)]);
                try {
                    $this->checkout($s->user, $s->plan);   // invoice + order; creator is emailed the pay link
                    $stats['renewal_invoices']++;
                } catch (\Throwable $e) {
                    report($e);                            // one gateway failure must not stop the whole run
                }
                $stats['past_due']++;
            } else {
                $s->update(['status' => 'expired']);
                $stats['expired']++;
            }
        });

        // 2. Grace over and still unpaid → expired
        $stats['expired'] += Subscription::where('status', 'past_due')->where('grace_ends_at', '<', now())->update(['status' => 'expired']);

        // 3. Expiry reminders (each threshold sent once)
        foreach (config('marketplace.subscription.reminder_days') as $days) {
            Subscription::where('status', 'active')->whereBetween('ends_at', [now()->addDays($days)->startOfDay(), now()->addDays($days)->endOfDay()])
                ->each(function (Subscription $s) use ($days, &$stats) {
                    $sent = $s->reminders_sent ?? [];
                    if (! in_array($days, $sent, true)) {
                        $s->user->notify(new \App\Notifications\SubscriptionExpiring($s, $days));
                        $s->update(['reminders_sent' => array_merge($sent, [$days])]);
                        $stats['reminders']++;
                    }
                });
        }
        return $stats;
    }

    // ---- plan limits -------------------------------------------------------

    public function assertBookingQuota(User $creator): void
    {
        $plan = $creator->activeSubscription()?->plan;
        if (! $plan || $plan->max_bookings_per_month === null) { return; }

        $profileIds = $creator->photographerProfile ? [$creator->photographerProfile->id] : [];
        if ($creator->studio) { $profileIds = array_merge($profileIds, $creator->studio->photographers()->pluck('id')->all()); }

        $count = Booking::whereIn('photographer_profile_id', $profileIds)
            ->whereIn('status', [Booking::PENDING_PAYMENT, Booking::CONFIRMED, Booking::COMPLETED])
            ->where('created_at', '>=', now()->startOfMonth())->count();

        if ($count >= $plan->max_bookings_per_month) {
            throw new MarketplaceException('This creator has reached their monthly booking limit.', 'quota_reached', 422);
        }
    }

    public function assertPortfolioQuota(User $creator, int $incomingFiles = 1): void
    {
        $plan = $creator->activeSubscription()?->plan
            ?? throw new MarketplaceException('An active subscription is required.', 'subscription_required', 402);
        if ($plan->max_portfolio_uploads === null) { return; }

        $owner = $creator->hasRole('studio') ? $creator->studio : $creator->photographerProfile;
        $used = \App\Models\PortfolioMedia::whereHas('portfolio', fn ($q) => $q->where('owner_type', $owner::class)->where('owner_id', $owner->id))->count();

        if ($used + $incomingFiles > $plan->max_portfolio_uploads) {
            throw new MarketplaceException("Your plan allows {$plan->max_portfolio_uploads} portfolio uploads. Upgrade to add more.", 'upload_limit', 402);
        }
    }

    public function proratedCredit(Subscription $s): int
    {
        $total = max($s->starts_at->diffInSeconds($s->ends_at), 1);
        $left = max(now()->diffInSeconds($s->ends_at, false), 0);
        return (int) floor($s->plan->price_paise * ($left / $total));
    }
}
