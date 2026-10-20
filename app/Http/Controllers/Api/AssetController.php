<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Services\Contracts\AssetServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AssetController extends BaseApiController
{
    public function __construct(
        protected AssetServiceInterface $assetService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return AssetResource::collection(
            $this->assetService->getAllForUser($request->user())
        )->response();
    }

    public function show(Request $request, int $asset): JsonResponse
    {
        $found = $this->assetService->getById($request->user(), $asset);

        if (! $found) {
            return ApiResponse::error('Asset not found.', 404);
        }

        return (new AssetResource($found))->response();
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        try {
            $asset = $this->assetService->create($request->user(), $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return (new AssetResource($asset))->response()->setStatusCode(201);
    }

    public function update(UpdateAssetRequest $request, int $asset): JsonResponse
    {
        try {
            $updated = $this->assetService->update($request->user(), $asset, $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return (new AssetResource($updated))->response();
    }

    public function destroy(Request $request, int $asset): JsonResponse
    {
        try {
            $this->assetService->delete($request->user(), $asset);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return ApiResponse::message('Asset deleted.');
    }
}
