<?php $__env->startSection('title', $creator->display_name . ' — ' . $creator->city); ?>
<?php $__env->startSection('meta_description', \Illuminate\Support\Str::limit($creator->bio, 150)); ?>

<?php $__env->startPush('head'); ?>
<script type="application/ld+json">
<?php echo json_encode(array_filter(['<?php $__contextArgs = [];
if (context()->has($__contextArgs[0])) :
if (isset($value)) { $__contextPrevious[] = $value; }
$value = context()->get($__contextArgs[0]); ?>' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $creator->display_name,
   'address' => ['@type' => 'PostalAddress', 'addressLocality' => $creator->city],
   'aggregateRating' => $creator->rating_count ? ['@type' => 'AggregateRating', 'ratingValue' => $creator->rating_avg, 'reviewCount' => $creator->rating_count] : null]), JSON_UNESCAPED_SLASHES); ?>

</script>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<?php
    $pkgs = $creator->packages->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'price' => $p->price_paise, 'mins' => $p->duration_minutes])->values();
?>
<div class="grid lg:grid-cols-3 gap-8"
     x-data="{
        pkg: <?php echo e($creator->packages->first()?->id ?? 'null'); ?>,
        starts_at: '', location: '', notes: '', coupon: '', points: 0, busy: false, done: false,
        packages: <?php echo \Illuminate\Support\Js::from($pkgs)->toHtml() ?>,
        get sel() { return this.packages.find(p => p.id == this.pkg) },
        async submit() {
            this.busy = true;
            const r = await api('post', '/api/bookings', {
                service_package_id: this.pkg, photographer_profile_id: <?php echo e($creator->id); ?>, starts_at: this.starts_at,
                location: this.location || null, notes: this.notes || null,
                coupon_code: this.coupon || null, loyalty_points: Number(this.points) || 0,
            });
            this.busy = false;
            if (r) { this.done = true; notifyOk('Request sent! The creator will respond soon.'); setTimeout(() => location.href = '<?php echo e(route('customer.bookings')); ?>', 1200); }
        }
     }">

    
    <div class="lg:col-span-2">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold"><?php echo e($creator->display_name); ?>

                    <?php if($creator->is_verified): ?><span class="badge bg-emerald-100 text-emerald-700 align-middle">✓ Verified</span><?php endif; ?>
                </h1>
                <p class="text-slate-500"><?php echo e($creator->city); ?> · <?php echo e($creator->experience_years); ?> yrs · ⭐ <?php echo e(number_format($creator->rating_avg, 1)); ?> (<?php echo e($creator->rating_count); ?> reviews)</p>
            </div>
        </div>
        <p class="mt-4 text-slate-700"><?php echo e($creator->bio); ?></p>
        <div class="mt-3 flex flex-wrap gap-1"><?php $__currentLoopData = $creator->categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span class="badge bg-slate-100 text-slate-600"><?php echo e($cat->name); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div>

        <h2 class="mt-8 mb-3 font-semibold text-lg">Portfolio</h2>
        <?php $shown = false; ?>
        <?php $__currentLoopData = $creator->portfolios->where('is_published', true); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pf): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php $thumbs = $pf->media->where('is_private', false)->whereNotNull('thumbnail_path'); ?>
            <?php if($thumbs->count()): ?> <?php $shown = true; ?>
                <h3 class="mt-4 font-medium"><?php echo e($pf->title); ?> <span class="text-xs text-slate-400"><?php echo e(ucfirst(str_replace('_', ' ', $pf->type))); ?></span></h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
                    <?php $__currentLoopData = $thumbs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <img loading="lazy" class="rounded-lg aspect-square object-cover" alt="<?php echo e($pf->title); ?>"
                             src="<?php echo e(Storage::disk(config('marketplace.media.disk'))->url($m->thumbnail_path)); ?>">
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if (! ($shown)): ?><p class="text-sm text-slate-400">This creator hasn't published photos yet.</p><?php endif; ?>

        <h2 class="mt-8 mb-3 font-semibold text-lg">Reviews</h2>
        <?php $__empty_1 = true; $__currentLoopData = $creator->reviews->where('is_visible', true); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="card p-4 mb-2">
                <div class="flex justify-between text-sm"><span><?php echo e(str_repeat('⭐', $rv->rating)); ?></span><span class="text-slate-400"><?php echo e($rv->customer->name); ?></span></div>
                <p class="text-sm mt-1"><?php echo e($rv->body); ?></p>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <p class="text-sm text-slate-400">No reviews yet.</p>
        <?php endif; ?>
    </div>

    
    <aside class="lg:sticky lg:top-20 self-start card p-5">
        <h2 class="font-semibold text-lg mb-3">Request a booking</h2>

        <?php if(auth()->guard()->guest()): ?>
            <p class="text-sm text-slate-600">Log in as a customer to book.</p>
            <a href="<?php echo e(route('login')); ?>" class="btn-primary w-full mt-3">Log in</a>
        <?php else: ?>
            <?php if (\Illuminate\Support\Facades\Blade::check('role', 'customer')): ?>
                <template x-if="!done">
                <div class="space-y-3">
                    <div>
                        <label class="label">Package</label>
                        <select class="input" x-model="pkg">
                            <template x-for="p in packages" :key="p.id"><option :value="p.id" x-text="p.title + ' — ' + inrPaise(p.price)"></option></template>
                        </select>
                        <p class="text-xs text-slate-400 mt-1" x-show="sel" x-text="'Duration ≈ ' + (sel.mins/60).toFixed(1) + ' hours'"></p>
                    </div>
                    <div><label class="label">Date & start time</label><input type="datetime-local" class="input" x-model="starts_at"></div>
                    <div><label class="label">Location</label><input class="input" x-model="location" placeholder="Venue / address"></div>
                    <div><label class="label">Notes</label><textarea rows="2" class="input" x-model="notes" placeholder="Anything the creator should know"></textarea></div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="label">Coupon</label><input class="input uppercase" x-model="coupon" placeholder="CODE"></div>
                        <div><label class="label">Loyalty points</label><input type="number" min="0" class="input" x-model="points"></div>
                    </div>
                    <div class="rounded-lg bg-slate-50 p-3 text-sm" x-show="sel">
                        <div class="flex justify-between"><span>Package price</span><b x-text="inrPaise(sel.price)"></b></div>
                        <p class="text-xs text-slate-500 mt-1">Coupon / points discounts are applied when the creator accepts. You only pay after acceptance.</p>
                    </div>
                    <button class="btn-primary w-full" :disabled="busy || !starts_at || !pkg" @click="submit()" x-text="busy ? 'Sending…' : 'Send request'"></button>
                </div>
                </template>
                <p x-show="done" class="text-emerald-700 text-sm">✅ Request sent. Redirecting to your bookings…</p>
            <?php else: ?>
                <p class="text-sm text-slate-500">Only customer accounts can book.</p>
            <?php endif; ?>
        <?php endif; ?>
    </aside>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.marketplace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/height8/Downloads/marketplace/resources/views/creator.blade.php ENDPATH**/ ?>