<?php

use App\Http\Controllers\Api\ReportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('reports', [ReportController::class, 'index']);
    Route::get('reports/export', [ReportController::class, 'export'])
        ->middleware('role:executive');
    Route::get('reports/{report}', [ReportController::class, 'show'])
        ->whereNumber('report');
    Route::get('reports/{report}/attachments/download-all', [ReportController::class, 'downloadAll'])
        ->name('reports.attachments.downloadAll')
        ->whereNumber('report');
    Route::get('reports/{report}/attachments/{attachment}/download', [ReportController::class, 'downloadAttachment'])
        ->name('reports.attachments.download')
        ->whereNumber('report')
        ->whereNumber('attachment');
    Route::post('reports', [ReportController::class, 'store'])
        ->middleware('role.exact:manager');
    Route::match(['post', 'put'], 'reports/{report}', [ReportController::class, 'update'])
        ->middleware('role.exact:manager')
        ->whereNumber('report');
    Route::post('reports/{report}/allow-edit', [ReportController::class, 'allowEdit'])
        ->middleware('role:executive')
        ->whereNumber('report');
});
