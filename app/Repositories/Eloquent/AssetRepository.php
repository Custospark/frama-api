<?php

namespace App\Repositories\Eloquent;

use App\Models\Asset;
use App\Repositories\Contracts\AssetRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class AssetRepository implements AssetRepositoryInterface
{
    public function allForBranch(int $branchId): Collection
    {
        return Asset::with('businessType')
            ->where('branch_id', $branchId)
            ->orderBy('business_type_id')
            ->orderBy('name')
            ->get();
    }

    public function find(int $id): ?Asset
    {
        return Asset::with('businessType')->find($id);
    }

    public function create(array $data): Asset
    {
        return Asset::create($data);
    }

    public function update(Asset $asset, array $data): Asset
    {
        $asset->update($data);

        return $asset->fresh();
    }

    public function delete(Asset $asset): bool
    {
        return $asset->delete();
    }
}
