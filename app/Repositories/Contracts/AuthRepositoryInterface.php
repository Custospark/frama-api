<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface AuthRepositoryInterface
{
    /** Resolve the user for their unique access code, or null. */
    public function findByAccessCode(string $accessCode): ?User;
}
