<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', config('app.name')); ?></title>
    <meta name="description" content="<?php echo $__env->yieldContent('meta_description', 'Book verified photographers, videographers and studios.'); ?>">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js']); ?>
    <?php echo $__env->yieldPushContent('head'); ?>
</head>
<body class="min-h-full text-slate-800 antialiased">
<header class="bg-white/90 backdrop-blur border-b sticky top-0 z-30" x-data="{ menu: false }">
    <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
        <a href="<?php echo e(route('home')); ?>" class="font-bold text-lg text-indigo-700"><?php echo e(config('app.name')); ?></a>
        <button class="md:hidden btn-ghost !px-2 !py-1" @click="menu = !menu" aria-label="Menu">☰</button>
        <nav class="hidden md:flex items-center gap-5 text-sm" >
            <a href="<?php echo e(route('home')); ?>" class="hover:text-indigo-600">Find creators</a>
            <?php if(auth()->guard()->check()): ?>
                <?php if (\Illuminate\Support\Facades\Blade::check('role', 'admin')): ?><a href="<?php echo e(route('admin.dashboard')); ?>" class="hover:text-indigo-600">Admin</a><?php endif; ?>
                <?php if (\Illuminate\Support\Facades\Blade::check('hasanyrole', 'photographer|studio')): ?><a href="<?php echo e(route('photographer.dashboard')); ?>" class="hover:text-indigo-600">My studio</a><?php endif; ?>
                <?php if (\Illuminate\Support\Facades\Blade::check('role', 'customer')): ?>
                    <a href="<?php echo e(route('customer.bookings')); ?>" class="hover:text-indigo-600">My bookings</a>
                    <a href="<?php echo e(route('customer.rewards')); ?>" class="hover:text-indigo-600">Rewards</a>
                <?php endif; ?>
                <span class="text-slate-400"><?php echo e(auth()->user()->name); ?></span>
                <form method="POST" action="<?php echo e(route('logout')); ?>"><?php echo csrf_field(); ?><button class="text-slate-500 hover:text-rose-600">Log out</button></form>
            <?php else: ?>
                <a href="<?php echo e(route('login')); ?>" class="hover:text-indigo-600">Log in</a>
                <a href="<?php echo e(route('register')); ?>" class="btn-primary !py-1.5">Join</a>
            <?php endif; ?>
        </nav>
    </div>
    <div x-show="menu" x-cloak class="md:hidden border-t px-4 py-3 space-y-2 text-sm" x-transition>
        <a class="block" href="<?php echo e(route('home')); ?>">Find creators</a>
        <?php if(auth()->guard()->check()): ?>
            <?php if (\Illuminate\Support\Facades\Blade::check('role', 'customer')): ?><a class="block" href="<?php echo e(route('customer.bookings')); ?>">My bookings</a><?php endif; ?>
            <?php if (\Illuminate\Support\Facades\Blade::check('hasanyrole', 'photographer|studio')): ?><a class="block" href="<?php echo e(route('photographer.dashboard')); ?>">My studio</a><?php endif; ?>
            <?php if (\Illuminate\Support\Facades\Blade::check('role', 'admin')): ?><a class="block" href="<?php echo e(route('admin.dashboard')); ?>">Admin</a><?php endif; ?>
            <form method="POST" action="<?php echo e(route('logout')); ?>"><?php echo csrf_field(); ?><button>Log out</button></form>
        <?php else: ?>
            <a class="block" href="<?php echo e(route('login')); ?>">Log in</a><a class="block" href="<?php echo e(route('register')); ?>">Join</a>
        <?php endif; ?>
    </div>
</header>

<?php if(auth()->guard()->check()): ?>
    <?php if (! (auth()->user()->hasVerifiedEmail())): ?>
        <div class="bg-amber-50 border-b border-amber-200 text-amber-900 text-sm px-4 py-2 text-center">
            Please verify your email to use bookings and dashboards.
            <form class="inline" method="POST" action="<?php echo e(route('verification.send')); ?>"><?php echo csrf_field(); ?><button class="underline ml-1">Resend link</button></form>
            <span class="text-amber-700">(local dev: the link is in <code>storage/logs/laravel.log</code>)</span>
        </div>
    <?php endif; ?>
<?php endif; ?>

<main class="max-w-6xl mx-auto px-4 py-8"><?php echo $__env->yieldContent('content'); ?></main>


<div class="fixed bottom-4 right-4 z-50 space-y-2 w-80 max-w-[90vw]" x-data>
    <template x-for="t in $store.toast.items" :key="t.id">
        <div class="rounded-lg px-4 py-3 text-sm shadow-lg text-white"
             :class="t.type === 'ok' ? 'bg-emerald-600' : t.type === 'err' ? 'bg-rose-600' : 'bg-slate-800'" x-text="t.msg"></div>
    </template>
</div>
<?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /home/height8/Downloads/marketplace/resources/views/layouts/marketplace.blade.php ENDPATH**/ ?>