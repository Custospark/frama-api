<?php

namespace App\Repositories\Contracts;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Collection;

interface BranchRepositoryInterface
{
    public function all(): Collection;

    public function find(int $id): ?Branch;

    public function findByCode(string $code): ?Branch;

    public function findBySlug(string $slug): ?Branch;

    public function create(array $data): Branch;

    public function update(Branch $branch, array $data): Branch;

    public function delete(Branch $branch): bool;

    public function codeExists(string $code, ?int $ignoreId = null): bool;
}
