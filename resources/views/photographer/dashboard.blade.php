@extends('layouts.marketplace')
@section('title', 'Creator workspace')

@push('head')<script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>@endpush

@push('scripts')
<script>
document.addEventListener('alpine:init', () => {
    const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    Alpine.data('creator', () => ({
        tab: 'requests', me: null, dash: null, needsPlan: false, loading: true,
        bookings: [], filter: 'inquiry,pending_payment,confirmed',
        // availability
        week: DAYS.map((d, i) => ({ day: i, name: d, on: i >= 1 && i <= 6, start: '09:00', end: '20:00' })), blocked: [], blk: { starts_at: '', ends_at: '', reason: '' }, noProfile: false,
        // portfolio
        portfolios: [], pf: { title: '', category_id: '', type: 'wedding', description: '' }, files: [], uploading: false,
        // plans
        plans: [], interval: 'monthly', sub: null, invoices: [], buying: null,
        // kyc
        kyc: { pan: '', gstin: '', holder: '', account: '', ifsc: '' }, kycSaving: false,
        // earnings
        earn: null, settlements: [],

        async init() {
            this.me = await api('get', '/api/me');
            try { this.dash = (await axios.get('/api/dashboard')).data; }
            catch (e) { if (e.response?.status === 402) { this.needsPlan = true; this.tab = 'plan'; } else notifyErr(e); }
            this.loading = false;
            await Promise.all([this.loadBookings(), this.loadPlans(), this.loadEarnings()]);
            if (!this.needsPlan) { this.loadAvailability(); this.loadPortfolios(); }
        },

        // ---- requests ----
        async loadBookings() { if (this.needsPlan) return; const r = await api('get', '/api/creator/bookings?status=' + this.filter); if (r) this.bookings = r.data; },
        async act(b, action) {
            const label = { accept: 'Accept', reject: 'Decline', complete: 'Mark as completed', cancel: 'Cancel' }[action];
            if (['reject', 'cancel', 'complete'].includes(action) && !confirm(label + ' this booking?')) return;
            const r = await api('post', `/api/bookings/${b.id}/${action}`, action === 'reject' || action === 'cancel' ? { reason: 'By creator' } : {});
            if (r) { notifyOk('Done'); await this.loadBookings(); this.dash = (await axios.get('/api/dashboard')).data; }
        },

        // ---- availability ----
        async loadAvailability() {
            try {
                const r = (await axios.get('/api/availability')).data;
                this.blocked = r.blocked;
                if (r.slots.length) this.week.forEach(w => { const s = r.slots.find(x => x.day_of_week === w.day); w.on = !!s; if (s) { w.start = s.start_time.slice(0, 5); w.end = s.end_time.slice(0, 5); } });
            } catch (e) { if (e.response?.status === 404) this.noProfile = true; }
        },
        async saveHours() {
            const slots = this.week.filter(w => w.on).map(w => ({ day_of_week: w.day, start_time: w.start, end_time: w.end }));
            const r = await api('post', '/api/availability', { slots });
            if (r) notifyOk('Working hours saved');
        },
        async addBlock() {
            const r = await api('post', '/api/availability', { blocked: [{ ...this.blk, reason: this.blk.reason || null }] });
            if (r) { notifyOk('Dates blocked'); this.blk = { starts_at: '', ends_at: '', reason: '' }; this.blocked = r.blocked; }
        },
        async unblock(b) { if (b.booking_id) return; await api('delete', `/api/availability/blocked/${b.id}`); this.blocked = this.blocked.filter(x => x.id !== b.id); },

        // ---- portfolio ----
        async loadPortfolios() { const r = await api('get', '/api/portfolio'); if (r) this.portfolios = r; },
        async createPortfolio() {
            this.uploading = true;
            const fd = new FormData();
            Object.entries(this.pf).forEach(([k, v]) => v && fd.append(k, v));
            [...this.files].forEach(f => fd.append('files[]', f));
            const r = await api('post', '/api/portfolio', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
            this.uploading = false;
            if (r) { notifyOk('Portfolio created. Thumbnails appear once the media queue worker runs.'); this.pf.title = ''; this.files = []; this.$refs.files.value = ''; this.loadPortfolios(); }
        },
        async delPortfolio(p) { if (!confirm('Delete "' + p.title + '"?')) return; await api('delete', `/api/portfolio/${p.id}`); this.loadPortfolios(); },

        // ---- plans ----
        async loadPlans() {
            const [plans, sub, inv] = await Promise.all([api('get', '/api/subscription/plans'), api('get', '/api/subscription'), api('get', '/api/subscription/invoices')]);
            if (plans) this.plans = plans; this.sub = sub || null; if (inv) this.invoices = inv.data;
        },
        get shownPlans() { return this.plans.filter(p => p.interval === this.interval); },
        async trial(p) { const r = await api('post', '/api/subscription/trial', { plan_id: p.id }); if (r) { notifyOk('Trial started!'); location.reload(); } },
        async buy(p) {
            this.buying = p.id;
            const o = await api('post', '/api/subscription/checkout', { plan_id: p.id });
            if (o) {
                const ok = await payWithRazorpay(o, { verifyUrl: '/api/subscription/verify', name: '{{ config('app.name') }}', description: p.name + ' (' + p.interval + ')', prefill: { name: this.me?.name, email: this.me?.email } });
                if (ok) { notifyOk('Subscription activated!'); location.reload(); }
            }
            this.buying = null;
        },
        async stopRenew() { if (!confirm('Turn off auto-renew? You keep access until the period ends.')) return; if (await api('post', '/api/subscription/cancel')) { notifyOk('Auto-renew off'); this.loadPlans(); } },
        limit(v) { return v === null ? 'Unlimited' : v; },

        // ---- kyc ----
        async submitKyc() {
            const fd = new FormData();
            fd.append('pan', this.kyc.pan.toUpperCase());
            if (this.kyc.gstin) fd.append('gstin', this.kyc.gstin.toUpperCase());
            fd.append('bank[account_holder]', this.kyc.holder); fd.append('bank[account_number]', this.kyc.account); fd.append('bank[ifsc]', this.kyc.ifsc.toUpperCase());
            fd.append('pan_file', this.$refs.panFile.files[0] ?? ''); fd.append('identity_file', this.$refs.idFile.files[0] ?? '');
            this.kycSaving = true;
            const r = await api('post', '/api/kyc', fd, { headers: { 'Content-Type': 'multipart/form-data' } });
            this.kycSaving = false;
            if (r) { notifyOk('Submitted for review'); this.me = await api('get', '/api/me'); }
        },

        // ---- earnings ----
        async loadEarnings() { [this.earn, this.settlements] = await Promise.all([api('get', '/api/earnings'), api('get', '/api/settlements').then(r => r?.data ?? [])]); },
    }));
});
</script>
@endpush

@section('content')
<div x-data="creator()">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
        <h1 class="text-xl font-semibold">Creator workspace</h1>
        <div class="flex items-center gap-2 text-sm" x-show="me" x-cloak>
            <span :class="statusClass(me?.kyc_status ?? 'pending')" x-text="me?.kyc_verified ? 'KYC verified' : (me?.kyc_status ? 'KYC ' + me.kyc_status : 'KYC not submitted')"></span>
            <span :class="statusClass(sub?.status ?? 'expired')" x-text="sub ? (sub.plan?.name + ' · ' + sub.status) : 'No plan'"></span>
        </div>
    </div>

    <div x-show="needsPlan" x-cloak class="rounded-xl bg-amber-50 border border-amber-200 p-4 text-sm mb-5">
        You need an active plan to receive bookings and publish work. Pick one in the <button class="underline font-medium" @click="tab = 'plan'">Plan & billing</button> tab.
    </div>

    <div class="grid gap-4 sm:grid-cols-4 mb-6" x-show="dash" x-cloak>
        <div class="card p-4"><p class="text-xs text-slate-500">Pending requests</p><p class="text-2xl font-semibold" x-text="dash?.pending_requests"></p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Bookings this month</p><p class="text-2xl font-semibold" x-text="dash?.month_bookings"></p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Upcoming shoots</p><p class="text-2xl font-semibold" x-text="dash?.upcoming?.length"></p></div>
        <div class="card p-4"><p class="text-xs text-slate-500">Balance owed to you</p><p class="text-2xl font-semibold" x-text="earn ? inr(earn.balance_owed) : '…'"></p></div>
    </div>

    <div class="flex gap-1 border-b mb-6 overflow-x-auto">
        <template x-for="[k, v] in [['requests','Bookings'],['availability','Availability'],['portfolio','Portfolio'],['plan','Plan & billing'],['kyc','Verification'],['earnings','Earnings']]">
            <button class="tab" :class="tab === k && 'tab-active'" @click="tab = k" x-text="v"></button>
        </template>
    </div>

    {{-- ===== Bookings ===== --}}
    <section x-show="tab === 'requests'" x-cloak>
        <div class="flex gap-2 mb-4 text-sm">
            <select class="input !w-auto" x-model="filter" @change="loadBookings()">
                <option value="inquiry,pending_payment,confirmed">Active</option>
                <option value="inquiry">New requests</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled,refunded">Cancelled / refunded</option>
            </select>
        </div>
        <p class="text-sm text-slate-400" x-show="!bookings.length">No bookings in this view.</p>
        <div class="space-y-3">
            <template x-for="b in bookings" :key="b.id">
                <div class="card p-4">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2"><b x-text="b.customer?.name"></b><span :class="statusClass(b.status)" x-text="statusLabel(b.status)"></span></div>
                            <p class="text-sm text-slate-500" x-text="(b.package?.title ?? '') + ' · ' + fmtDate(b.starts_at)"></p>
                            <p class="text-sm text-slate-500" x-show="b.location" x-text="'📍 ' + b.location"></p>
                            <p class="text-sm mt-1" x-show="b.notes" x-text="b.notes"></p>
                        </div>
                        <div class="text-right text-sm">
                            <p class="font-semibold" x-text="'You earn ' + inrPaise(b.creator_net_paise)"></p>
                            <p class="text-xs text-slate-400" x-text="'Customer pays ' + inrPaise(b.total_amount_paise) + ' · commission ' + b.commission_percent + '%'"></p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button class="btn-ok" x-show="b.status === 'inquiry'" @click="act(b, 'accept')">Accept</button>
                        <button class="btn-danger" x-show="b.status === 'inquiry'" @click="act(b, 'reject')">Decline</button>
                        <button class="btn-primary" x-show="b.status === 'confirmed'" @click="act(b, 'complete')">Mark completed</button>
                        <button class="btn-ghost" x-show="['pending_payment','confirmed'].includes(b.status)" @click="act(b, 'cancel')">Cancel</button>
                        <template x-if="!['cancelled','refunded'].includes(b.status)"><x-chat-button /></template>
                    </div>
                    <p class="text-xs text-sky-700 mt-2" x-show="b.status === 'pending_payment'">Waiting for the customer to pay (slot is held for a limited time).</p>
                </div>
            </template>
        </div>
    </section>

    {{-- ===== Availability ===== --}}
    <section x-show="tab === 'availability'" x-cloak class="grid lg:grid-cols-2 gap-6">
        <p x-show="noProfile" class="text-sm text-slate-500 lg:col-span-2">Availability is set per photographer profile. Create your photographer profile first (studios manage hours on each photographer's account).</p>
        <div class="card p-5" x-show="!noProfile">
            <h2 class="font-semibold mb-3">Weekly working hours</h2>
            <div class="space-y-2">
                <template x-for="w in week" :key="w.day">
                    <div class="flex items-center gap-3 text-sm">
                        <label class="w-28 flex items-center gap-2"><input type="checkbox" x-model="w.on"> <span x-text="w.name"></span></label>
                        <input type="time" class="input !w-auto" x-model="w.start" :disabled="!w.on"> <span>to</span>
                        <input type="time" class="input !w-auto" x-model="w.end" :disabled="!w.on">
                    </div>
                </template>
            </div>
            <button class="btn-primary mt-4" @click="saveHours()">Save hours</button>
        </div>
        <div class="card p-5" x-show="!noProfile">
            <h2 class="font-semibold mb-3">Block dates</h2>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label">From</label><input type="datetime-local" class="input" x-model="blk.starts_at"></div>
                <div><label class="label">To</label><input type="datetime-local" class="input" x-model="blk.ends_at"></div>
                <div class="col-span-2"><label class="label">Reason (optional)</label><input class="input" x-model="blk.reason"></div>
            </div>
            <button class="btn-primary mt-3" :disabled="!blk.starts_at || !blk.ends_at" @click="addBlock()">Block</button>
            <ul class="mt-4 space-y-2 text-sm">
                <template x-for="b in blocked" :key="b.id">
                    <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2">
                        <span><span x-text="fmtDate(b.starts_at)"></span> → <span x-text="fmtDate(b.ends_at)"></span> <i class="text-slate-400" x-text="b.reason"></i></span>
                        <button x-show="!b.booking_id" class="text-rose-600" @click="unblock(b)">Remove</button>
                        <span x-show="b.booking_id" class="text-xs text-slate-400">🔒 booking</span>
                    </li>
                </template>
            </ul>
        </div>
    </section>

    {{-- ===== Portfolio ===== --}}
    <section x-show="tab === 'portfolio'" x-cloak class="grid lg:grid-cols-2 gap-6">
        <div class="card p-5">
            <h2 class="font-semibold mb-3">New portfolio</h2>
            <div class="space-y-3">
                <div><label class="label">Title</label><input class="input" x-model="pf.title"></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label">Category</label>
                        <select class="input" x-model="pf.category_id"><option value="">Select…</option>
                            @foreach($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
                        </select></div>
                    <div><label class="label">Type</label>
                        <select class="input" x-model="pf.type">
                            @foreach(['wedding','pre_wedding','product','fashion','corporate','event'] as $t)<option value="{{ $t }}">{{ ucfirst(str_replace('_', ' ', $t)) }}</option>@endforeach
                        </select></div>
                </div>
                <div><label class="label">Description</label><textarea rows="2" class="input" x-model="pf.description"></textarea></div>
                <div><label class="label">Photos / videos (JPG, PNG, WebP, MP4, MOV)</label>
                    <input type="file" multiple x-ref="files" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime" @change="files = $event.target.files" class="text-sm"></div>
                <button class="btn-primary" :disabled="uploading || !pf.title || !pf.category_id" @click="createPortfolio()" x-text="uploading ? 'Uploading…' : 'Create & upload'"></button>
            </div>
        </div>
        <div class="card p-5">
            <h2 class="font-semibold mb-3">Your portfolios</h2>
            <p class="text-sm text-slate-400" x-show="!portfolios.length">Nothing yet.</p>
            <ul class="space-y-2 text-sm">
                <template x-for="p in portfolios" :key="p.id">
                    <li class="flex justify-between items-center rounded-lg bg-slate-50 px-3 py-2">
                        <span><b x-text="p.title"></b> <span class="text-slate-400" x-text="'· ' + p.category?.name + ' · ' + p.media_count + ' files'"></span></span>
                        <button class="text-rose-600" @click="delPortfolio(p)">Delete</button>
                    </li>
                </template>
            </ul>
        </div>
    </section>

    {{-- ===== Plan & billing ===== --}}
    <section x-show="tab === 'plan'" x-cloak>
        <div class="card p-5 mb-6" x-show="sub">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-sm text-slate-500">Current plan</p>
                    <p class="text-lg font-semibold" x-text="sub?.plan?.name"></p>
                    <p class="text-sm text-slate-500" x-text="(sub?.status === 'trialing' ? 'Trial ends ' : 'Renews / ends ') + (sub ? fmtDate(sub.ends_at) : '')"></p>
                    <p class="text-sm text-amber-700" x-show="sub?.status === 'past_due'">Payment overdue — pay below before the grace period ends.</p>
                </div>
                <button class="btn-ghost" x-show="sub?.auto_renew" @click="stopRenew()">Turn off auto-renew</button>
            </div>
        </div>

        <div class="flex items-center gap-2 mb-4 text-sm">
            <button class="btn" :class="interval === 'monthly' ? 'bg-indigo-600 text-white' : 'border'" @click="interval = 'monthly'">Monthly</button>
            <button class="btn" :class="interval === 'annual' ? 'bg-indigo-600 text-white' : 'border'" @click="interval = 'annual'">Annual</button>
        </div>
        <div class="grid md:grid-cols-3 gap-4">
            <template x-for="p in shownPlans" :key="p.id">
                <div class="card p-5 flex flex-col" :class="sub?.subscription_plan_id === p.id && 'ring-2 ring-indigo-500'">
                    <h3 class="font-semibold" x-text="p.name"></h3>
                    <p class="text-2xl font-bold mt-1"><span x-text="inrPaise(p.price_paise)"></span><span class="text-sm font-normal text-slate-400" x-text="'/' + (p.interval === 'annual' ? 'yr' : 'mo')"></span></p>
                    <ul class="text-sm text-slate-600 mt-3 space-y-1 flex-1">
                        <li>📷 Portfolio uploads: <b x-text="limit(p.max_portfolio_uploads)"></b></li>
                        <li>📅 Bookings / month: <b x-text="limit(p.max_bookings_per_month)"></b></li>
                        <li x-show="p.for_studios">👥 Team members: <b x-text="limit(p.max_team_members)"></b></li>
                        <li x-show="p.featured_listing">⭐ Featured listing</li>
                        <li x-show="p.analytics_access">📈 Analytics</li>
                    </ul>
                    <div class="mt-4 flex flex-col gap-2">
                        <button class="btn-ghost" x-show="p.trial_days > 0 && !sub && !invoices.length" @click="trial(p)" x-text="'Start ' + p.trial_days + '-day free trial'"></button>
                        <button class="btn-primary" :disabled="buying === p.id || sub?.subscription_plan_id === p.id && sub.status === 'active'" @click="buy(p)"
                                x-text="sub?.subscription_plan_id === p.id && sub.status === 'active' ? 'Current plan' : (buying === p.id ? 'Opening…' : (sub ? 'Switch to this plan' : 'Subscribe'))"></button>
                    </div>
                </div>
            </template>
        </div>
        <p class="text-xs text-slate-400 mt-3">Prices exclude 18% GST. Upgrades are prorated against your remaining time.</p>

        <h3 class="font-semibold mt-8 mb-2">Invoices</h3>
        <p class="text-sm text-slate-400" x-show="!invoices.length">No invoices yet.</p>
        <ul class="text-sm space-y-1"><template x-for="i in invoices" :key="i.id">
            <li class="flex justify-between bg-white border rounded-lg px-3 py-2"><span x-text="i.number"></span><span><span x-text="inrPaise(i.total_paise)"></span> <span :class="statusClass(i.status)" x-text="i.status"></span></span></li>
        </template></ul>
    </section>

    {{-- ===== KYC ===== --}}
    <section x-show="tab === 'kyc'" x-cloak class="max-w-xl">
        <div class="card p-5">
            <h2 class="font-semibold">Identity & bank verification</h2>
            <p class="text-sm text-slate-500 mb-4">Required before payouts. Reviewed by our team, usually within 1–2 business days.</p>
            <p class="text-sm mb-3" x-show="me?.kyc_status">Status: <span :class="statusClass(me?.kyc_status)" x-text="me?.kyc_status"></span></p>
            <div class="space-y-3" x-show="!me?.kyc_verified">
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label">PAN</label><input class="input uppercase" maxlength="10" x-model="kyc.pan" placeholder="ABCDE1234F"></div>
                    <div><label class="label">GSTIN (optional)</label><input class="input uppercase" maxlength="15" x-model="kyc.gstin"></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label">PAN card (image/PDF)</label><input type="file" x-ref="panFile" accept=".jpg,.jpeg,.png,.pdf" class="text-sm"></div>
                    <div><label class="label">ID proof (image/PDF)</label><input type="file" x-ref="idFile" accept=".jpg,.jpeg,.png,.pdf" class="text-sm"></div>
                </div>
                <div><label class="label">Account holder</label><input class="input" x-model="kyc.holder"></div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label">Account number</label><input class="input" inputmode="numeric" x-model="kyc.account"></div>
                    <div><label class="label">IFSC</label><input class="input uppercase" maxlength="11" x-model="kyc.ifsc"></div>
                </div>
                <button class="btn-primary" :disabled="kycSaving" @click="submitKyc()" x-text="kycSaving ? 'Submitting…' : 'Submit for verification'"></button>
            </div>
            <p x-show="me?.kyc_verified" class="text-emerald-700 text-sm">✅ You're verified and eligible for payouts.</p>
        </div>
    </section>

    {{-- ===== Earnings ===== --}}
    <section x-show="tab === 'earnings'" x-cloak>
        <div class="grid sm:grid-cols-2 gap-4 mb-6" x-show="earn">
            <div class="card p-5"><p class="text-sm text-slate-500">Balance owed to you</p><p class="text-2xl font-semibold" x-text="inr(earn?.balance_owed)"></p></div>
            <div class="card p-5"><p class="text-sm text-slate-500">Lifetime paid out</p><p class="text-2xl font-semibold" x-text="inr(earn?.lifetime_paid_out)"></p></div>
        </div>
        <h3 class="font-semibold mb-2">Settlements</h3>
        <p class="text-sm text-slate-400" x-show="!settlements.length">No settlements yet. Completed bookings are settled after a short hold period.</p>
        <ul class="space-y-2 text-sm mb-6"><template x-for="s in settlements" :key="s.id">
            <li class="card px-4 py-3 flex justify-between"><span x-text="s.reference + ' · ' + fmtDate(s.created_at)"></span><span><b x-text="inrPaise(s.net_paise)"></b> <span :class="statusClass(s.status)" x-text="statusLabel(s.status)"></span></span></li>
        </template></ul>
        <h3 class="font-semibold mb-2">Recent ledger entries</h3>
        <ul class="text-sm space-y-1"><template x-for="l in (earn?.recent_entries ?? [])" :key="l.id">
            <li class="flex justify-between bg-white border rounded-lg px-3 py-2"><span x-text="l.description"></span><b :class="l.direction === 'credit' ? 'text-emerald-700' : 'text-rose-600'" x-text="(l.direction === 'credit' ? '+' : '−') + inrPaise(l.amount_paise)"></b></li>
        </template></ul>
    </section>
</div>
@endsection
