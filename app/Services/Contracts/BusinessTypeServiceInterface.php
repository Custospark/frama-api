<?php

namespace App\Services\Contracts;

use App\Models\BusinessType;
use Illuminate\Database\Eloquent\Collection;

interface BusinessTypeServiceInterface
{
    public function list(bool $activeOnly = false): Collection;

    public function create(array $data): BusinessType;

    public function update(int $id, array $data): BusinessType;

    public function delete(int $id): bool;
}
