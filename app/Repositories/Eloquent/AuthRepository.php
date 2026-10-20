<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;

class AuthRepository implements AuthRepositoryInterface
{
    public function findByAccessCode(string $accessCode): ?User
    {
        $accessCode = strtoupper(trim($accessCode));

        if ($accessCode === '') {
            return null;
        }

        return User::query()
            ->whereRaw('UPPER(access_code) = ?', [$accessCode])
            ->where('is_active', true)
            ->first();
    }
}
