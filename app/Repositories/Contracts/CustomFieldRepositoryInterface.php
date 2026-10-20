<?php

namespace App\Repositories\Contracts;

use App\Enums\CustomFieldScope;
use App\Models\CustomField;
use Illuminate\Database\Eloquent\Collection;

interface CustomFieldRepositoryInterface
{
    /**
     * Definitions manageable by a user: org-wide + their branch's own.
     *
     * @return Collection<int, CustomField>
     */
    public function allForBranch(?int $branchId, ?CustomFieldScope $scope = null, ?int $businessTypeId = null): Collection;

    public function find(int $id): ?CustomField;

    public function create(array $data): CustomField;

    public function update(CustomField $field, array $data): CustomField;

    public function delete(CustomField $field): bool;

    public function keyExists(CustomFieldScope $scope, ?int $businessTypeId, ?int $branchId, string $key, ?int $ignoreId = null): bool;
}
