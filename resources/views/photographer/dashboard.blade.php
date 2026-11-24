@extends('layouts.marketplace')
@section('title', 'Creator dashboard')

@section('content')
<div x-data="{ d: null, e: null, err: null }"
     x-init="axios.get('/api/dashboard').then(r => d = r.data).catch(x => err = x.response?.data?.message ?? 'Failed to load');
             axios.get('/api/earnings').then(r => e = r.data)">
    <p x-show="err" x-text="err" class="text-red-600 mb-4" x-cloak></p>
    <template x-if="d">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="bg-white border rounded-xl p-4"><p class="text-sm text-slate-500">Plan</p><p class="text-lg font-semibold" x-text="d.subscription?.plan?.name ?? 'None'"></p></div>
            <div class="bg-white border rounded-xl p-4"><p class="text-sm text-slate-500">Pending requests</p><p class="text-lg font-semibold" x-text="d.pending_requests"></p></div>
            <div class="bg-white border rounded-xl p-4"><p class="text-sm text-slate-500">Bookings this month</p><p class="text-lg font-semibold" x-text="d.month_bookings"></p></div>
            <div class="bg-white border rounded-xl p-4"><p class="text-sm text-slate-500">Balance owed to you</p><p class="text-lg font-semibold" x-text="e ? inr(e.balance_owed) : '…'"></p></div>
        </div>
    </template>
    <template x-if="d && !d.kyc_verified"><p class="mt-4 bg-amber-50 border border-amber-200 rounded p-3 text-sm">Complete KYC to receive payouts.</p></template>
    <h2 class="mt-8 mb-2 font-semibold">Upcoming shoots</h2>
    <ul class="space-y-2"><template x-for="b in (d?.upcoming ?? [])" :key="b.id">
        <li class="bg-white border rounded p-3 text-sm flex justify-between"><span x-text="b.reference"></span><span x-text="new Date(b.starts_at).toLocaleString('en-IN')"></span></li>
    </template></ul>
</div>
@endsection
