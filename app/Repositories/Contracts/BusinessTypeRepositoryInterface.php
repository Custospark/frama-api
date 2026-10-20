<?php

namespace App\Repositories\Contracts;

use App\Models\BusinessType;
use Illuminate\Database\Eloquent\Collection;

interface BusinessTypeRepositoryInterface
{
    public function all(bool $activeOnly = false): Collection;

    public function find(int $id): ?BusinessType;

    public function create(array $data): BusinessType;

    public function update(BusinessType $businessType, array $data): BusinessType;

    public function delete(BusinessType $businessType): bool;

    public function slugExists(string $slug, ?int $ignoreId = null): bool;
}
