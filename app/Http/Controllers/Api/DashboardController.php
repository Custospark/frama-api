<?php

namespace App\Http\Controllers\Api;

use App\Services\Contracts\DashboardServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    public function __construct(
        protected DashboardServiceInterface $dashboardService,
    ) {}

    public function summary(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->summary($request->query('date')),
        ]);
    }

    public function trend(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboardService->trend(
                $request->query('from'),
                $request->query('to'),
            ),
        ]);
    }
}
