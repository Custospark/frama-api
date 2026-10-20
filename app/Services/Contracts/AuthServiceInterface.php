<?php

namespace App\Services\Contracts;

use App\Models\User;

interface AuthServiceInterface
{
    /**
     * Authenticate by access code + PIN.
     *
     * @return array{user: User, token: string}
     *
     * @throws \RuntimeException when the code/PIN combination is invalid.
     */
    public function login(string $accessCode, string $pin): array;

    public function logout(User $user): void;

    public function changePin(User $user, string $currentPin, string $newPin): void;

    /**
     * Change a PIN without an authenticated session: the access code + current
     * PIN together prove ownership. No email verification is involved.
     *
     * @throws \RuntimeException when the access code / current PIN is invalid.
     */
    public function changePinByAccessCode(string $accessCode, string $currentPin, string $newPin): void;
}
