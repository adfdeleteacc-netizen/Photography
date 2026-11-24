<?php

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;

// Loaded under: /api/admin, auth:sanctum, role:admin
Route::get('/dashboard', [AdminController::class, 'dashboard'])->middleware('permission:view analytics');

Route::post('/commission', [AdminController::class, 'setCommission'])->middleware('permission:configure commissions');

Route::middleware('permission:manage subscriptions')->group(function () {
    Route::post('/subscription-plans', [AdminController::class, 'storePlan']);
    Route::patch('/subscription-plans/{plan}', [AdminController::class, 'updatePlan']);
});

Route::middleware('permission:verify kyc')->group(function () {
    Route::get('/kyc', [AdminController::class, 'kycQueue']);
    Route::post('/kyc/{doc}/review', [AdminController::class, 'reviewKyc']);
    Route::get('/kyc/{doc}/file', [AdminController::class, 'kycFile']);
});

Route::middleware('permission:approve settlements')->group(function () {
    Route::get('/settlements', [AdminController::class, 'settlements']);
    Route::post('/settlements/generate', [AdminController::class, 'generateSettlements']);
    Route::post('/settlements/{settlement}/approve', [AdminController::class, 'approveSettlement']);
});

Route::middleware('permission:export tax')->group(function () {
    Route::get('/gst-reports', [AdminController::class, 'gstReports']);
    Route::get('/gst-reports/gstr8.csv', [AdminController::class, 'exportGstr8']);
});

Route::post('/coupons', [AdminController::class, 'storeCoupon'])->middleware('permission:manage coupons');
Route::get('/users', [AdminController::class, 'users'])->middleware('permission:manage users');
Route::post('/users/{user}/suspend', [AdminController::class, 'suspendUser'])->middleware('permission:manage users');
Route::get('/fraud-flags', [AdminController::class, 'fraudFlags'])->middleware('permission:monitor fraud');
