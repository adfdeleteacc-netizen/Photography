<?php

use App\Http\Controllers\Studio\StudioController;
use Illuminate\Support\Facades\Route;

// Loaded under: /api/studio, auth:sanctum, role:studio
Route::middleware('subscribed')->group(function () {
    Route::get('/{studio}/team', [StudioController::class, 'team']);
    Route::post('/{studio}/team', [StudioController::class, 'addMember']);
    Route::delete('/{studio}/team/{member}', [StudioController::class, 'removeMember']);
});
