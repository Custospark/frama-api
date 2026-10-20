<?php

namespace App\Services;

use App\Enums\CustomFieldScope;
use App\Models\Asset;
use App\Models\User;
use App\Repositories\Contracts\AssetRepositoryInterface;
use App\Services\Contracts\AssetServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

class AssetService implements AssetServiceInterface
{
    public function __construct(
        protected AssetRepositoryInterface $assetRepository,
        protected DynamicFieldValidator $dynamicFieldValidator,
    ) {}

    public function getAllForUser(User $user): Collection
    {
        if (! $user->branch_id) {
            return new Collection();
        }

        return $this->assetRepository->allForBranch($user->branch_id);
    }

    public function getById(User $user, int $id): ?Asset
    {
        $asset = $this->assetRepository->find($id);

        if (! $asset || $asset->branch_id !== $user->branch_id) {
            return null;
        }

        return $asset;
    }

    public function create(User $user, array $data): Asset
    {
        if (! $user->branch_id) {
            throw new RuntimeException('Your account is not linked to a branch.');
        }

        $data['branch_id'] = $user->branch_id;
        $data['custom_values'] = $this->extractCustomValues(
            (int) $data['business_type_id'],
            $user->branch_id,
            $data['custom_values'] ?? [],
        ) ?: null;

        return $this->assetRepository->create($data);
    }

    public function update(User $user, int $id, array $data): Asset
    {
        $asset = $this->assetRepository->find($id);

        if (! $asset || $asset->branch_id !== $user->branch_id) {
            throw new RuntimeException('Asset not found.');
        }

        unset($data['branch_id']);

        $businessTypeId = isset($data['business_type_id'])
            ? (int) $data['business_type_id']
            : $asset->business_type_id;

        if (array_key_exists('custom_values', $data)) {
            $data['custom_values'] = $this->extractCustomValues(
                (int) $businessTypeId,
                $user->branch_id,
                $data['custom_values'] ?? [],
            ) ?: null;
        }

        return $this->assetRepository->update($asset, $data);
    }

    public function delete(User $user, int $id): bool
    {
        $asset = $this->assetRepository->find($id);

        if (! $asset || $asset->branch_id !== $user->branch_id) {
            throw new RuntimeException('Asset not found.');
        }

        return $this->assetRepository->delete($asset);
    }

    /**
     * Persist the asset-scoped custom field values for a given business type.
     */
    protected function extractCustomValues(int $businessTypeId, ?int $branchId, array $input): array
    {
        $definitions = $this->dynamicFieldValidator->definitions(CustomFieldScope::Asset, $businessTypeId, $branchId);

        return $this->dynamicFieldValidator->extractValues($definitions, $input, 'assets/custom');
    }
}
