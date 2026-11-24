<?php

use App\Http\Controllers\Booking\BookingController;
use App\Http\Controllers\Customer\ReviewController;
use App\Http\Controllers\Payment\PaymentController;
use Illuminate\Support\Facades\Route;

// Loaded under: /api, auth:sanctum, role:customer
Route::post('/bookings', [BookingController::class, 'store']);
Route::get('/my-bookings', [BookingController::class, 'mine']);
Route::post('/reviews', [ReviewController::class, 'store']);

Route::middleware('throttle:payments')->group(function () {
    Route::post('/payments', [PaymentController::class, 'create']);
    Route::post('/payments/verify', [PaymentController::class, 'verify']);
});
