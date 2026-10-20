<?php

use App\Http\Controllers\Api\BranchController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:executive'])->group(function () {
    Route::get('branches', [BranchController::class, 'index']);
    Route::get('branches/{branch}', [BranchController::class, 'show']);
});

Route::middleware(['auth:sanctum', 'role:platform_admin'])->group(function () {
    Route::post('branches', [BranchController::class, 'store']);
    Route::put('branches/{branch}', [BranchController::class, 'update']);
    Route::delete('branches/{branch}', [BranchController::class, 'destroy']);
    Route::post('branches/{branch}/regenerate-code', [BranchController::class, 'regenerateCode']);
});
