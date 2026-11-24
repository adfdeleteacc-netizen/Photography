<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', 'Book verified photographers, videographers and studios.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-slate-800">
<header class="bg-white border-b">
    <div class="max-w-6xl mx-auto px-4 h-14 flex items-center justify-between">
        <a href="{{ route('home') }}" class="font-semibold text-lg">{{ config('app.name') }}</a>
        <nav class="flex gap-4 text-sm">
            @auth
                @role('admin')<a href="{{ route('admin.dashboard') }}">Admin</a>@endrole
                @hasanyrole('photographer|studio')<a href="{{ route('photographer.dashboard') }}">Dashboard</a>@endhasanyrole
                @role('customer')<a href="{{ route('customer.bookings') }}">My bookings</a>@endrole
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-slate-500">Log out</button></form>
            @else
                <a href="{{ route('login') }}">Log in</a>
                <a href="{{ route('register') }}" class="font-medium text-indigo-600">Join</a>
            @endauth
        </nav>
    </div>
</header>
<main class="max-w-6xl mx-auto px-4 py-8">@yield('content')</main>
</body>
</html>
