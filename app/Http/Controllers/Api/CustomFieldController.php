<?php

namespace App\Http\Controllers\Api;

use App\Enums\CustomFieldScope;
use App\Http\Requests\StoreCustomFieldRequest;
use App\Http\Requests\UpdateCustomFieldRequest;
use App\Http\Resources\CustomFieldResource;
use App\Services\Contracts\CustomFieldServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CustomFieldController extends BaseApiController
{
    public function __construct(
        protected CustomFieldServiceInterface $customFieldService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $scope = $request->query('scope') ? CustomFieldScope::tryFrom($request->query('scope')) : null;
        $businessTypeId = $request->query('business_type_id');
        $businessTypeId = $businessTypeId !== null && $businessTypeId !== '' ? (int) $businessTypeId : null;

        $fields = $this->customFieldService->list($request->user(), $scope, $businessTypeId);

        return CustomFieldResource::collection($fields)->response();
    }

    public function store(StoreCustomFieldRequest $request): JsonResponse
    {
        try {
            $field = $this->customFieldService->create($request->user(), $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return (new CustomFieldResource($field))->response()->setStatusCode(201);
    }

    public function update(UpdateCustomFieldRequest $request, string $customField): JsonResponse
    {
        try {
            $field = $this->customFieldService->update($request->user(), (int) $customField, $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return (new CustomFieldResource($field))->response();
    }

    public function destroy(Request $request, string $customField): JsonResponse
    {
        try {
            $this->customFieldService->delete($request->user(), (int) $customField);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return ApiResponse::message('Custom field deleted.');
    }
}
