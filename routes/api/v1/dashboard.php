<?php

use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:executive'])->group(function () {
    Route::get('dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('dashboard/trend', [DashboardController::class, 'trend']);
});
