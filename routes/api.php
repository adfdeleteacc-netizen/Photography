<?php

use App\Http\Controllers\API\PublicController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Payment\PaymentController;
use Illuminate\Support\Facades\Route;

/* ---------- Public ---------- */
Route::get('/photographers', [PublicController::class, 'photographers']);
Route::get('/photographers/{slug}', [PublicController::class, 'creator']);
Route::get('/studios', [PublicController::class, 'studios']);
Route::get('/portfolio/{slug}', [PublicController::class, 'portfolio']);
Route::get('/search', [PublicController::class, 'search']);

/* ---------- Auth ---------- */
Route::middleware('throttle:auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

/* ---------- Webhooks (signature-verified, no auth) ---------- */
Route::post('/webhooks/razorpay', [PaymentController::class, 'webhook'])->middleware('throttle:webhooks');

/* ---------- Any authenticated user ---------- */
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/kyc', [AuthController::class, 'submitKyc']);

    // Booking detail / chat / cancel are authorised per-booking by BookingPolicy (customer OR creator side)
    Route::get('/bookings/{booking}', [\App\Http\Controllers\Booking\BookingController::class, 'show']);
    Route::post('/bookings/{booking}/cancel', [\App\Http\Controllers\Booking\BookingController::class, 'cancel']);
    Route::get('/bookings/{booking}/messages', [\App\Http\Controllers\Customer\MessageController::class, 'index']);
    Route::post('/bookings/{booking}/messages', [\App\Http\Controllers\Customer\MessageController::class, 'store']);
});
