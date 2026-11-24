<?php

use App\Models\{Category, PhotographerProfile};
use App\Services\Booking\SearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Breeze's login / register / password-reset / email-verification routes
require __DIR__ . '/auth.php';

Route::get('/', function (Request $r, SearchService $search) {
    $f = $r->validate(['city' => 'nullable|string|max:80', 'service_type' => 'nullable|exists:categories,slug', 'q' => 'nullable|string|max:80', 'min_rating' => 'nullable|numeric', 'verified' => 'nullable|boolean']);
    return view('home', ['creators' => $search->search($f, 12), 'categories' => Category::orderBy('name')->get(), 'filters' => $f]);
})->name('home');

Route::get('/creators/{slug}', function (string $slug) {
    $c = PhotographerProfile::with(['categories', 'packages', 'portfolios.media', 'reviews.customer'])->where('slug', $slug)->firstOrFail();
    return view('creator', ['creator' => $c]);
})->name('creator.show');

// SPA-less role dashboards (Blade + Alpine) fetch data from the /api endpoints with the session's Sanctum cookie.
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/photographer/dashboard', fn () => view('photographer.dashboard', ['categories' => Category::orderBy('name')->get()]))
        ->middleware('role:photographer|studio')->name('photographer.dashboard');
    Route::view('/admin/dashboard', 'admin.dashboard')->middleware('role:admin')->name('admin.dashboard');
    Route::view('/my/rewards', 'customer.rewards')->middleware('role:customer')->name('customer.rewards');
    Route::view('/my/bookings', 'customer.bookings')->middleware('role:customer')->name('customer.bookings');
});

// Breeze redirects here after login; send each role to its own home.
Route::get('/dashboard', function (Request $r) {
    $u = $r->user();
    return redirect()->route(match (true) {
        $u->hasRole('admin') => 'admin.dashboard',
        $u->hasAnyRole(['photographer', 'studio']) => 'photographer.dashboard',
        default => 'customer.bookings',
    });
})->middleware(['auth', 'verified'])->name('dashboard');
require __DIR__ . '/profile.php';
