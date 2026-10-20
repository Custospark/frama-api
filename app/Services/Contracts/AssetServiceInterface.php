<?php

namespace App\Services\Contracts;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

interface AssetServiceInterface
{
    public function getAllForUser(User $user): Collection;

    public function getById(User $user, int $id): ?Asset;

    public function create(User $user, array $data): Asset;

    public function update(User $user, int $id, array $data): Asset;

    public function delete(User $user, int $id): bool;
}
