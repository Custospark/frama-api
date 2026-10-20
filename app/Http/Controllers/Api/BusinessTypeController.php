<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreBusinessTypeRequest;
use App\Http\Requests\UpdateBusinessTypeRequest;
use App\Http\Resources\BusinessTypeResource;
use App\Services\Contracts\BusinessTypeServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class BusinessTypeController extends BaseApiController
{
    public function __construct(
        protected BusinessTypeServiceInterface $businessTypeService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only');

        return BusinessTypeResource::collection($this->businessTypeService->list($activeOnly))->response();
    }

    public function store(StoreBusinessTypeRequest $request): JsonResponse
    {
        try {
            $type = $this->businessTypeService->create($request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return (new BusinessTypeResource($type))->response()->setStatusCode(201);
    }

    public function update(UpdateBusinessTypeRequest $request, int $businessType): JsonResponse
    {
        try {
            $updated = $this->businessTypeService->update($businessType, $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return (new BusinessTypeResource($updated))->response();
    }

    public function destroy(int $businessType): JsonResponse
    {
        try {
            $this->businessTypeService->delete($businessType);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return ApiResponse::message('Business type deleted.');
    }
}
