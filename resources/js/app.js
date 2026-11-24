import Alpine from 'alpinejs';
import axios from 'axios';

window.axios = axios;
axios.defaults.withCredentials = true;
axios.defaults.withXSRFToken = true;
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

/* ---------- formatting ---------- */
window.inr = (rupees) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(rupees ?? 0);
window.inrPaise = (paise) => window.inr((paise ?? 0) / 100);
window.fmtDate = (d) => new Date(d).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });

const STATUS_STYLES = {
    inquiry: 'bg-amber-100 text-amber-800', pending_payment: 'bg-sky-100 text-sky-800',
    confirmed: 'bg-emerald-100 text-emerald-800', completed: 'bg-slate-200 text-slate-700',
    cancelled: 'bg-rose-100 text-rose-700', refunded: 'bg-purple-100 text-purple-700',
    pending: 'bg-amber-100 text-amber-800', verified: 'bg-emerald-100 text-emerald-800', rejected: 'bg-rose-100 text-rose-700',
    pending_approval: 'bg-amber-100 text-amber-800', approved: 'bg-sky-100 text-sky-800', paid: 'bg-emerald-100 text-emerald-800',
    processing: 'bg-sky-100 text-sky-800', failed: 'bg-rose-100 text-rose-700', active: 'bg-emerald-100 text-emerald-800',
    trialing: 'bg-sky-100 text-sky-800', past_due: 'bg-amber-100 text-amber-800', expired: 'bg-rose-100 text-rose-700',
};
window.statusClass = (s) => 'badge ' + (STATUS_STYLES[s] ?? 'bg-slate-100 text-slate-700');
window.statusLabel = (s) => (s ?? '').replaceAll('_', ' ');

/* ---------- toasts ---------- */
Alpine.store('toast', {
    items: [],
    push(msg, type = 'info') {
        const id = Date.now() + Math.random();
        this.items.push({ id, msg, type });
        setTimeout(() => (this.items = this.items.filter((t) => t.id !== id)), 5000);
    },
    ok(m) { this.push(m, 'ok'); },
    err(m) { this.push(m, 'err'); },
});

/** Turn any axios error into a readable message (Laravel validation errors included). */
window.errMsg = (e) => {
    const d = e?.response?.data;
    if (d?.errors) return Object.values(d.errors).flat().join(' ');
    return d?.message ?? e?.message ?? 'Something went wrong';
};
window.notifyErr = (e) => Alpine.store('toast').err(window.errMsg(e));
window.notifyOk = (m) => Alpine.store('toast').ok(m);

/** Run an API call, show a toast on failure, return data or null. */
window.api = async (method, url, data, config = {}) => {
    try {
        return (await axios({ method, url, data, ...config })).data;
    } catch (e) {
        window.notifyErr(e);
        return null;
    }
};

/* ---------- Razorpay checkout ---------- */
/**
 * order = { order_id, amount, key, currency }
 * verifyUrl = endpoint that verifies the signature server-side.
 * Resolves true when the server confirmed the payment.
 */
window.payWithRazorpay = (order, { verifyUrl, name, description, prefill = {} }) =>
    new Promise((resolve) => {
        if (!window.Razorpay) { window.notifyErr({ message: 'Razorpay checkout failed to load. Check your internet connection.' }); return resolve(false); }
        if (!order.key) { window.notifyErr({ message: 'Razorpay is not configured (set RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET in .env).' }); return resolve(false); }

        const rzp = new window.Razorpay({
            key: order.key, amount: order.amount, currency: order.currency ?? 'INR', order_id: order.order_id,
            name, description, prefill,
            handler: async (resp) => {
                const r = await window.api('post', verifyUrl, {
                    razorpay_order_id: resp.razorpay_order_id, razorpay_payment_id: resp.razorpay_payment_id, razorpay_signature: resp.razorpay_signature,
                });
                resolve(!!r);
            },
            modal: { ondismiss: () => resolve(false) },
            theme: { color: '#4f46e5' },
        });
        rzp.on('payment.failed', (r) => window.notifyErr({ message: r.error?.description ?? 'Payment failed' }));
        rzp.open();
    });

/* ---------- booking chat (Alpine component) ---------- */
Alpine.data('chat', (bookingId, myId) => ({
    open: false, loading: false, messages: [], body: '', timer: null,
    async toggle() {
        this.open = !this.open;
        if (this.open) { await this.load(); this.timer = setInterval(() => this.load(), 8000); }
        else clearInterval(this.timer);
    },
    async load() {
        const r = await window.api('get', `/api/bookings/${bookingId}/messages`);
        if (r) { this.messages = [...r.data].reverse(); this.$nextTick(() => { const el = this.$refs.list; if (el) el.scrollTop = el.scrollHeight; }); }
    },
    async send() {
        if (!this.body.trim()) return;
        const r = await window.api('post', `/api/bookings/${bookingId}/messages`, { body: this.body });
        if (r) { this.body = ''; await this.load(); }
    },
    mine(m) { return m.sender_id === myId; },
}));

window.Alpine = Alpine;
Alpine.start();
