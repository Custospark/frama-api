<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\BranchShareResource;
use App\Services\Contracts\BranchShareServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchShareController extends BaseApiController
{
    public function __construct(
        protected BranchShareServiceInterface $branchShareService,
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $summary = $this->branchShareService->summary($slug);

        if (! $summary) {
            return ApiResponse::error('This branch link is invalid.', 404);
        }

        $user = $request->user();

        if (! $user->canViewAllBranches() && (int) $summary['branch']['id'] !== $user->branch_id) {
            return ApiResponse::error('This branch link is not available to your account.', 403);
        }

        return (new BranchShareResource($summary))->response();
    }
}
