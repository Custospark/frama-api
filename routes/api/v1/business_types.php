<?php

use App\Http\Controllers\Api\BusinessTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('business-types', [BusinessTypeController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'role.exact:platform_admin'])->group(function () {
    Route::post('business-types', [BusinessTypeController::class, 'store']);
    Route::put('business-types/{businessType}', [BusinessTypeController::class, 'update']);
    Route::delete('business-types/{businessType}', [BusinessTypeController::class, 'destroy']);
});
