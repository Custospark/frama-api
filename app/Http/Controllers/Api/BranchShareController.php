<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\BranchShareResource;
use App\Services\Contracts\BranchShareServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BranchShareController extends BaseApiController
{
    public function __construct(
        protected BranchShareServiceInterface $branchShareService,
    ) {}

    public function show(string $slug): JsonResponse
    {
        $summary = $this->branchShareService->summary($slug);

        if (! $summary) {
            return ApiResponse::error('This branch link is invalid.', 404);
        }

        return (new BranchShareResource($summary))->response();
    }
}
