<?php $__env->startSection('title', $creator->display_name . ' — ' . $creator->city); ?>
<?php $__env->startSection('meta_description', \Illuminate\Support\Str::limit($creator->bio, 150)); ?>

<?php $__env->startSection('content'); ?>
<script type="application/ld+json">
<?php echo json_encode(['<?php $__contextArgs = [];
if (context()->has($__contextArgs[0])) :
if (isset($value)) { $__contextPrevious[] = $value; }
$value = context()->get($__contextArgs[0]); ?>' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $creator->display_name,
   'address' => ['@type' => 'PostalAddress', 'addressLocality' => $creator->city],
   'aggregateRating' => $creator->rating_count ? ['@type' => 'AggregateRating', 'ratingValue' => $creator->rating_avg, 'reviewCount' => $creator->rating_count] : null], JSON_UNESCAPED_SLASHES); ?>

</script>

<h1 class="text-2xl font-bold"><?php echo e($creator->display_name); ?></h1>
<p class="text-slate-500"><?php echo e($creator->city); ?> · ★ <?php echo e(number_format($creator->rating_avg, 1)); ?> (<?php echo e($creator->rating_count); ?> reviews)</p>
<p class="mt-4 max-w-2xl"><?php echo e($creator->bio); ?></p>

<h2 class="mt-8 mb-3 font-semibold">Packages</h2>
<div class="grid gap-4 md:grid-cols-3">
<?php $__currentLoopData = $creator->packages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $p): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <div class="bg-white border rounded-xl p-4">
        <h3 class="font-medium"><?php echo e($p->title); ?></h3>
        <p class="text-sm text-slate-500"><?php echo e(intdiv($p->duration_minutes, 60)); ?> hrs</p>
        <p class="mt-2 font-semibold">₹<?php echo e(number_format($p->price_paise / 100)); ?></p>
    </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</div>

<h2 class="mt-8 mb-3 font-semibold">Portfolio</h2>
<?php $__currentLoopData = $creator->portfolios->where('is_published', true); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pf): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    <h3 class="mt-4 font-medium"><?php echo e($pf->title); ?> <span class="text-xs text-slate-400"><?php echo e(ucfirst(str_replace('_', ' ', $pf->type))); ?></span></h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mt-2">
        <?php $__currentLoopData = $pf->media->where('is_private', false)->whereNotNull('thumbnail_path'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <img loading="lazy" class="rounded aspect-square object-cover"
                 src="<?php echo e(Storage::disk(config('marketplace.media.disk'))->url($m->thumbnail_path)); ?>" alt="<?php echo e($pf->title); ?>">
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

<h2 class="mt-8 mb-3 font-semibold">Reviews</h2>
<?php $__empty_1 = true; $__currentLoopData = $creator->reviews->where('is_visible', true); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="bg-white border rounded-lg p-3 mb-2"><span><?php echo e(str_repeat('★', $rv->rating)); ?></span> <span class="text-sm text-slate-500"><?php echo e($rv->customer->name); ?></span><p class="text-sm"><?php echo e($rv->body); ?></p></div>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <p class="text-slate-500 text-sm">No reviews yet.</p>
<?php endif; ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.marketplace', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/height8/Downloads/marketplace/resources/views/creator.blade.php ENDPATH**/ ?>