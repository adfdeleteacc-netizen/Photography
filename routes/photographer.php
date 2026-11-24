<?php

use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Photographer\{AvailabilityController, DashboardController, PortfolioController, SubscriptionController};
use Illuminate\Support\Facades\Route;

// Loaded under: /api, auth:sanctum, role:photographer|studio

// Billing stays reachable without a subscription so a lapsed creator can always pay.
Route::get('/subscription/plans', [SubscriptionController::class, 'plans'])->name('subscription.plans');
Route::get('/subscription', [SubscriptionController::class, 'current']);
Route::post('/subscription/trial', [SubscriptionController::class, 'trial']);
Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout'])->middleware('throttle:payments');
Route::post('/subscription/verify', [SubscriptionController::class, 'verify'])->middleware('throttle:payments');
Route::post('/subscription/downgrade', [SubscriptionController::class, 'downgrade']);
Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel']);
Route::get('/subscription/invoices', [SubscriptionController::class, 'invoices']);

Route::get('/earnings', [DashboardController::class, 'earnings']);
Route::get('/settlements', [DashboardController::class, 'settlements']);

// Everything that earns money requires an active (or in-grace) subscription.
Route::middleware('subscribed')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboard']);
    Route::get('/creator/bookings', [DashboardController::class, 'bookings']);
    Route::get('/portfolio', [PortfolioController::class, 'index']);

    Route::post('/portfolio', [PortfolioController::class, 'store'])->middleware('throttle:uploads');
    Route::post('/portfolio/{portfolio}/media', [PortfolioController::class, 'addMedia'])->middleware('throttle:uploads');
    Route::delete('/portfolio/{portfolio}', [PortfolioController::class, 'destroy']);
    Route::get('/media/{media}/private-url', [PortfolioController::class, 'privateUrl']);

    Route::get('/availability', [AvailabilityController::class, 'show']);
    Route::post('/availability', [AvailabilityController::class, 'store']);
    Route::delete('/availability/blocked/{blocked}', [AvailabilityController::class, 'unblock']);

    Route::post('/bookings/{booking}/accept', [BookingController::class, 'accept']);
    Route::post('/bookings/{booking}/reject', [BookingController::class, 'reject']);
    Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete']);
    Route::post('/bookings/{booking}/assign', [BookingController::class, 'assign']);
});
