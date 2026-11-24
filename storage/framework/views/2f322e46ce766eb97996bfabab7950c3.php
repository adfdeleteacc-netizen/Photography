<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', config('app.name')); ?></title>
    <meta name="description" content="<?php echo $__env->yieldContent('meta_description', 'Book verified photographers, videographers and studios.'); ?>">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
</head>
<body class="h-full text-slate-800">
<header class="bg-white border-b">
    <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
        <a href="<?php echo e(route('home')); ?>" class="font-semibold text-lg"><?php echo e(config('app.name')); ?></a>
        <nav class="flex gap-4 text-sm">
            <?php if(auth()->guard()->check()): ?>
                <?php if (\Illuminate\Support\Facades\Blade::check('role', 'admin')): ?><a href="<?php echo e(route('admin.dashboard')); ?>">Admin</a><?php endif; ?>
                <?php if (\Illuminate\Support\Facades\Blade::check('hasanyrole', 'photographer|studio')): ?><a href="<?php echo e(route('photographer.dashboard')); ?>">Dashboard</a><?php endif; ?>
                <?php if (\Illuminate\Support\Facades\Blade::check('role', 'customer')): ?><a href="<?php echo e(route('customer.bookings')); ?>">My bookings</a><?php endif; ?>
                <form method="POST" action="<?php echo e(route('logout')); ?>"><?php echo csrf_field(); ?><button class="text-slate-500">Log out</button></form>
            <?php else: ?>
                <a href="<?php echo e(route('login')); ?>">Log in</a>
                <a href="<?php echo e(route('register')); ?>" class="font-medium text-indigo-600">Join</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="max-w-6xl mx-auto px-4 py-8"><?php echo $__env->yieldContent('content'); ?></main>
</body>
</html>
<?php /**PATH /home/height8/Downloads/marketplace/resources/views/layouts/marketplace.blade.php ENDPATH**/ ?>