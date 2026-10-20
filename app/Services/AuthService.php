<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Services\Contracts\AuthServiceInterface;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        protected AuthRepositoryInterface $authRepository,
    ) {}

    public function login(string $accessCode, string $pin): array
    {
        $user = $this->authRepository->findByAccessCode($accessCode);

        if (! $user || ! $user->pin_hash || ! Hash::check($pin, $user->pin_hash)) {
            throw new RuntimeException('Invalid access code or PIN.');
        }

        if (! $user->is_active) {
            throw new RuntimeException('Your account is not active.');
        }

        $user->forceFill(['last_login_at' => now()])->save();

        $token = $user->createToken('frama-auth')->plainTextToken;

        return ['user' => $user->loadMissing('branch.businessType'), 'token' => $token];
    }

    public function logout(User $user): void
    {
        $user->currentAccessToken()?->delete();
    }

    public function changePin(User $user, string $currentPin, string $newPin): void
    {
        if (! $user->pin_hash || ! Hash::check($currentPin, $user->pin_hash)) {
            throw new RuntimeException('Your current PIN is incorrect.');
        }

        $user->forceFill(['pin_hash' => Hash::make($newPin)])->save();
    }

    public function changePinByAccessCode(string $accessCode, string $currentPin, string $newPin): void
    {
        $user = $this->authRepository->findByAccessCode($accessCode);

        if (! $user || ! $user->pin_hash || ! Hash::check($currentPin, $user->pin_hash)) {
            throw new RuntimeException('Invalid access code or current PIN.');
        }

        if (! $user->is_active) {
            throw new RuntimeException('Your account is not active.');
        }

        $user->forceFill(['pin_hash' => Hash::make($newPin)])->save();
    }
}
