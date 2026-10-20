<?php

namespace App\Services\Contracts;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;

interface BranchServiceInterface
{
    public function getAll(): Collection;

    public function getById(int $id): ?Branch;

    public function create(array $data): Branch;

    public function update(int $id, array $data): Branch;

    public function delete(int $id): bool;

    public function regenerateCode(int $id): Branch;
}
