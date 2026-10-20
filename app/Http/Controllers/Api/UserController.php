<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ResetPinRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\Contracts\UserServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class UserController extends BaseApiController
{
    public function __construct(
        protected UserServiceInterface $userService,
    ) {}

    public function index(): JsonResponse
    {
        return UserResource::collection($this->userService->getAll())->response();
    }

    public function show(int $user): JsonResponse
    {
        $found = $this->userService->getById($user);

        if (! $found) {
            return ApiResponse::error('User not found.', 404);
        }

        return (new UserResource($found))->response();
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        try {
            $user = $this->userService->create($request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return (new UserResource($user->load('branch')))->response()->setStatusCode(201);
    }

    public function update(UpdateUserRequest $request, int $user): JsonResponse
    {
        try {
            $updated = $this->userService->update($user, $request->validated());
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return (new UserResource($updated))->response();
    }

    public function destroy(int $user): JsonResponse
    {
        try {
            $this->userService->delete($user);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return ApiResponse::message('User deleted.');
    }

    public function resetPin(ResetPinRequest $request, int $user): JsonResponse
    {
        try {
            $updated = $this->userService->resetPin($user, $request->validated('pin'));
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        }

        return (new UserResource($updated))->response();
    }
}
