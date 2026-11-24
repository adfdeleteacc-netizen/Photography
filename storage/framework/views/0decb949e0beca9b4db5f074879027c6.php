<?php $__env->startSection('title', 'Admin dashboard'); ?>

<?php $__env->startSection('content'); ?>
<div x-data="{ d: null }" x-init="axios.get('/api/admin/dashboard').then(r => d = r.data)">
    <template x-if="d">
        <div class="grid gap-4 md:grid-cols-4">
            <template x-for="[label, val, money] in [['GMV (30d)', d.gmv_30d, true], ['Commission revenue', d.commission_revenue, true], ['GST payable', d.gst_payable, true], ['TCS payable', d.tcs_payable, true],
                                                    ['Active subscriptions', d.active_subscriptions, false], ['Pending KYC', d.pending_kyc, false], ['Settlements to approve', d.settlements_awaiting_approval, false], ['Open fraud flags', d.open_fraud_flags, false]]">
                <div class="bg-white border rounded-xl p-4"><p class="text-sm text-slate-500" x-text="label"></p><p class="text-xl font-semibold" x-text="money ? inr(val) : val"></p></div>
            </template>
        </div>
    </template>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.marketplace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/height8/Downloads/marketplace/resources/views/admin/dashboard.blade.php ENDPATH**/ ?>