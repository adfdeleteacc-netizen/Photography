<?php

namespace App\Http\Controllers\Photographer;

use App\Http\Controllers\Controller;
use App\Models\{Booking, Ledger, Settlement};
use App\Services\Payment\LedgerService;
use Illuminate\Http\{JsonResponse, Request};

class DashboardController extends Controller
{
    public function __construct(private LedgerService $ledger) {}

    public function dashboard(Request $r): JsonResponse
    {
        $u = $r->user();
        $ids = $this->profileIds($u);
        $sub = $u->activeSubscription();

        return response()->json([
            'subscription' => $sub?->load('plan'),
            'upcoming' => Booking::whereIn('photographer_profile_id', $ids)->where('status', Booking::CONFIRMED)->where('starts_at', '>=', now())->orderBy('starts_at')->limit(10)->get(),
            'pending_requests' => Booking::whereIn('photographer_profile_id', $ids)->where('status', Booking::INQUIRY)->count(),
            'month_bookings' => Booking::whereIn('photographer_profile_id', $ids)->whereIn('status', [Booking::CONFIRMED, Booking::COMPLETED])->where('created_at', '>=', now()->startOfMonth())->count(),
            'kyc_verified' => $u->isKycVerified(),
        ]);
    }

    public function earnings(Request $r): JsonResponse
    {
        $acct = LedgerService::creatorAccount($r->user()->id);
        $bal = fn ($q) => ((int) $q->selectRaw("COALESCE(SUM(CASE direction WHEN 'credit' THEN amount_paise ELSE -amount_paise END),0) v")->value('v')) / 100;

        return response()->json([
            'currency' => 'INR',
            'balance_owed' => $bal(Ledger::where('account', $acct)),                 // earned − paid out
            'lifetime_paid_out' => Settlement::where('user_id', $r->user()->id)->where('status', 'paid')->sum('net_paise') / 100,
            'recent_entries' => Ledger::where('account', $acct)->latest()->limit(50)->get(),
        ]);
    }

    public function settlements(Request $r): JsonResponse
    {
        return response()->json(Settlement::where('user_id', $r->user()->id)->latest()->paginate(20));
    }

    private function profileIds($u): array
    {
        $ids = $u->photographerProfile ? [$u->photographerProfile->id] : [];
        if ($u->studio) { $ids = array_merge($ids, $u->studio->photographers()->pluck('id')->all()); }
        return $ids;
    }
}
