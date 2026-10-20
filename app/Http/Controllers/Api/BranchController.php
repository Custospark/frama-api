<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Services\Contracts\BranchServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class BranchController extends BaseApiController
{
    public function __construct(
        protected BranchServiceInterface $branchService,
    ) {}

    public function index(): JsonResponse
    {
        return BranchResource::collection($this->branchService->getAll())->response();
    }

    public function show(int $branch): JsonResponse
    {
        $found = $this->branchService->getById($branch);

        if (! $found) {
            return ApiResponse::error('Branch not found.', 404);
        }

        return (new BranchResource($found))->response();
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        try {
            $branch = $this->branchService->create($request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }

    public function update(UpdateBranchRequest $request, int $branch): JsonResponse
    {
        try {
            $updated = $this->branchService->update($branch, $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return (new BranchResource($updated))->response();
    }

    public function destroy(int $branch): JsonResponse
    {
        try {
            $this->branchService->delete($branch);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return ApiResponse::message('Branch deleted.');
    }

    public function regenerateCode(int $branch): JsonResponse
    {
        try {
            $updated = $this->branchService->regenerateCode($branch);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return (new BranchResource($updated))->response();
    }
}
