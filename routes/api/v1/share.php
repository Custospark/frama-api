<?php

use App\Http\Controllers\Api\BranchShareController;
use Illuminate\Support\Facades\Route;

/**
 * Shareable branch summary - reachable by any authenticated user (managers
 * share the link, but viewers must log in first). The branch slug in the URL
 * keeps links short and readable while still scoping the response to a single
 * branch. No receipt images are exposed.
 */
Route::middleware('auth:sanctum')->group(function () {
    Route::get('share/branch/{slug}', [BranchShareController::class, 'show']);
});
