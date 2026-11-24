<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StorePlanRequest;
use App\Models\{Booking, Coupon, FraudFlag, KycDocument, Ledger, Setting, Settlement, SubscriptionPlan, User};
use App\Services\Auth\AccountService;
use App\Services\Settlement\SettlementService;
use App\Services\Tax\TaxService;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $sum = fn (string $acct) => (int) Ledger::where('account', $acct)->selectRaw("COALESCE(SUM(CASE direction WHEN 'credit' THEN amount_paise ELSE -amount_paise END),0) v")->value('v') / 100;

        return response()->json([
            'gmv_30d' => Booking::whereIn('status', ['confirmed', 'completed'])->where('confirmed_at', '>=', now()->subDays(30))->sum('total_amount_paise') / 100,
            'commission_revenue' => $sum('platform_revenue'),
            'gst_payable' => $sum('gst_payable'), 'tcs_payable' => $sum('tcs_payable'),
            'subscription_revenue_30d' => \App\Models\Invoice::where('status', 'paid')->where('paid_at', '>=', now()->subDays(30))->sum('amount_paise') / 100,
            'active_subscriptions' => \App\Models\Subscription::whereIn('status', ['active', 'trialing'])->count(),
            'pending_kyc' => KycDocument::where('status', 'pending')->count(),
            'settlements_awaiting_approval' => Settlement::where('status', 'pending_approval')->count(),
            'open_fraud_flags' => FraudFlag::where('status', 'open')->count(),
            'bookings_by_status' => Booking::selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    // ---- commission ------------------------------------------------------
    public function setCommission(Request $r): JsonResponse
    {
        $d = $r->validate(['commission_percent' => 'required|numeric|between:0,50']);
        Setting::put('commission_percent', $d['commission_percent']);   // applies to NEW bookings; existing ones keep their snapshot
        return response()->json($d);
    }

    // ---- plans -----------------------------------------------------------
    public function storePlan(StorePlanRequest $r): JsonResponse
    {
        return response()->json(SubscriptionPlan::create($r->validated()), 201);
    }

    public function updatePlan(Request $r, SubscriptionPlan $plan): JsonResponse
    {
        $plan->update($r->validate(['price_paise' => 'integer|min:0', 'is_active' => 'boolean', 'max_portfolio_uploads' => 'nullable|integer', 'max_bookings_per_month' => 'nullable|integer', 'max_team_members' => 'nullable|integer']));
        return response()->json($plan);
    }

    // ---- KYC -------------------------------------------------------------
    public function kycQueue(): JsonResponse
    {
        return response()->json(KycDocument::with('user:id,name,email')->where('status', 'pending')->oldest()->paginate(30));
    }

    public function reviewKyc(Request $r, KycDocument $doc, AccountService $accounts): JsonResponse
    {
        $d = $r->validate(['approve' => 'required|boolean', 'reason' => 'required_if:approve,false|nullable|string|max:300']);
        return response()->json($accounts->reviewKyc($doc, $r->user(), $d['approve'], $d['reason'] ?? null));
    }

    public function kycFile(KycDocument $doc)
    {
        abort_unless($doc->file_path, 404);
        return Storage::disk('local')->response($doc->file_path);   // never publicly linked
    }

    // ---- settlements -----------------------------------------------------
    public function settlements(Request $r): JsonResponse
    {
        return response()->json(Settlement::with('user:id,name')->when($r->status, fn ($q, $s) => $q->where('status', $s))->latest()->paginate(30));
    }

    public function generateSettlements(Request $r, SettlementService $svc): JsonResponse
    {
        $d = $r->validate(['frequency' => 'required|in:daily,weekly,manual', 'user_id' => 'nullable|exists:users,id']);
        return response()->json(['created' => $svc->generate($d['frequency'], $d['user_id'] ?? null)]);
    }

    public function approveSettlement(Request $r, Settlement $settlement, SettlementService $svc): JsonResponse
    {
        $this->authorize('approve', $settlement);
        $svc->approve($settlement, $r->user());
        return response()->json($svc->disburse($settlement->refresh()));
    }

    // ---- tax -------------------------------------------------------------
    public function gstReports(Request $r, TaxService $tax): JsonResponse
    {
        $period = $r->validate(['period' => 'required|date_format:Y-m'])['period'];
        return response()->json(['summary' => $tax->summary($period), 'gstr8' => $tax->gstr8Rows($period)]);
    }

    public function exportGstr8(Request $r, TaxService $tax)
    {
        $period = $r->validate(['period' => 'required|date_format:Y-m'])['period'];
        $report = $tax->exportGstr8Csv($period, $r->user()->id);
        return Storage::disk('local')->download($report->file_path, "gstr8-{$period}.csv");
    }

    // ---- coupons / users / fraud ----------------------------------------
    public function storeCoupon(Request $r): JsonResponse
    {
        $d = $r->validate([
            'code' => 'required|alpha_dash|max:40|unique:coupons,code', 'type' => 'required|in:percentage,fixed',
            'value' => 'required|integer|min:1', 'max_discount_paise' => 'nullable|integer|min:1',
            'min_order_paise' => 'nullable|integer|min:0', 'usage_limit' => 'nullable|integer|min:1',
            'per_user_limit' => 'nullable|integer|min:1', 'starts_at' => 'nullable|date', 'expires_at' => 'nullable|date|after:starts_at',
        ]);
        abort_if($d['type'] === 'percentage' && $d['value'] > 100, 422, 'Percentage cannot exceed 100.');
        return response()->json(Coupon::create(['code' => strtoupper($d['code'])] + $d), 201);
    }

    public function users(Request $r): JsonResponse
    {
        return response()->json(User::with('roles:id,name')->when($r->q, fn ($q, $v) => $q->where('email', 'like', "%{$v}%")->orWhere('name', 'like', "%{$v}%"))->paginate(30));
    }

    public function suspendUser(User $user): JsonResponse
    {
        abort_if($user->hasRole('admin'), 403);
        $user->update(['is_active' => false]);
        $user->tokens()->delete();
        return response()->json($user);
    }

    public function fraudFlags(): JsonResponse
    {
        return response()->json(FraudFlag::where('status', 'open')->latest()->paginate(30));
    }
}
