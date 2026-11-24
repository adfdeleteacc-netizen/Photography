<?php $__env->startSection('title', 'Find photographers & studios'); ?>

<?php $__env->startSection('content'); ?>
<form method="GET" class="grid gap-3 md:grid-cols-5 bg-white p-4 rounded-xl border mb-8">
    <input name="q" value="<?php echo e($filters['q'] ?? ''); ?>" placeholder="Search creators" class="border rounded px-3 py-2 md:col-span-2">
    <input name="city" value="<?php echo e($filters['city'] ?? ''); ?>" placeholder="City" class="border rounded px-3 py-2">
    <select name="service_type" class="border rounded px-3 py-2">
        <option value="">All services</option>
        <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <option value="<?php echo e($c->slug); ?>" <?php if(($filters['service_type'] ?? '') === $c->slug): echo 'selected'; endif; ?>><?php echo e($c->name); ?></option>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </select>
    <button class="bg-indigo-600 text-white rounded px-3 py-2">Search</button>
    <label class="text-sm flex items-center gap-2 md:col-span-5"><input type="checkbox" name="verified" value="1" <?php if(!empty($filters['verified'])): echo 'checked'; endif; ?>> Verified creators only</label>
</form>

<div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
<?php $__empty_1 = true; $__currentLoopData = $creators; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $c): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <a href="<?php echo e(route('creator.show', $c->slug)); ?>" class="bg-white rounded-xl border p-5 hover:shadow">
        <div class="flex items-center justify-between">
            <h3 class="font-semibold"><?php echo e($c->display_name); ?></h3>
            <?php if($c->is_verified): ?><span class="text-xs bg-emerald-100 text-emerald-700 rounded px-2 py-0.5">Verified</span><?php endif; ?>
        </div>
        <p class="text-sm text-slate-500"><?php echo e($c->city); ?> · <?php echo e($c->experience_years); ?> yrs</p>
        <p class="mt-2 text-sm line-clamp-2"><?php echo e($c->bio); ?></p>
        <div class="mt-3 flex justify-between text-sm">
            <span>★ <?php echo e(number_format($c->rating_avg, 1)); ?> (<?php echo e($c->rating_count); ?>)</span>
            <span>from ₹<?php echo e(number_format($c->starting_price_paise / 100)); ?></span>
        </div>
    </a>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <p class="col-span-full text-center text-slate-500">No creators match your filters.</p>
<?php endif; ?>
</div>
<div class="mt-8"><?php echo e($creators->links()); ?></div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.marketplace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/height8/Downloads/marketplace/resources/views/home.blade.php ENDPATH**/ ?>