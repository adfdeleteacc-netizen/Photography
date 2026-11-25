<!DOCTYPE html>
<html lang="en" class="h-full bg-stone-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', 'Book verified photographers, videographers and studios.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-full text-stone-800 antialiased">
<header class="bg-white/95 backdrop-blur border-b sticky top-0 z-30" x-data="{ menu: false }">
    <div class="max-w-7xl mx-auto px-5 sm:px-6 h-16 flex items-center justify-between">
        <a href="{{ route('home') }}" class="font-bold text-xl text-stone-900">{{ config('app.name') }}</a>
        <button class="md:hidden btn-ghost !px-2 !py-1" @click="menu = !menu" aria-label="Menu">☰</button>
        <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
            <a href="{{ route('home') }}" class="hover:text-indigo-600">Find creators</a>
            @auth
                @role('admin')<a href="{{ route('admin.dashboard') }}" class="hover:text-indigo-600">Admin</a>@endrole
                @hasanyrole('photographer|studio')<a href="{{ route('photographer.dashboard') }}" class="hover:text-indigo-600">My studio</a>@endhasanyrole
                @role('customer')
                    <a href="{{ route('customer.bookings') }}" class="hover:text-indigo-600">My bookings</a>
                    <a href="{{ route('customer.rewards') }}" class="hover:text-indigo-600">Rewards</a>
                @endrole
                <span class="text-slate-400">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-slate-500 hover:text-rose-600">Log out</button></form>
            @else
                <a href="{{ route('login') }}" class="hover:text-indigo-600">Log in</a>
                <a href="{{ route('register') }}" class="btn-primary !py-1.5">Join</a>
            @endauth
        </nav>
    </div>
    <div x-show="menu" x-cloak class="md:hidden border-t px-4 py-3 space-y-2 text-sm" x-transition>
        <a class="block" href="{{ route('home') }}">Find creators</a>
        @auth
            @role('customer')<a class="block" href="{{ route('customer.bookings') }}">My bookings</a>@endrole
            @hasanyrole('photographer|studio')<a class="block" href="{{ route('photographer.dashboard') }}">My studio</a>@endhasanyrole
            @role('admin')<a class="block" href="{{ route('admin.dashboard') }}">Admin</a>@endrole
            <form method="POST" action="{{ route('logout') }}">@csrf<button>Log out</button></form>
        @else
            <a class="block" href="{{ route('login') }}">Log in</a><a class="block" href="{{ route('register') }}">Join</a>
        @endauth
    </div>
</header>

@auth
    @unless(auth()->user()->hasVerifiedEmail())
        <div class="bg-amber-50 border-b border-amber-200 text-amber-900 text-sm px-4 py-2 text-center">
            Please verify your email to use bookings and dashboards.
            <form class="inline" method="POST" action="{{ route('verification.send') }}">@csrf<button class="underline ml-1">Resend link</button></form>
            <span class="text-amber-700">(local dev: the link is in <code>storage/logs/laravel.log</code>)</span>
        </div>
    @endunless
@endauth

<main class="max-w-7xl mx-auto px-5 sm:px-6 py-8 lg:py-10">@yield('content')</main>

{{-- toasts --}}
<div class="fixed bottom-4 right-4 z-50 space-y-2 w-80 max-w-[90vw]" x-data>
    <template x-for="t in $store.toast.items" :key="t.id">
        <div class="rounded-lg px-4 py-3 text-sm shadow-lg text-white"
             :class="t.type === 'ok' ? 'bg-emerald-600' : t.type === 'err' ? 'bg-rose-600' : 'bg-slate-800'" x-text="t.msg"></div>
    </template>
</div>
@stack('scripts')
</body>
</html>
