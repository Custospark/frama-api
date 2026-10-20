<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', function () {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
        ]);
    });

    require __DIR__ . '/api/v1/auth.php';
    require __DIR__ . '/api/v1/branches.php';
    require __DIR__ . '/api/v1/assets.php';
    require __DIR__ . '/api/v1/business_types.php';
    require __DIR__ . '/api/v1/custom_fields.php';
    require __DIR__ . '/api/v1/reports.php';
    require __DIR__ . '/api/v1/share.php';
    require __DIR__ . '/api/v1/dashboard.php';
    require __DIR__ . '/api/v1/users.php';
});
