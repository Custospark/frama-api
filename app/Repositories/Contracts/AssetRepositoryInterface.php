<?php

namespace App\Repositories\Contracts;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Collection;

interface AssetRepositoryInterface
{
    public function allForBranch(int $branchId): Collection;

    public function find(int $id): ?Asset;

    public function create(array $data): Asset;

    public function update(Asset $asset, array $data): Asset;

    public function delete(Asset $asset): bool;
}
