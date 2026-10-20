<?php

namespace App\Repositories\Eloquent;

use App\Models\Branch;
use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;

class AuthRepository implements AuthRepositoryInterface
{
    public function findByAccessCode(string $accessCode): ?User
    {
        $accessCode = trim($accessCode);

        if (strcasecmp($accessCode, (string) config('app.org_code')) === 0) {
            return User::whereIn('role', ['executive', 'platform_admin'])
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        $branch = Branch::where('code', strtoupper($accessCode))
            ->where('is_active', true)
            ->first();

        if (! $branch) {
            return null;
        }

        return User::where('branch_id', $branch->id)
            ->where('is_active', true)
            ->where('role', 'manager')
            ->orderBy('id')
            ->first();
    }
}
