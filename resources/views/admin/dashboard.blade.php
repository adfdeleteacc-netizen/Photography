@extends('layouts.marketplace')
@section('title', 'Admin')

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('admin', () => ({
        tab: 'overview', d: null, kyc: [], settlements: [], settleFilter: '', users: [], q: '', flags: [],
        commission: '', period: new Date().toISOString().slice(0, 7), tax: null,
        coupon: { code: '', type: 'percentage', value: 10, max_discount_paise: '', usage_limit: '', expires_at: '' },
        busy: false,

        async init() {
            this.d = await api('get', '/api/admin/dashboard');
            this.commission = '';
            await Promise.all([this.loadKyc(), this.loadSettlements(), this.loadUsers(), this.loadFlags()]);
        },
        async loadKyc() { const r = await api('get', '/api/admin/kyc'); if (r) this.kyc = r.data; },
        async reviewKyc(k, approve) {
            let reason = null;
            if (!approve) { reason = prompt('Reason for rejection?'); if (!reason) return; }
            const r = await api('post', `/api/admin/kyc/${k.id}/review`, { approve, reason });
            if (r) { notifyOk(approve ? 'Approved' : 'Rejected'); this.loadKyc(); this.d = await api('get', '/api/admin/dashboard'); }
        },
        async loadSettlements() { const r = await api('get', '/api/admin/settlements' + (this.settleFilter ? '?status=' + this.settleFilter : '')); if (r) this.settlements = r.data; },
        async generate() {
            const r = await api('post', '/api/admin/settlements/generate', { frequency: 'manual' });
            if (r) { notifyOk(r.created + ' settlement(s) created'); this.loadSettlements(); }
        },
        async approve(s) {
            if (!confirm(`Approve and pay ${inrPaise(s.net_paise)} to ${s.user?.name}?`)) return;
            this.busy = true;
            const r = await api('post', `/api/admin/settlements/${s.id}/approve`);
            this.busy = false;
            if (r) notifyOk('Payout ' + r.status); this.loadSettlements();
        },
        async setCommission() {
            const r = await api('post', '/api/admin/commission', { commission_percent: this.commission });
            if (r) notifyOk('Commission set to ' + r.commission_percent + '% for new bookings');
        },
        async loadTax() { this.tax = await api('get', '/api/admin/gst-reports?period=' + this.period); },
        async createCoupon() {
            const body = { ...this.coupon, max_discount_paise: this.coupon.max_discount_paise || null, usage_limit: this.coupon.usage_limit || null, expires_at: this.coupon.expires_at || null };
            if (body.max_discount_paise) body.max_discount_paise = Number(body.max_discount_paise) * 100;
            if (this.coupon.type === 'fixed') body.value = Number(body.value) * 100;
            const r = await api('post', '/api/admin/coupons', body);
            if (r) notifyOk('Coupon ' + r.code + ' created');
        },
        async loadUsers() { const r = await api('get', '/api/admin/users?q=' + encodeURIComponent(this.q)); if (r) this.users = r.data; },
        async suspend(u) { if (!confirm('Suspend ' + u.email + '?')) return; if (await api('post', `/api/admin/users/${u.id}/suspend`)) { notifyOk('Suspended'); this.loadUsers(); } },
        async loadFlags() { const r = await api('get', '/api/admin/fraud-flags'); if (r) this.flags = r.data; },
    }));
});
</script>
@endpush

@section('content')
<div x-data="admin()">
    <h1 class="text-xl font-semibold mb-5">Admin console</h1>

    <div class="flex gap-1 border-b mb-6 overflow-x-auto">
        <template x-for="[k, v] in [['overview','Overview'],['kyc','KYC queue'],['settlements','Settlements'],['pricing','Commission & coupons'],['tax','GST / TCS'],['users','Users'],['fraud','Fraud flags']]">
            <button class="tab" :class="tab === k && 'tab-active'" @click="tab = k; k === 'tax' && loadTax()" x-text="v"></button>
        </template>
    </div>

    {{-- Overview --}}
    <section x-show="tab === 'overview'" x-cloak>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" x-show="d">
            <template x-for="[label, val, money] in [
                ['GMV (30 days)', d?.gmv_30d, true], ['Commission revenue', d?.commission_revenue, true], ['GST payable', d?.gst_payable, true], ['TCS payable', d?.tcs_payable, true],
                ['Subscription revenue (30d)', d?.subscription_revenue_30d, true], ['Active subscriptions', d?.active_subscriptions, false],
                ['Pending KYC', d?.pending_kyc, false], ['Settlements to approve', d?.settlements_awaiting_approval, false]]">
                <div class="card p-4"><p class="text-xs text-slate-500" x-text="label"></p><p class="text-2xl font-semibold" x-text="money ? inr(val) : val"></p></div>
            </template>
        </div>
        <h3 class="font-semibold mt-8 mb-2">Bookings by status</h3>
        <div class="flex flex-wrap gap-2"><template x-for="(n, s) in (d?.bookings_by_status ?? {})"><span :class="statusClass(s)" x-text="statusLabel(s) + ': ' + n"></span></template></div>
    </section>

    {{-- KYC --}}
    <section x-show="tab === 'kyc'" x-cloak>
        <p class="text-sm text-slate-400" x-show="!kyc.length">No pending verifications 🎉</p>
        <div class="space-y-3"><template x-for="k in kyc" :key="k.id">
            <div class="card p-4 flex flex-wrap items-center justify-between gap-3">
                <div><b x-text="k.user?.name"></b> <span class="text-slate-400" x-text="k.user?.email"></span>
                    <p class="text-sm" x-text="k.type.toUpperCase() + (k.number ? ': ' + k.number : '')"></p></div>
                <div class="flex gap-2">
                    <a class="btn-ghost" x-show="k.file_path" :href="`/api/admin/kyc/${k.id}/file`" target="_blank">View document</a>
                    <button class="btn-ok" @click="reviewKyc(k, true)">Approve</button>
                    <button class="btn-danger" @click="reviewKyc(k, false)">Reject</button>
                </div>
            </div>
        </template></div>
        <p class="text-xs text-slate-400 mt-3">Approving a PAN also verifies the creator's bank account and profile badge. The document link opens via your admin session.</p>
    </section>

    {{-- Settlements --}}
    <section x-show="tab === 'settlements'" x-cloak>
        <div class="flex flex-wrap gap-2 mb-4">
            <select class="input !w-auto" x-model="settleFilter" @change="loadSettlements()">
                <option value="">All</option><option value="pending_approval">Awaiting approval</option><option value="paid">Paid</option><option value="failed">Failed</option>
            </select>
            <button class="btn-ghost" @click="generate()">Generate settlements now</button>
        </div>
        <p class="text-sm text-slate-400" x-show="!settlements.length">No settlements.</p>
        <div class="space-y-3"><template x-for="s in settlements" :key="s.id">
            <div class="card p-4">
                <div class="flex flex-wrap justify-between gap-2">
                    <div><b x-text="s.user?.name"></b> <span class="text-slate-400" x-text="s.reference"></span> <span :class="statusClass(s.status)" x-text="statusLabel(s.status)"></span></div>
                    <b x-text="inrPaise(s.net_paise)"></b>
                </div>
                <p class="text-xs text-slate-500 mt-1" x-text="`Gross ${inrPaise(s.gross_paise)} · commission ${inrPaise(s.commission_paise)} · GST ${inrPaise(s.gst_paise)} · TCS ${inrPaise(s.tcs_paise)}`"></p>
                <button class="btn-ok mt-3" x-show="s.status === 'pending_approval' || s.status === 'failed'" :disabled="busy" @click="approve(s)">Approve & pay</button>
            </div>
        </template></div>
    </section>

    {{-- Pricing --}}
    <section x-show="tab === 'pricing'" x-cloak class="grid lg:grid-cols-2 gap-6">
        <div class="card p-5">
            <h2 class="font-semibold mb-1">Platform commission</h2>
            <p class="text-xs text-slate-500 mb-3">Applies to <b>new</b> bookings only; existing bookings keep the rate they were created with.</p>
            <div class="flex gap-2"><input type="number" step="0.5" min="0" max="50" class="input" x-model="commission" placeholder="e.g. 20"><button class="btn-primary" :disabled="commission === ''" @click="setCommission()">Save %</button></div>
        </div>
        <div class="card p-5">
            <h2 class="font-semibold mb-3">Create coupon</h2>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label">Code</label><input class="input uppercase" x-model="coupon.code"></div>
                <div><label class="label">Type</label><select class="input" x-model="coupon.type"><option value="percentage">Percentage</option><option value="fixed">Fixed ₹</option></select></div>
                <div><label class="label" x-text="coupon.type === 'fixed' ? 'Value (₹)' : 'Value (%)'"></label><input type="number" min="1" class="input" x-model="coupon.value"></div>
                <div><label class="label">Max discount ₹ (optional)</label><input type="number" class="input" x-model="coupon.max_discount_paise"></div>
                <div><label class="label">Usage limit</label><input type="number" class="input" x-model="coupon.usage_limit"></div>
                <div><label class="label">Expires</label><input type="datetime-local" class="input" x-model="coupon.expires_at"></div>
            </div>
            <button class="btn-primary mt-3" :disabled="!coupon.code" @click="createCoupon()">Create coupon</button>
        </div>
    </section>

    {{-- Tax --}}
    <section x-show="tab === 'tax'" x-cloak>
        <div class="flex flex-wrap items-end gap-2 mb-4">
            <div><label class="label">Month</label><input type="month" class="input" x-model="period" @change="loadTax()"></div>
            <a class="btn-primary" :href="'/api/admin/gst-reports/gstr8.csv?period=' + period">Download GSTR-8 CSV</a>
        </div>
        <div class="grid sm:grid-cols-5 gap-3" x-show="tax">
            <template x-for="[k, v] in Object.entries(tax?.summary ?? {}).filter(([k]) => k !== 'period')">
                <div class="card p-3"><p class="text-xs text-slate-500" x-text="k.replaceAll('_', ' ')"></p><p class="font-semibold" x-text="inr(v)"></p></div>
            </template>
        </div>
        <table class="w-full text-sm mt-5 bg-white border rounded-xl overflow-hidden" x-show="tax?.gstr8?.length">
            <thead class="bg-slate-50 text-left text-xs text-slate-500"><tr><th class="p-2">Supplier GSTIN</th><th>Gross</th><th>Returns</th><th>Net</th><th>TCS</th></tr></thead>
            <tbody><template x-for="r in (tax?.gstr8 ?? [])"><tr class="border-t"><td class="p-2" x-text="r.supplier_gstin"></td><td x-text="inr(r.gross_supplies)"></td><td x-text="inr(r.returns)"></td><td x-text="inr(r.net_supplies)"></td><td x-text="inr(r.tcs_amount)"></td></tr></template></tbody>
        </table>
        <p class="text-xs text-slate-400 mt-3">Summary report for review — have your CA validate it against the official GSTR-8 template before filing.</p>
    </section>

    {{-- Users --}}
    <section x-show="tab === 'users'" x-cloak>
        <div class="flex gap-2 mb-4"><input class="input" placeholder="Search name or email" x-model="q" @keydown.enter="loadUsers()"><button class="btn-primary" @click="loadUsers()">Search</button></div>
        <div class="space-y-2"><template x-for="u in users" :key="u.id">
            <div class="card px-4 py-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                <span><b x-text="u.name"></b> <span class="text-slate-400" x-text="u.email"></span> <template x-for="r in u.roles"><span class="badge bg-slate-100 text-slate-600 ml-1" x-text="r.name"></span></template>
                    <span class="badge bg-rose-100 text-rose-700 ml-1" x-show="!u.is_active">suspended</span></span>
                <button class="btn-ghost !py-1" x-show="u.is_active && !u.roles.some(r => r.name === 'admin')" @click="suspend(u)">Suspend</button>
            </div>
        </template></div>
    </section>

    {{-- Fraud --}}
    <section x-show="tab === 'fraud'" x-cloak>
        <p class="text-sm text-slate-400" x-show="!flags.length">No open flags.</p>
        <ul class="space-y-2 text-sm"><template x-for="f in flags" :key="f.id"><li class="card px-4 py-3"><b x-text="'User #' + f.user_id"></b> — <span x-text="f.reason"></span> <span class="text-slate-400" x-text="'· ' + fmtDate(f.created_at)"></span></li></template></ul>
    </section>
</div>
@endsection
