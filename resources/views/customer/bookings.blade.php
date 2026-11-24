@extends('layouts.marketplace')
@section('title', 'My bookings')

@push('head')<script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>@endpush

@section('content')
<div x-data="{
    rows: [], loading: true, tab: 'all', paying: null, reviewing: null, rating: 5, body: '',
    me: @js(['name' => auth()->user()->name, 'email' => auth()->user()->email]),
    async load() { const r = await api('get', '/api/my-bookings'); if (r) this.rows = r.data; this.loading = false; },
    get shown() { return this.tab === 'all' ? this.rows : this.rows.filter(b => b.status === this.tab || (this.tab === 'open' && ['inquiry','pending_payment'].includes(b.status))); },
    async pay(b) {
        this.paying = b.id;
        const order = await api('post', '/api/payments', { booking_id: b.id });
        if (order) {
            const ok = await payWithRazorpay(order, { verifyUrl: '/api/payments/verify', name: '{{ config('app.name') }}', description: b.package?.title ?? 'Booking ' + b.reference, prefill: this.me });
            if (ok) notifyOk('Payment received — your booking is confirmed!');
        }
        this.paying = null; await this.load();
    },
    async cancel(b) {
        const warn = b.status === 'confirmed' ? 'This booking is paid. A refund is issued according to the cancellation policy. Continue?' : 'Cancel this request?';
        if (!confirm(warn)) return;
        const r = await api('post', `/api/bookings/${b.id}/cancel`, { reason: 'Cancelled by customer' });
        if (r) notifyOk('Booking cancelled'); await this.load();
    },
    async review(b) {
        const r = await api('post', '/api/reviews', { booking_id: b.id, rating: this.rating, body: this.body });
        if (r) { notifyOk('Thanks for your review!'); this.reviewing = null; this.body = ''; await this.load(); }
    },
    left(b) { if (!b.payment_due_at) return ''; const m = Math.max(0, Math.round((new Date(b.payment_due_at) - Date.now()) / 60000)); return m + ' min left to pay'; }
}" x-init="load()">

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-xl font-semibold">My bookings</h1>
        <a href="{{ route('home') }}" class="btn-primary">+ New booking</a>
    </div>

    <div class="flex gap-1 border-b mb-5 overflow-x-auto">
        @foreach(['all' => 'All', 'open' => 'Awaiting', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $k => $v)
            <button class="tab" :class="tab === '{{ $k }}' && 'tab-active'" @click="tab = '{{ $k }}'">{{ $v }}</button>
        @endforeach
    </div>

    <p x-show="loading" class="text-slate-400 text-sm">Loading…</p>
    <p x-show="!loading && !shown.length" class="text-center text-slate-400 py-12" x-cloak>Nothing here yet.</p>

    <div class="space-y-3">
        <template x-for="b in shown" :key="b.id">
            <div class="card p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <b x-text="b.photographer?.display_name"></b>
                            <span :class="statusClass(b.status)" x-text="statusLabel(b.status)"></span>
                        </div>
                        <p class="text-sm text-slate-500" x-text="(b.package?.title ?? '') + ' · ' + fmtDate(b.starts_at)"></p>
                        <p class="text-xs text-slate-400" x-text="'Ref ' + b.reference"></p>
                        <p class="text-xs text-sky-700 mt-1" x-show="b.status === 'pending_payment'" x-text="left(b)"></p>
                    </div>
                    <div class="text-right">
                        <p class="font-semibold" x-text="inrPaise(b.total_amount_paise)"></p>
                        <p class="text-xs text-slate-400" x-show="b.coupon_discount_paise + b.loyalty_discount_paise > 0"
                           x-text="'saved ' + inrPaise(b.coupon_discount_paise + b.loyalty_discount_paise)"></p>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button class="btn-primary" x-show="b.status === 'pending_payment'" :disabled="paying === b.id" @click="pay(b)" x-text="paying === b.id ? 'Opening…' : 'Pay now'"></button>
                    <button class="btn-ghost" x-show="['inquiry','pending_payment','confirmed'].includes(b.status)" @click="cancel(b)">Cancel</button>
                    <button class="btn-ghost" x-show="b.status === 'completed' && !b.review" @click="reviewing = reviewing === b.id ? null : b.id">⭐ Review</button>
                    <span class="text-sm" x-show="b.review" x-text="'Your rating: ' + '⭐'.repeat(b.review?.rating ?? 0)"></span>
                    <template x-if="!['cancelled','refunded'].includes(b.status)"><x-chat-button /></template>
                </div>

                <div x-show="reviewing === b.id" x-cloak class="mt-3 border-t pt-3 space-y-2">
                    <div class="flex gap-1">
                        <template x-for="n in 5"><button type="button" class="text-2xl" :class="n <= rating ? '' : 'opacity-30'" @click="rating = n">⭐</button></template>
                    </div>
                    <textarea rows="2" class="input" x-model="body" placeholder="How was your experience?"></textarea>
                    <button class="btn-primary" @click="review(b)">Submit review</button>
                </div>
            </div>
        </template>
    </div>
</div>
@endsection
