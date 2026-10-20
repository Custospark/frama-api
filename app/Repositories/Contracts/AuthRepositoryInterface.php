<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface AuthRepositoryInterface
{
    /** Resolve the user for an access code (branch code or org code), or null. */
    public function findByAccessCode(string $accessCode): ?User;
}
