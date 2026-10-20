<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ChangePinByCodeRequest;
use App\Http\Requests\ChangePinRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\Contracts\AuthServiceInterface;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AuthController extends BaseApiController
{
    public function __construct(
        protected AuthServiceInterface $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->validated('access_code'),
                $request->validated('pin'),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 401);
        }

        return response()->json([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return ApiResponse::message('Signed out.');
    }

    public function me(Request $request): JsonResponse
    {
        return (new UserResource($request->user()->load('branch.businessType')))
            ->response();
    }

    public function changePin(ChangePinRequest $request): JsonResponse
    {
        try {
            $this->authService->changePin(
                $request->user(),
                $request->validated('current_pin'),
                $request->validated('pin'),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::message('PIN updated.');
    }

    public function changePinByCode(ChangePinByCodeRequest $request): JsonResponse
    {
        try {
            $this->authService->changePinByAccessCode(
                $request->validated('access_code'),
                $request->validated('current_pin'),
                $request->validated('pin'),
            );
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::message('PIN updated. You can now sign in with your new PIN.');
    }
}
