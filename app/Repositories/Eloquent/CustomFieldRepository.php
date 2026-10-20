<?php

namespace App\Repositories\Eloquent;

use App\Enums\CustomFieldScope;
use App\Models\CustomField;
use App\Repositories\Contracts\CustomFieldRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class CustomFieldRepository implements CustomFieldRepositoryInterface
{
    public function allForBranch(?int $branchId, ?CustomFieldScope $scope = null, ?int $businessTypeId = null): Collection
    {
        return CustomField::query()
            ->with('businessType')
            ->when($branchId, fn ($q) => $q->where(fn ($inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $branchId)))
            ->when(! $branchId, fn ($q) => $q->whereNull('branch_id'))
            ->when($scope, fn ($q) => $q->where('scope', $scope->value))
            ->when($businessTypeId, fn ($q) => $q->where('business_type_id', $businessTypeId))
            ->orderBy('scope')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?CustomField
    {
        return CustomField::find($id);
    }

    public function create(array $data): CustomField
    {
        return CustomField::create($data);
    }

    public function update(CustomField $field, array $data): CustomField
    {
        $field->update($data);

        return $field->fresh();
    }

    public function delete(CustomField $field): bool
    {
        return $field->delete();
    }

    public function keyExists(CustomFieldScope $scope, ?int $businessTypeId, ?int $branchId, string $key, ?int $ignoreId = null): bool
    {
        return CustomField::query()
            ->where('scope', $scope->value)
            ->where('business_type_id', $businessTypeId)
            ->where('branch_id', $branchId)
            ->where('key', $key)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
