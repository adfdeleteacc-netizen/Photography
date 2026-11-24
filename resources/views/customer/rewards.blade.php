@extends('layouts.marketplace')
@section('title', 'Rewards')

@section('content')
<div x-data="{ me: null }" x-init="api('get', '/api/me').then(r => me = r)" class="max-w-xl">
    <h1 class="text-xl font-semibold mb-4">Rewards & referrals</h1>
    <template x-if="me">
        <div class="space-y-4">
            <div class="card p-5">
                <p class="text-sm text-slate-500">Loyalty points</p>
                <p class="text-3xl font-bold text-indigo-700" x-text="me.loyalty_points"></p>
                <p class="text-xs text-slate-400 mt-1">1 point = ₹1 off, up to 20% of a booking. Earn points on every completed booking.</p>
            </div>
            <div class="card p-5">
                <p class="text-sm text-slate-500">Your referral link</p>
                <div class="flex gap-2 mt-2">
                    <input readonly class="input" :value="location.origin + '/register?ref=' + me.referral_code">
                    <button class="btn-primary" @click="navigator.clipboard.writeText(location.origin + '/register?ref=' + me.referral_code); notifyOk('Link copied')">Copy</button>
                </div>
                <p class="text-xs text-slate-400 mt-2">Earn bonus points when a friend completes their first booking.</p>
            </div>
        </div>
    </template>
</div>
@endsection
