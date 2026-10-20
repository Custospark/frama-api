<?php

use App\Http\Controllers\Api\CustomFieldController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:manager'])->group(function () {
    Route::get('custom-fields', [CustomFieldController::class, 'index']);
    Route::post('custom-fields', [CustomFieldController::class, 'store']);
    Route::put('custom-fields/{customField}', [CustomFieldController::class, 'update']);
    Route::delete('custom-fields/{customField}', [CustomFieldController::class, 'destroy']);
});
